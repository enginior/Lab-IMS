<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require '../includes/db_conn.php';

// Extract Filter & Search Parameters (matching inventory.php)
$search         = trim($_GET['search'] ?? '');
$cat_filter     = trim($_GET['cat'] ?? '');
$subcat_filter  = trim($_GET['subcat'] ?? '');
$type_filter    = trim($_GET['type'] ?? '');
$stock_filter   = trim($_GET['stock'] ?? '');
$loc_filter     = trim($_GET['loc'] ?? '');
$sort_by        = trim($_GET['sort'] ?? 'cat_asc');

$order_by_sql = match ($sort_by) {
    'name_asc'     => 'item_name ASC',
    'qty_asc'      => 'quantity ASC, item_name ASC',
    'qty_desc'     => 'quantity DESC, item_name ASC',
    'loc_asc'      => 'COALESCE(NULLIF(location, \'\'), \'zzz\') ASC, item_name ASC',
    'newest'       => 'created_at DESC, id DESC',
    'updated_desc' => 'last_updated DESC',
    default        => 'category ASC, sub_category ASC, item_name ASC'
};

// Build Parameterized WHERE Clause
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($search !== '') {
    $where_clauses[] = "(item_name LIKE ? OR sub_category LIKE ? OR component_rating LIKE ? OR model_number LIKE ? OR serial_number LIKE ? OR location LIKE ? OR specifications LIKE ?)";
    $like_search = "%{$search}%";
    for ($i = 0; $i < 7; $i++) {
        $params[] = $like_search;
    }
    $types .= "sssssss";
}
if ($cat_filter !== '') {
    $where_clauses[] = "category = ?";
    $params[] = $cat_filter;
    $types .= "s";
}
if ($subcat_filter !== '') {
    $where_clauses[] = "sub_category = ?";
    $params[] = $subcat_filter;
    $types .= "s";
}
if ($type_filter === 'Bulk' || $type_filter === 'Unique') {
    $where_clauses[] = "item_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}
if ($loc_filter !== '') {
    if ($loc_filter === '__unassigned__') {
        $where_clauses[] = "(location IS NULL OR TRIM(location) = '')";
    } else {
        $where_clauses[] = "location = ?";
        $params[] = $loc_filter;
        $types .= "s";
    }
}
if ($stock_filter === 'out') {
    $where_clauses[] = "quantity <= 0";
} elseif ($stock_filter === 'low') {
    $where_clauses[] = "(quantity > 0 AND min_quantity > 0 AND quantity <= min_quantity)";
} elseif ($stock_filter === 'alert') {
    $where_clauses[] = "(quantity <= 0 OR (min_quantity > 0 AND quantity <= min_quantity))";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);
$sql = "SELECT * FROM inventory $where_sql ORDER BY $order_by_sql";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Generate dynamic filename
$timestamp = date("Y-m-d_His");
$filename = "lab_inventory_export_{$timestamp}.csv";

// Output Headers for CSV Download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open PHP output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header Row
fputcsv($output, [
    'ID',
    'Item Name',
    'Category',
    'Sub-Category',
    'Tracking Type',
    'Value / Rating / Package',
    'Model / Part #',
    'Serial Number (S/N)',
    'Condition Status',
    'Quantity',
    'Min Quantity (Threshold)',
    'Stock Status',
    'Storage Location',
    'Datasheet / Manual URL',
    'Specifications / Notes',
    'Image Filename',
    'Last Updated',
    'Date Added'
]);

// Stream data rows
while ($row = $result->fetch_assoc()) {
    $stock = get_stock_status((int)$row['quantity'], (int)($row['min_quantity'] ?? 0));

    fputcsv($output, [
        $row['id'],
        $row['item_name'],
        $row['category'],
        $row['sub_category'] ?? '',
        $row['item_type'] ?? 'Bulk',
        $row['component_rating'] ?? '',
        $row['model_number'] ?? '',
        $row['serial_number'] ?? '',
        $row['condition_status'] ?? 'Working',
        (int)$row['quantity'],
        (int)($row['min_quantity'] ?? 0),
        $stock['label'],
        $row['location'] ?: 'Unassigned',
        $row['datasheet_url'] ?? '',
        $row['specifications'] ?? '',
        $row['image_path'] ?? '',
        $row['last_updated'],
        $row['created_at']
    ]);
}

fclose($output);
$stmt->close();
exit();
