<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require '../includes/db_conn.php';

$mode = $_GET['mode'] ?? 'locations'; // 'locations' or 'items'
$loc_filter = trim($_GET['loc'] ?? '');
$target_item_id = isset($_GET['item_id']) && is_numeric($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($target_item_id > 0) {
    $mode = 'items';
}

// Fetch all available unique locations
$locs_res = $conn->query("SELECT DISTINCT location FROM inventory WHERE location IS NOT NULL AND TRIM(location) != '' ORDER BY location ASC");
$all_locations = [];
while ($lrow = $locs_res->fetch_assoc()) {
    $all_locations[] = $lrow['location'];
}

// Fetch data based on mode
$location_data = [];
$items_data = [];

if ($mode === 'locations') {
    $loc_sql = "SELECT * FROM inventory WHERE location IS NOT NULL AND TRIM(location) != ''";
    $loc_params = [];
    $loc_types = "";

    if ($loc_filter !== '') {
        $loc_sql .= " AND location = ?";
        $loc_params[] = $loc_filter;
        $loc_types = "s";
    }

    $loc_sql .= " ORDER BY location ASC, item_name ASC";
    $stmt = $conn->prepare($loc_sql);
    if ($loc_types !== "") {
        $stmt->bind_param($loc_types, ...$loc_params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $loc_name = $row['location'];
        if (!isset($location_data[$loc_name])) {
            $location_data[$loc_name] = [
                'location'   => $loc_name,
                'items'      => [],
                'total_qty'  => 0,
                'item_count' => 0
            ];
        }
        $location_data[$loc_name]['items'][] = $row;
        $location_data[$loc_name]['total_qty'] += (int)$row['quantity'];
        $location_data[$loc_name]['item_count']++;
    }
    $stmt->close();
} else {
    // Mode is 'items'
    if ($target_item_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->bind_param("i", $target_item_id);
    } elseif ($loc_filter !== '') {
        $stmt = $conn->prepare("SELECT * FROM inventory WHERE location = ? ORDER BY item_name ASC");
        $stmt->bind_param("s", $loc_filter);
    } else {
        $stmt = $conn->prepare("SELECT * FROM inventory ORDER BY location ASC, item_name ASC LIMIT 100");
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items_data[] = $row;
    }
    $stmt->close();
}

// Automatically detect host URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$parent_dir = rtrim(str_replace('\\', '/', dirname($script_dir)), '/');
$detected_base = $protocol . "://" . $host . $parent_dir;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Labels - Lab Inventory</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Favicons & App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="../favicon.ico">

    <!-- Standalone local QR Code generator (100% offline capable) -->
    <script src="../assets/js/qrcode.min.js"></script>

    <style>
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .print-label {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                border: 1.5px solid #000000 !important;
                background: #ffffff !important;
                color: #000000 !important;
                box-shadow: none !important;
            }
            .print-badge {
                border: 1px solid #000000 !important;
                background: #f0f0f0 !important;
                color: #000000 !important;
            }
            .label-grid {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 12px !important;
                padding: 0 !important;
            }
            .label-compact .label-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 8px !important;
            }
        }
    </style>
</head>

<body class="bg-slate-900 text-slate-200 min-h-screen">

    <!-- Interactive Top Control Toolbar (Hidden in Print) -->
    <header class="no-print bg-slate-800 border-b border-slate-700 shadow-md sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">

            <!-- Title & Mode Tabs -->
            <div class="flex items-center gap-4">
                <a href="inventory.php" class="text-slate-400 hover:text-white transition flex items-center gap-1.5 text-sm" title="Back to Parts List">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span class="hidden sm:inline">Inventory</span>
                </a>
                <div class="h-6 w-px bg-slate-700"></div>
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-qrcode text-xl text-blue-400"></i>
                    <h1 class="font-bold text-lg text-white">QR Label Generator</h1>
                </div>

                <div class="inline-flex rounded-lg bg-slate-900 p-1 border border-slate-700 text-xs">
                    <a href="?mode=locations<?php echo $loc_filter !== '' ? '&loc=' . urlencode($loc_filter) : ''; ?>"
                        class="px-3 py-1.5 rounded-md font-semibold transition <?php echo $mode === 'locations' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'; ?>">
                        <i class="fa-solid fa-boxes-stacked mr-1"></i> Storage Drawers
                    </a>
                    <a href="?mode=items<?php echo $loc_filter !== '' ? '&loc=' . urlencode($loc_filter) : ''; ?>"
                        class="px-3 py-1.5 rounded-md font-semibold transition <?php echo $mode === 'items' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'; ?>">
                        <i class="fa-solid fa-microchip mr-1"></i> Individual Parts
                    </a>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Location Filter Dropdown -->
                <form method="GET" class="inline-flex items-center gap-1 text-xs">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                    <select name="loc" onchange="this.form.submit()"
                        class="bg-slate-900 border border-slate-600 text-white text-xs rounded-lg px-2.5 py-2 focus:outline-none focus:border-blue-500">
                        <option value="">📍 All Locations (<?php echo count($all_locations); ?>)</option>
                        <?php foreach ($all_locations as $l): ?>
                            <option value="<?php echo htmlspecialchars($l); ?>" <?php if ($loc_filter === $l) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($l); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <!-- Label Size Selector -->
                <select id="size-selector" onchange="updateLabelSize(this.value)"
                    class="bg-slate-900 border border-slate-600 text-white text-xs rounded-lg px-2.5 py-2 focus:outline-none focus:border-blue-500">
                    <option value="standard">Standard (3.5" x 2")</option>
                    <option value="compact">Compact Bin (2.5" x 1.25")</option>
                    <option value="detailed">Detailed / Large (4" x 2.5")</option>
                </select>

                <!-- Print Button -->
                <button onclick="window.print()"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-lg transition flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-print"></i> Print Labels
                </button>
            </div>
        </div>

        <!-- Optional LAN Base URL Override Banner -->
        <div class="bg-slate-900/90 border-t border-slate-700/80 px-4 py-2 text-xs text-slate-400">
            <div class="container mx-auto flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-network-wired text-cyan-400"></i>
                    <span>Target Base URL for Phone QR Scans:</span>
                    <input type="text" id="base-url-input" value="<?php echo htmlspecialchars($detected_base); ?>"
                        class="bg-slate-950 border border-slate-700 text-cyan-300 font-mono text-xs rounded px-2.5 py-1 w-64 focus:outline-none focus:border-cyan-400"
                        title="If scanning QR codes from mobile phones in your lab, set this to your Debian LXC LAN IP (e.g. http://192.168.1.50:8080)">
                    <button type="button" onclick="regenerateAllQRCodes()"
                        class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 px-2.5 py-1 rounded text-xs transition cursor-pointer">
                        Apply URL
                    </button>
                </div>
                <div class="flex items-center gap-4 text-[11px] text-slate-400">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" id="toggle-summary" checked onchange="toggleSummary(this.checked)" class="rounded border-slate-700 text-blue-600">
                        <span>Show Components List</span>
                    </label>
                    <span class="text-slate-600">•</span>
                    <span>Ready for Sheet / Sticker Paper</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Printable Area -->
    <main class="container mx-auto px-4 py-8 max-w-6xl">

        <?php if ($mode === 'locations'): ?>
            <!-- STORAGE DRAWER & RACK LABELS -->
            <?php if (empty($location_data)): ?>
                <div class="text-center py-16 text-slate-500 no-print">
                    <i class="fa-solid fa-box-archive text-5xl mb-3"></i>
                    <p class="text-lg">No storage locations found.</p>
                    <a href="inventory.php" class="text-blue-400 hover:underline text-sm mt-2 inline-block">Return to Inventory</a>
                </div>
            <?php else: ?>
                <div id="labels-container" class="label-grid grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($location_data as $loc_name => $data): ?>
                        <div class="print-label bg-slate-800 border-2 border-slate-600 rounded-xl p-5 shadow-lg flex flex-col justify-between transition relative overflow-hidden">
                            
                            <!-- Header Row: Lab Title & Location Name -->
                            <div class="flex justify-between items-start mb-3 border-b border-slate-700 pb-3">
                                <div>
                                    <span class="text-[10px] uppercase font-bold tracking-widest text-slate-400 block">LAB STORAGE DRAWER</span>
                                    <h2 class="text-2xl font-black text-white tracking-wide uppercase flex items-center gap-2 mt-0.5">
                                        <i class="fa-solid fa-location-dot text-blue-400 text-lg"></i>
                                        <?php echo htmlspecialchars($loc_name); ?>
                                    </h2>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-bold px-2 py-0.5 rounded bg-blue-950 text-blue-300 border border-blue-700/60 print-badge">
                                        <?php echo $data['item_count']; ?> Types &bull; <?php echo $data['total_qty']; ?> Qty
                                    </span>
                                </div>
                            </div>

                            <!-- Body: QR Code + Content Summary -->
                            <div class="flex items-center gap-4 my-2">
                                <!-- QR Code Box -->
                                <div class="bg-white p-2.5 rounded-lg border border-slate-300 flex-shrink-0 flex items-center justify-center">
                                    <div class="qr-target" data-qr-path="/pages/inventory.php?loc=<?php echo urlencode($loc_name); ?>" id="qr-loc-<?php echo md5($loc_name); ?>"></div>
                                </div>

                                <!-- Right side info & items list -->
                                <div class="flex-grow min-w-0">
                                    <div class="text-xs text-slate-400 mb-1 flex items-center gap-1 font-mono">
                                        <i class="fa-solid fa-mobile-screen-button text-[10px]"></i>
                                        <span>Scan to view/update drawer</span>
                                    </div>

                                    <!-- Drawer Contents Preview -->
                                    <div class="loc-summary-list flex flex-wrap gap-1 mt-2 max-h-24 overflow-hidden">
                                        <?php
                                        $shown_items = array_slice($data['items'], 0, 8);
                                        foreach ($shown_items as $it):
                                        ?>
                                            <span class="text-[11px] px-2 py-0.5 rounded bg-slate-900 border border-slate-700 text-slate-300 print-badge truncate max-w-[200px]"
                                                title="<?php echo htmlspecialchars($it['item_name']); ?>">
                                                <?php echo htmlspecialchars($it['item_name']); ?>
                                                <?php if (!empty($it['component_rating'])): ?>
                                                    <span class="text-amber-400 font-bold ml-0.5"><?php echo htmlspecialchars($it['component_rating']); ?></span>
                                                <?php endif; ?>
                                                <span class="opacity-60 font-mono">(x<?php echo (int)$it['quantity']; ?>)</span>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($data['items']) > 8): ?>
                                            <span class="text-[10px] text-slate-500 italic px-1 self-center">+<?php echo count($data['items']) - 8; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer: Micro text -->
                            <div class="border-t border-slate-700/60 pt-2 mt-3 flex justify-between items-center text-[10px] text-slate-500">
                                <span>Lab-IMS Inventory System</span>
                                <span class="font-mono">loc=<?php echo htmlspecialchars($loc_name); ?></span>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- INDIVIDUAL COMPONENT / HARDWARE LABELS -->
            <?php if (empty($items_data)): ?>
                <div class="text-center py-16 text-slate-500 no-print">
                    <i class="fa-solid fa-microchip text-5xl mb-3"></i>
                    <p class="text-lg">No items found matching criteria.</p>
                    <a href="print_labels.php" class="text-blue-400 hover:underline text-sm mt-2 inline-block">View All Drawer Labels</a>
                </div>
            <?php else: ?>
                <div id="labels-container" class="label-grid grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($items_data as $it): ?>
                        <div class="print-label bg-slate-800 border-2 border-slate-600 rounded-xl p-5 shadow-lg flex flex-col justify-between transition relative overflow-hidden">
                            
                            <!-- Header Row -->
                            <div class="flex justify-between items-start mb-2 border-b border-slate-700 pb-2">
                                <div>
                                    <span class="text-[10px] uppercase font-bold tracking-widest text-slate-400 block">
                                        <?php echo htmlspecialchars($it['category']); ?> &bull; <?php echo htmlspecialchars($it['item_type']); ?>
                                    </span>
                                    <h3 class="text-xl font-bold text-white tracking-wide truncate max-w-xs mt-0.5" title="<?php echo htmlspecialchars($it['item_name']); ?>">
                                        <?php echo htmlspecialchars($it['item_name']); ?>
                                    </h3>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-bold px-2 py-0.5 rounded bg-blue-900/80 text-blue-200 border border-blue-700/50 print-badge">
                                        Qty: <?php echo (int)$it['quantity']; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Body: QR Code + Hardware distinction -->
                            <div class="flex items-center gap-4 my-2">
                                <div class="bg-white p-2.5 rounded-lg border border-slate-300 flex-shrink-0 flex items-center justify-center">
                                    <div class="qr-target" data-qr-path="/pages/view_item.php?id=<?php echo (int)$it['id']; ?>" id="qr-item-<?php echo (int)$it['id']; ?>"></div>
                                </div>

                                <div class="space-y-1.5 flex-grow min-w-0">
                                    <!-- High-Contrast Rating / Value -->
                                    <?php if (!empty($it['component_rating'])): ?>
                                        <div>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-400/50 print-badge">
                                                <i class="fa-solid fa-bolt text-[10px] text-amber-400"></i>
                                                <?php echo htmlspecialchars($it['component_rating']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Model & Serial -->
                                    <?php if (!empty($it['model_number']) || !empty($it['serial_number'])): ?>
                                        <div class="text-xs font-mono space-y-0.5">
                                            <?php if (!empty($it['model_number'])): ?>
                                                <div class="text-slate-300 truncate">M/N: <?php echo htmlspecialchars($it['model_number']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($it['serial_number'])): ?>
                                                <div class="text-cyan-300 font-bold truncate">S/N: <?php echo htmlspecialchars($it['serial_number']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Location & Condition -->
                                    <div class="text-xs text-slate-400 flex items-center gap-2 pt-1">
                                        <span class="flex items-center gap-1 font-semibold text-slate-200">
                                            <i class="fa-solid fa-location-dot text-slate-400"></i>
                                            <?php echo htmlspecialchars($it['location'] ?: 'Unassigned'); ?>
                                        </span>
                                        <?php if (!empty($it['condition_status'])): ?>
                                            <span class="text-[10px] px-1.5 py-0.2 rounded border border-slate-600 print-badge">
                                                <?php echo htmlspecialchars($it['condition_status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="border-t border-slate-700/60 pt-2 mt-2 flex justify-between items-center text-[10px] text-slate-500">
                                <span>Lab-IMS Item #<?php echo (int)$it['id']; ?></span>
                                <span class="font-mono">Scan for Datasheet &amp; Specs</span>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>

    <script>
        // Generate QR Codes client-side
        function generateQRCodes() {
            const baseUrlInput = document.getElementById('base-url-input');
            const baseUrl = (baseUrlInput ? baseUrlInput.value.trim() : window.location.origin).replace(/\/+$/, '');
            const targets = document.querySelectorAll('.qr-target');

            targets.forEach(el => {
                el.innerHTML = ''; // Clear previous QR
                const path = el.getAttribute('data-qr-path');
                const fullUrl = baseUrl + path;

                try {
                    new QRCode(el, {
                        text: fullUrl,
                        width: 88,
                        height: 88,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                } catch (e) {
                    console.error('QR generation error:', e);
                }
            });
        }

        function regenerateAllQRCodes() {
            generateQRCodes();
        }

        function toggleSummary(show) {
            document.querySelectorAll('.loc-summary-list').forEach(el => {
                el.style.display = show ? 'flex' : 'none';
            });
        }

        function updateLabelSize(size) {
            const main = document.querySelector('main');
            main.classList.remove('label-compact', 'label-standard', 'label-detailed');
            main.classList.add('label-' + size);

            const container = document.getElementById('labels-container');
            if (container) {
                if (size === 'compact') {
                    container.className = 'label-grid grid grid-cols-1 md:grid-cols-3 gap-4';
                } else if (size === 'detailed') {
                    container.className = 'label-grid grid grid-cols-1 md:grid-cols-2 gap-8';
                } else {
                    container.className = 'label-grid grid grid-cols-1 md:grid-cols-2 gap-6';
                }
            }
        }

        // Generate on page load
        document.addEventListener('DOMContentLoaded', () => {
            generateQRCodes();
        });
    </script>

</body>
</html>
