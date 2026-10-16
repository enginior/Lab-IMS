<?php
// Optional local configuration override (ignored by git)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

$servername = getenv('DB_HOST') ?: ($db_host ?? "localhost");
$username   = getenv('DB_USER') ?: ($db_user ?? "root"); // Default XAMPP/local user
$password   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($db_pass ?? ""); // Default XAMPP password is empty
$dbname     = getenv('DB_NAME') ?: ($db_name ?? "inventory_db");

// Create connection
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please check your configuration.");
}

$conn->set_charset("utf8mb4");

/**
 * Generate or retrieve the current session CSRF token.
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token against the session token.
 */
function verify_csrf_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate and store an uploaded item image safely.
 * Returns ['filename' => string|null, 'error' => string|null]
 */
function handle_item_image_upload(array $file, string $upload_dir = '../assets/uploads/'): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['filename' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['filename' => null, 'error' => 'File upload error (code ' . (int)$file['error'] . ').'];
    }

    // Max 5MB
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['filename' => null, 'error' => 'Image exceeds the 5MB size limit.'];
    }

    // Verify MIME type using finfo
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($allowed_mimes[$mime])) {
        return ['filename' => null, 'error' => 'Invalid file type. Only JPG, PNG, and WEBP images are allowed.'];
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = $allowed_mimes[$mime];
    $new_filename = time() . "_" . random_int(1000, 9999) . "." . $ext;
    $upload_path = rtrim($upload_dir, '/') . '/' . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['filename' => null, 'error' => 'Failed to move uploaded file. Check folder permissions on assets/uploads.'];
    }

    return ['filename' => $new_filename, 'error' => null];
}

/**
 * Automatically ensure new v2 columns exist in the inventory table and backfill existing items.
 */
function ensure_inventory_schema(mysqli $conn): void {
    $res = $conn->query("SHOW COLUMNS FROM `inventory` LIKE 'component_rating'");
    if ($res && $res->num_rows === 0) {
        $alter_sql = "ALTER TABLE `inventory`
            ADD COLUMN `item_type` ENUM('Bulk', 'Unique') NOT NULL DEFAULT 'Bulk' AFTER `sub_category`,
            ADD COLUMN `component_rating` VARCHAR(120) DEFAULT NULL AFTER `item_type`,
            ADD COLUMN `model_number` VARCHAR(100) DEFAULT NULL AFTER `component_rating`,
            ADD COLUMN `serial_number` VARCHAR(120) DEFAULT NULL AFTER `model_number`,
            ADD COLUMN `condition_status` ENUM('New', 'Working', 'Untested', 'For Parts') NOT NULL DEFAULT 'Working' AFTER `serial_number`";
        $conn->query($alter_sql);

        // Enrich existing default/current items automatically
        $conn->query("UPDATE `inventory` SET `item_type`='Bulk', `sub_category`='Passive - Resistor', `component_rating`='1/2W' WHERE `id`=1 AND `component_rating` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Bulk', `sub_category`='Passive - Capacitor', `component_rating`='4700uF | 25V | Electrolytic' WHERE `id`=6 AND `component_rating` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Unique', `sub_category`='Measurement / Multimeter', `model_number`='ennoLogic' WHERE `id`=7 AND `model_number` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Bulk', `sub_category`='IC / Logic / Op-Amp', `component_rating`='Decade Counter | DIP-16', `model_number`='CD4017' WHERE `id`=8 AND `component_rating` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Unique', `sub_category`='Storage - HDD', `component_rating`='5400 RPM | SATA 3.5\"', `model_number`='1EHRANJ2', `serial_number`='eaEGgafhq113t', `condition_status`='New' WHERE `id`=9 AND `serial_number` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Unique', `sub_category`='Memory / RAM', `component_rating`='16GB | DDR5' WHERE `id`=11 AND `component_rating` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Unique', `sub_category`='Microcontroller / Dev Board', `component_rating`='ATmega328P | DIP', `model_number`='UNO R3' WHERE `id`=12 AND `component_rating` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Unique', `sub_category`='CPU / Processor', `component_rating`='24-Core | sTR5', `model_number`='7960X' WHERE `id`=13 AND `component_rating` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Unique', `sub_category`='Storage - SSD / NVMe', `component_rating`='1TB | PCIe NVMe M.2', `model_number`='MZ-V8V1T0' WHERE `id`=14 AND `component_rating` IS NULL");
        $conn->query("UPDATE `inventory` SET `item_type`='Unique', `sub_category`='Microcontroller / Dev Board', `component_rating`='38-Pin | Wi-Fi + BT', `model_number`='ESP-WROOM-32' WHERE `id`=15 AND `component_rating` IS NULL");
    }

    // Ensure min_quantity column exists for Low-Stock / Out-of-Stock alerts
    $min_res = $conn->query("SHOW COLUMNS FROM `inventory` LIKE 'min_quantity'");
    if ($min_res && $min_res->num_rows === 0) {
        $conn->query("ALTER TABLE `inventory` ADD COLUMN `min_quantity` INT(11) NOT NULL DEFAULT 2 AFTER `quantity`");
        // Set sensible defaults: 5 for Bulk small components, 0 for unique one-off hardware/collectibles
        $conn->query("UPDATE `inventory` SET `min_quantity` = 5 WHERE `item_type` = 'Bulk'");
        $conn->query("UPDATE `inventory` SET `min_quantity` = 0 WHERE `item_type` = 'Unique'");
    }

    // Ensure datasheet_url column exists for IC/component/board manuals and pinouts
    $ds_res = $conn->query("SHOW COLUMNS FROM `inventory` LIKE 'datasheet_url'");
    if ($ds_res && $ds_res->num_rows === 0) {
        $conn->query("ALTER TABLE `inventory` ADD COLUMN `datasheet_url` VARCHAR(500) DEFAULT NULL AFTER `specifications`");
        // Backfill sample datasheets for default electronics components
        $conn->query("UPDATE `inventory` SET `datasheet_url` = 'https://www.ti.com/lit/ds/symlink/cd4017b.pdf' WHERE `id` = 8 AND (`datasheet_url` IS NULL OR `datasheet_url` = '')");
        $conn->query("UPDATE `inventory` SET `datasheet_url` = 'https://docs.espressif.com/projects/esp-idf/en/latest/esp32/hw-reference/esp32/get-started-devkitc.html' WHERE `id` = 15 AND (`datasheet_url` IS NULL OR `datasheet_url` = '')");
        $conn->query("UPDATE `inventory` SET `datasheet_url` = 'https://docs.arduino.cc/resources/datasheets/A000066-datasheet.pdf' WHERE `id` = 12 AND (`datasheet_url` IS NULL OR `datasheet_url` = '')");
    }

    // Ensure role column exists in users table
    $u_res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    if ($u_res && $u_res->num_rows === 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `role` ENUM('Admin', 'Operator') NOT NULL DEFAULT 'Admin' AFTER `password`");
    }

    // Ensure inventory_logs table exists for Stock Movement & Audit Trail
    $conn->query("CREATE TABLE IF NOT EXISTS `inventory_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) DEFAULT NULL,
        `item_name` VARCHAR(150) NOT NULL,
        `user_id` INT(11) DEFAULT NULL,
        `username` VARCHAR(50) NOT NULL DEFAULT 'System',
        `change_type` ENUM('quick_adjust', 'manual_edit', 'item_created', 'item_deleted', 'restock') NOT NULL,
        `delta` INT(11) NOT NULL,
        `old_quantity` INT(11) NOT NULL,
        `new_quantity` INT(11) NOT NULL,
        `notes` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_item_id` (`item_id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_change_type` (`change_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

ensure_inventory_schema($conn);

/**
 * Record a stock change or audit event in inventory_logs.
 */
function log_stock_movement(
    mysqli $conn,
    ?int $item_id,
    string $item_name,
    ?int $user_id,
    string $username,
    string $change_type,
    int $delta,
    int $old_qty,
    int $new_qty,
    ?string $notes = null
): bool {
    $stmt = $conn->prepare("INSERT INTO `inventory_logs` 
        (`item_id`, `item_name`, `user_id`, `username`, `change_type`, `delta`, `old_quantity`, `new_quantity`, `notes`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Failed to prepare audit log statement: " . $conn->error);
        return false;
    }
    $stmt->bind_param(
        "isisssiis",
        $item_id,
        $item_name,
        $user_id,
        $username,
        $change_type,
        $delta,
        $old_qty,
        $new_qty,
        $notes
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Sanitize and validate an external URL (such as a datasheet or manual link).
 */
function sanitize_external_url(?string $url): ?string {
    if ($url === null) {
        return null;
    }
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    // Automatically prepend https:// if protocol is omitted
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    // Validate syntax
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}

/**
 * Evaluate stock status ('out', 'low', or 'ok') based on current quantity and min_quantity threshold.
 */
function get_stock_status(int $quantity, int $min_quantity): array {
    if ($quantity <= 0) {
        return [
            'code'  => 'out',
            'label' => 'Out of Stock',
            'icon'  => 'fa-circle-xmark',
            'class' => 'bg-red-950/90 text-red-300 border-red-500/60'
        ];
    }
    if ($min_quantity > 0 && $quantity <= $min_quantity) {
        return [
            'code'  => 'low',
            'label' => 'Low Stock',
            'icon'  => 'fa-triangle-exclamation',
            'class' => 'bg-orange-950/90 text-orange-300 border-orange-500/60'
        ];
    }
    return [
        'code'  => 'ok',
        'label' => 'In Stock',
        'icon'  => 'fa-circle-check',
        'class' => 'bg-emerald-950/80 text-emerald-300 border-emerald-600/50'
    ];
}

/**
 * Structured Category -> Sub-Category taxonomy for Electronics, Computer Parts, Tools, and Other.
 */
function get_category_presets(): array {
    return [
        'Electronic' => [
            'Passive - Resistor',
            'Passive - Capacitor',
            'Passive - Inductor / Ferrite',
            'Discrete - Diode / Transistor / MOSFET',
            'IC / Logic / Op-Amp',
            'Microcontroller / Dev Board',
            'Sensor / Module',
            'Power / Voltage Regulator',
            'Connector / Header / Wire',
            'Display / LED / Optoelectronics',
            'PCB / Breadboard'
        ],
        'Computer' => [
            'CPU / Processor',
            'GPU / Graphics Card',
            'Motherboard',
            'Memory / RAM',
            'Storage - SSD / NVMe',
            'Storage - HDD',
            'Power Supply (PSU)',
            'Cooling / Fan / Heatsink',
            'Network / NIC / Switch',
            'Peripherals / Adapter / Cable',
            'Chassis / Case'
        ],
        'Tool' => [
            'Measurement / Multimeter',
            'Oscilloscope / Logic Analyzer',
            'Soldering / Rework Station',
            'Power Supply / Load Tester',
            'Hand Tool / Driver / Pliers',
            'Crimp / Wire Tool',
            'Consumables (Solder, Flux, Tape)'
        ],
        'Other' => [
            'Scale Model / Collectible',
            'Enclosure / Storage Bin',
            'Fasteners / Screws / Standoffs',
            'Documentation / Reference',
            'Miscellaneous'
        ]
    ];
}


