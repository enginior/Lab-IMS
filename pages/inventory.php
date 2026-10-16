<?php
require '../includes/db_conn.php';
require '../includes/header.php';

// --- Pagination Settings ---
$limit = 12; // Items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// --- Search & Filter Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$cat_filter = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$subcat_filter = isset($_GET['subcat']) ? trim($_GET['subcat']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$stock_filter = isset($_GET['stock']) ? trim($_GET['stock']) : '';
$loc_filter = isset($_GET['loc']) ? trim($_GET['loc']) : '';
$sort_by = isset($_GET['sort']) ? trim($_GET['sort']) : 'cat_asc';

// Map sort option to safe SQL ORDER BY clause
$order_by_sql = match ($sort_by) {
    'name_asc'     => 'item_name ASC, component_rating ASC',
    'name_desc'    => 'item_name DESC, component_rating ASC',
    'qty_asc'      => 'quantity ASC, item_name ASC',
    'qty_desc'     => 'quantity DESC, item_name ASC',
    'newest'       => 'created_at DESC, id DESC',
    'updated_desc' => 'last_updated DESC, id DESC',
    'loc_asc'      => 'location ASC, item_name ASC',
    default        => 'category ASC, sub_category ASC, item_name ASC, component_rating ASC'
};

// Fetch distinct sub-categories for the active category (or all) to populate the filter bar
$subcats_available = [];
if ($cat_filter !== '') {
    $sub_stmt = $conn->prepare("SELECT DISTINCT sub_category, COUNT(*) as cnt FROM inventory WHERE sub_category IS NOT NULL AND sub_category != '' AND category = ? GROUP BY sub_category ORDER BY sub_category ASC");
    $sub_stmt->bind_param("s", $cat_filter);
    $sub_stmt->execute();
    $sub_res = $sub_stmt->get_result();
    while ($srow = $sub_res->fetch_assoc()) {
        $subcats_available[] = $srow;
    }
    $sub_stmt->close();
} else {
    $sub_sql = "SELECT DISTINCT sub_category, COUNT(*) as cnt FROM inventory WHERE sub_category IS NOT NULL AND sub_category != '' GROUP BY sub_category ORDER BY sub_category ASC";
    if ($sub_res = $conn->query($sub_sql)) {
        while ($srow = $sub_res->fetch_assoc()) {
            $subcats_available[] = $srow;
        }
    }
}

// Fetch distinct storage locations to populate the Location filter dropdown
$locations_available = [];
$loc_sql = "SELECT DISTINCT location, COUNT(*) as cnt FROM inventory WHERE location IS NOT NULL AND TRIM(location) != '' GROUP BY location ORDER BY location ASC";
if ($loc_res = $conn->query($loc_sql)) {
    while ($lrow = $loc_res->fetch_assoc()) {
        $locations_available[] = $lrow;
    }
}

// 1. Build Parameterized Filter SQL (Shared by Count and Fetch queries)
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

// 2. Count Total Records (For Pagination Numbers)
$count_sql = "SELECT COUNT(*) as total FROM inventory $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types !== "") {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = (int)ceil($total_records / $limit);

// 3. Fetch Actual Data (With Limit)
$sql = "SELECT * FROM inventory $where_sql ORDER BY $order_by_sql LIMIT ? OFFSET ?";
$fetch_stmt = $conn->prepare($sql);
$fetch_types = $types . "ii";
$fetch_params = array_merge($params, [$limit, $offset]);
$fetch_stmt->bind_param($fetch_types, ...$fetch_params);
$fetch_stmt->execute();
$result = $fetch_stmt->get_result();

// Pre-count how many units share the same model_number (or item_name for Unique items) across the database
$model_counts = [];
$mc_res = $conn->query("SELECT COALESCE(NULLIF(model_number, ''), item_name) as grp_key, COUNT(*) as unit_count, SUM(quantity) as total_qty FROM inventory GROUP BY grp_key HAVING COUNT(*) > 1");
if ($mc_res) {
    while ($mrow = $mc_res->fetch_assoc()) {
        $model_counts[$mrow['grp_key']] = [
            'units'     => (int)$mrow['unit_count'],
            'total_qty' => (int)$mrow['total_qty']
        ];
    }
}

function condition_badge_class(string $cond): string {
    return match ($cond) {
        'New'       => 'bg-emerald-950/80 text-emerald-300 border-emerald-600/60',
        'Working'   => 'bg-green-950/80 text-green-300 border-green-600/60',
        'Untested'  => 'bg-amber-950/80 text-amber-300 border-amber-600/60',
        'For Parts' => 'bg-red-950/80 text-red-300 border-red-600/60',
        default     => 'bg-slate-800 text-slate-300 border-slate-600'
    };
}
?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3 sm:gap-4">

    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-white">Inventory <span class="text-base sm:text-lg font-normal text-slate-400">(<?php echo $total_records; ?> Items)</span></h1>
        <p class="text-[11px] sm:text-xs text-slate-400 mt-1">
            <span class="inline-block w-2 h-2 rounded-full bg-amber-400 mr-1"></span><span class="text-amber-300 font-medium">Ratings</span> &bull;
            <span class="inline-block w-2 h-2 rounded-full bg-cyan-400 ml-1.5 mr-1"></span><span class="text-cyan-300 font-medium">Serial #</span> &bull;
            <span class="inline-block w-2 h-2 rounded-full bg-orange-400 ml-1.5 mr-1"></span><span class="text-orange-300 font-medium">Stock Alerts</span>
        </p>
    </div>

    <div class="w-full sm:w-auto flex flex-wrap items-center justify-between sm:justify-end gap-2">
        <?php
        $export_qs = http_build_query([
            'search' => $search,
            'cat'    => $cat_filter,
            'subcat' => $subcat_filter,
            'type'   => $type_filter,
            'stock'  => $stock_filter,
            'loc'    => $loc_filter,
            'sort'   => $sort_by
        ]);
        ?>
        <a href="export_csv.php?<?php echo $export_qs; ?>" class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 text-xs sm:text-sm px-2.5 sm:px-3 py-2 rounded-lg shadow transition flex items-center gap-1.5 whitespace-nowrap" title="Export current filtered parts list to CSV">
            <i class="fa-solid fa-file-csv text-emerald-400"></i>
            <span class="inline">CSV</span>
        </a>

        <a href="print_labels.php<?php echo $loc_filter !== '' ? '?loc=' . urlencode($loc_filter) : ''; ?>" class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 text-xs sm:text-sm px-2.5 sm:px-3 py-2 rounded-lg shadow transition flex items-center gap-1.5 whitespace-nowrap" title="Generate printable QR drawer and part labels">
            <i class="fa-solid fa-qrcode text-cyan-400"></i>
            <span class="inline">QR Labels</span>
        </a>

        <a href="add_item.php" class="bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm px-3 sm:px-4 py-2 rounded-lg shadow transition flex items-center gap-1.5 whitespace-nowrap font-medium">
            <i class="fa-solid fa-plus"></i> <span class="hidden xs:inline">Add Item</span><span class="xs:hidden">Add</span>
        </a>

        <div class="flex bg-slate-800 rounded-lg p-1 border border-slate-600">
            <button id="btn-list" onclick="switchView('list')" class="px-2.5 sm:px-3 py-1 rounded text-slate-400 hover:text-white transition cursor-pointer" title="List View">
                <i class="fa-solid fa-list text-sm"></i>
            </button>
            <button id="btn-grid" onclick="switchView('grid')" class="px-2.5 sm:px-3 py-1 rounded text-slate-400 hover:text-white transition cursor-pointer" title="Grid View">
                <i class="fa-solid fa-border-all text-sm"></i>
            </button>
        </div>
    </div>
</div>

<!-- Filter & Sort Bar -->
<div class="bg-slate-800/80 border border-slate-700 rounded-xl p-3 sm:p-3.5 mb-4 shadow">
    <form id="filter-form" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-2.5" method="GET" action="inventory.php">
        <input type="hidden" name="subcat" id="hidden-subcat" value="<?php echo htmlspecialchars($subcat_filter); ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>" id="current-sort-input">

        <!-- Search Input -->
        <div class="relative lg:col-span-2">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, 4kΩ, S/N, model..."
                class="bg-slate-900 border border-slate-600 text-white text-sm rounded-lg px-3 py-2 pl-8 focus:outline-none focus:border-blue-500 w-full">
            <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-3 text-slate-400 text-xs"></i>
        </div>

        <!-- Category Filter -->
        <select name="cat" onchange="document.getElementById('hidden-subcat').value=''; this.form.submit()" class="bg-slate-900 border border-slate-600 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500">
            <option value="">All Categories</option>
            <option value="Electronic" <?php if ($cat_filter == 'Electronic') echo 'selected'; ?>>Electronics</option>
            <option value="Computer" <?php if ($cat_filter == 'Computer') echo 'selected'; ?>>Computer Parts</option>
            <option value="Tool" <?php if ($cat_filter == 'Tool') echo 'selected'; ?>>Tools</option>
            <option value="Other" <?php if ($cat_filter == 'Other') echo 'selected'; ?>>Other</option>
        </select>

        <!-- Location Filter -->
        <select name="loc" onchange="this.form.submit()" class="bg-slate-900 border border-slate-600 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500">
            <option value="">📍 All Locations</option>
            <?php foreach ($locations_available as $loc_item): ?>
                <option value="<?php echo htmlspecialchars($loc_item['location']); ?>" <?php if ($loc_filter === $loc_item['location']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($loc_item['location']); ?> (<?php echo (int)$loc_item['cnt']; ?>)
                </option>
            <?php endforeach; ?>
            <option value="__unassigned__" <?php if ($loc_filter === '__unassigned__') echo 'selected'; ?>>Unassigned</option>
        </select>

        <!-- Stock & Tracking Type Combined -->
        <div class="flex gap-2 lg:col-span-2">
            <select name="type" onchange="this.form.submit()" class="w-1/2 bg-slate-900 border border-slate-600 text-white text-sm rounded-lg px-2.5 py-2 focus:outline-none focus:border-blue-500" title="Tracking Type">
                <option value="">All Types</option>
                <option value="Bulk" <?php if ($type_filter == 'Bulk') echo 'selected'; ?>>Bulk Parts</option>
                <option value="Unique" <?php if ($type_filter == 'Unique') echo 'selected'; ?>>Serialized Units</option>
            </select>

            <select name="stock" onchange="this.form.submit()" class="w-1/2 bg-slate-900 border border-slate-600 text-white text-sm rounded-lg px-2.5 py-2 focus:outline-none focus:border-orange-400" title="Stock Status">
                <option value="">All Stock</option>
                <option value="alert" <?php if ($stock_filter == 'alert') echo 'selected'; ?>>⚠️ Restock</option>
                <option value="low" <?php if ($stock_filter == 'low') echo 'selected'; ?>>🟠 Low Stock</option>
                <option value="out" <?php if ($stock_filter == 'out') echo 'selected'; ?>>🔴 Out (0)</option>
            </select>
        </div>

        <?php
        $sort_options = [
            'cat_asc'      => 'Category & Name',
            'name_asc'     => 'Name (A–Z)',
            'qty_asc'      => 'Qty (Low → High)',
            'qty_desc'     => 'Qty (High → Low)',
            'loc_asc'      => 'Location',
            'newest'       => 'Recently Added',
            'updated_desc' => 'Last Updated'
        ];
        ?>

        <!-- Mobile Sort Dropdown (sm:hidden) -->
        <div class="sm:hidden flex items-center justify-between gap-2 pt-2 border-t border-slate-700/70 text-xs">
            <label for="mobile-sort-select" class="font-semibold uppercase tracking-wider text-slate-400 flex items-center gap-1 flex-shrink-0">
                <i class="fa-solid fa-arrow-up-wide-short"></i> Sort:
            </label>
            <select id="mobile-sort-select" onchange="document.getElementById('current-sort-input').value=this.value; document.getElementById('filter-form').submit();"
                class="bg-slate-900 border border-slate-600 text-white text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-blue-500 flex-grow">
                <?php foreach ($sort_options as $s_key => $s_label): ?>
                    <option value="<?php echo $s_key; ?>" <?php if ($sort_by === $s_key) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($s_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($cat_filter !== '' || $subcat_filter !== '' || $type_filter !== '' || $stock_filter !== '' || $loc_filter !== '' || $search !== '' || $sort_by !== 'cat_asc'): ?>
                <a href="inventory.php" class="text-xs text-red-400 hover:text-red-300 flex items-center gap-1 font-medium flex-shrink-0">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            <?php endif; ?>
        </div>

        <!-- Tablet & Desktop Sort Buttons (hidden sm:flex) -->
        <div class="hidden sm:flex sm:col-span-2 md:col-span-3 lg:col-span-6 flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-700/70 text-xs">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="font-semibold uppercase tracking-wider text-slate-400 mr-1"><i class="fa-solid fa-arrow-up-wide-short mr-1"></i>Sort By:</span>
                <?php foreach ($sort_options as $s_key => $s_label):
                    $active_sort = ($sort_by === $s_key);
                ?>
                    <button type="button" onclick="document.getElementById('current-sort-input').value='<?php echo $s_key; ?>'; this.form.submit();"
                        class="px-2.5 py-1 rounded border transition cursor-pointer <?php echo $active_sort ? 'bg-blue-600/30 border-blue-500 text-blue-200 font-semibold' : 'bg-slate-900 border-slate-700 text-slate-400 hover:text-white'; ?>">
                        <?php echo htmlspecialchars($s_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if ($cat_filter !== '' || $subcat_filter !== '' || $type_filter !== '' || $stock_filter !== '' || $loc_filter !== '' || $search !== '' || $sort_by !== 'cat_asc'): ?>
                <a href="inventory.php" class="text-xs text-red-400 hover:text-red-300 flex items-center gap-1 font-medium">
                    <i class="fa-solid fa-rotate-left"></i> Reset All
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Sub-Category Quick Filter Pills (Smooth scroll on mobile, wrap on tablet/desktop) -->
<?php if (!empty($subcats_available)): ?>
    <div class="flex items-center gap-1.5 mb-5 pb-2 border-b border-slate-800 overflow-x-auto no-scrollbar sm:flex-wrap">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 mr-1 flex-shrink-0">Sub-Categories:</span>
        <a href="?cat=<?php echo urlencode($cat_filter); ?>&loc=<?php echo urlencode($loc_filter); ?>&type=<?php echo urlencode($type_filter); ?>&stock=<?php echo urlencode($stock_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&search=<?php echo urlencode($search); ?>"
           class="text-xs px-2.5 py-1 rounded-full border transition flex-shrink-0 <?php echo $subcat_filter === '' ? 'bg-blue-600 border-blue-500 text-white font-semibold' : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-white'; ?>">
            All
        </a>
        <?php foreach ($subcats_available as $sc): ?>
            <?php $is_active_sc = ($subcat_filter === $sc['sub_category']); ?>
            <a href="?cat=<?php echo urlencode($cat_filter); ?>&subcat=<?php echo urlencode($sc['sub_category']); ?>&loc=<?php echo urlencode($loc_filter); ?>&type=<?php echo urlencode($type_filter); ?>&stock=<?php echo urlencode($stock_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&search=<?php echo urlencode($search); ?>"
               class="text-xs px-2.5 py-1 rounded-full border transition flex-shrink-0 <?php echo $is_active_sc ? 'bg-blue-600 border-blue-500 text-white font-semibold' : 'bg-slate-800 border-slate-700 text-slate-300 hover:border-slate-500 hover:text-white'; ?>">
                <?php echo htmlspecialchars($sc['sub_category']); ?>
                <span class="opacity-60 ml-0.5">(<?php echo (int)$sc['cnt']; ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($total_records == 0): ?>
    <div class="text-center py-12 text-slate-500">
        <i class="fa-solid fa-box-open text-4xl mb-3"></i>
        <p>No items found matching your criteria.</p>
    </div>
<?php else: ?>

    <?php
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $fetch_stmt->close();
    ?>
    <!-- List View -->
    <div id="view-list" class="hidden">
        <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg">

            <!-- Mobile & Tablet Portrait Card-List (< lg screens) -->
            <div class="lg:hidden divide-y divide-slate-700/80">
                <?php foreach ($items as $row): ?>
                    <?php
                    $item_type = $row['item_type'] ?? 'Bulk';
                    $grp_key = !empty($row['model_number']) ? $row['model_number'] : $row['item_name'];
                    $multi_unit_info = $model_counts[$grp_key] ?? null;
                    $stock = get_stock_status((int)$row['quantity'], (int)($row['min_quantity'] ?? 0));
                    ?>
                    <div class="p-3.5 sm:p-4 hover:bg-slate-700/20 transition <?php echo $stock['code'] === 'out' ? 'bg-red-950/15' : ($stock['code'] === 'low' ? 'bg-orange-950/10' : ''); ?>">
                        <!-- Top Row: Thumbnail + Title + Badges -->
                        <div class="flex items-start gap-3">
                            <div class="h-12 w-12 bg-slate-900 rounded-lg flex items-center justify-center border border-slate-700 overflow-hidden flex-shrink-0 cursor-pointer" onclick="window.location='view_item.php?id=<?php echo (int)$row['id']; ?>'">
                                <?php if (!empty($row['image_path'])): ?>
                                    <img src="../assets/uploads/<?php echo htmlspecialchars($row['image_path']); ?>" class="h-full w-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid <?php echo $item_type === 'Unique' ? 'fa-barcode text-cyan-400' : 'fa-microchip text-amber-400'; ?> text-lg"></i>
                                <?php endif; ?>
                            </div>

                            <div class="flex-grow min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <a href="view_item.php?id=<?php echo (int)$row['id']; ?>" class="font-bold text-white text-sm sm:text-base hover:text-blue-400 transition truncate">
                                        <?php echo htmlspecialchars($row['item_name']); ?>
                                    </a>
                                    <?php if ($item_type === 'Bulk'): ?>
                                        <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-700 text-slate-300 font-semibold flex-shrink-0" title="Bulk Part">Bulk</span>
                                    <?php else: ?>
                                        <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-cyan-950 text-cyan-300 border border-cyan-700/50 font-semibold flex-shrink-0" title="Serialized Unit">Unit</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Distinction Chips (Ratings, Serial #, Model #) -->
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <?php if (!empty($row['component_rating'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/20 text-amber-300 border border-amber-400/50">
                                            <i class="fa-solid fa-bolt text-[9px] text-amber-400"></i>
                                            <?php echo htmlspecialchars($row['component_rating']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($row['serial_number'])): ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono bg-cyan-950/80 text-cyan-300 border border-cyan-500/50">
                                            S/N: <?php echo htmlspecialchars($row['serial_number']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($row['model_number'])): ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono bg-slate-900 text-slate-300 border border-slate-700">
                                            M/N: <?php echo htmlspecialchars($row['model_number']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($item_type === 'Unique' && !empty($row['condition_status'])): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium border <?php echo condition_badge_class($row['condition_status']); ?>">
                                            <?php echo htmlspecialchars($row['condition_status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Hierarchy & Location -->
                                <div class="flex flex-wrap items-center gap-2 mt-1.5 text-xs text-slate-400">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium <?php echo ($row['category'] == 'Electronic') ? 'bg-blue-900/80 text-blue-200 border border-blue-700/50' : (($row['category'] == 'Computer') ? 'bg-purple-900/80 text-purple-200 border border-purple-700/50' : (($row['category'] == 'Tool') ? 'bg-teal-900/80 text-teal-200 border border-teal-700/50' : 'bg-slate-700 text-slate-200')); ?>">
                                        <?php echo htmlspecialchars($row['category']); ?>
                                    </span>
                                    <?php if (!empty($row['sub_category'])): ?>
                                        <span class="text-slate-400 text-xs">
                                            <i class="fa-solid fa-folder-tree text-[9px] mr-1 opacity-60"></i><?php echo htmlspecialchars($row['sub_category']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <a href="?loc=<?php echo urlencode(!empty($row['location']) ? $row['location'] : '__unassigned__'); ?>" class="text-slate-400 hover:text-blue-400 transition text-xs flex items-center gap-1">
                                        <i class="fa-solid fa-location-dot text-slate-500 text-[10px]"></i><?php echo htmlspecialchars($row['location'] ?: 'Unassigned'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Row: Stock Alert + Quantity Stepper + Quick Action Buttons -->
                        <div class="flex items-center justify-between gap-2 mt-3 pt-2.5 border-t border-slate-700/60">
                            <!-- Left: Stock Badge & Variants Link -->
                            <div class="flex items-center gap-1.5">
                                <span class="stock-badge-<?php echo (int)$row['id']; ?> inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border <?php echo $stock['class']; ?> <?php echo $stock['code'] === 'ok' ? 'hidden' : ''; ?>">
                                    <i class="stock-icon-<?php echo (int)$row['id']; ?> fa-solid <?php echo $stock['icon']; ?>"></i>
                                    <span class="stock-label-<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars($stock['label']); ?></span>
                                </span>
                                <?php if ($multi_unit_info): ?>
                                    <a href="?search=<?php echo urlencode($grp_key); ?>" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] bg-blue-950/70 text-blue-300 border border-blue-600/40">
                                        <i class="fa-solid fa-clone text-[9px]"></i> <?php echo $multi_unit_info['units']; ?> units
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Right: Thumb-friendly Quantity Stepper & Quick Actions -->
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="inline-flex items-center gap-1 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 shadow-inner">
                                    <button type="button" onclick="adjustQty(<?php echo (int)$row['id']; ?>, -1, event)"
                                        class="w-7 h-7 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-slate-300 hover:text-white text-xs transition cursor-pointer" title="Decrease quantity">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <span class="qty-display-<?php echo (int)$row['id']; ?> font-bold text-white min-w-[1.75rem] text-center text-sm">
                                        <?php echo (int)$row['quantity']; ?>
                                    </span>
                                    <button type="button" onclick="adjustQty(<?php echo (int)$row['id']; ?>, 1, event)"
                                        class="w-7 h-7 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-slate-300 hover:text-white text-xs transition cursor-pointer" title="Increase quantity">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>

                                <div class="inline-flex items-center gap-2 pl-1 border-l border-slate-700">
                                    <?php if (!empty($row['datasheet_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['datasheet_url']); ?>" target="_blank" rel="noopener noreferrer" class="p-1.5 text-red-400 hover:text-red-300 transition text-sm" title="Datasheet">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="add_item.php?clone_id=<?php echo (int)$row['id']; ?>" class="p-1.5 text-slate-400 hover:text-cyan-400 transition text-sm" title="Clone">
                                        <i class="fa-solid fa-copy"></i>
                                    </a>
                                    <a href="edit_item.php?id=<?php echo (int)$row['id']; ?>" class="p-1.5 text-blue-400 hover:text-blue-300 text-sm" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Desktop Table View (hidden lg:block) -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-left text-slate-300">
                    <thead class="bg-slate-700/50 text-slate-100 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-5 py-4">Item &amp; Type</th>
                            <th class="px-5 py-4">Value / Rating / Serial Distinction</th>
                            <th class="px-5 py-4">Category / Sub-Category</th>
                            <th class="px-5 py-4">Location</th>
                            <th class="px-5 py-4 text-center">Qty &amp; Stock Status</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach ($items as $row): ?>
                            <?php
                            $item_type = $row['item_type'] ?? 'Bulk';
                            $grp_key = !empty($row['model_number']) ? $row['model_number'] : $row['item_name'];
                            $multi_unit_info = $model_counts[$grp_key] ?? null;
                            $stock = get_stock_status((int)$row['quantity'], (int)($row['min_quantity'] ?? 0));
                            ?>
                            <tr class="hover:bg-slate-700/30 transition group <?php echo $stock['code'] === 'out' ? 'bg-red-950/15' : ($stock['code'] === 'low' ? 'bg-orange-950/10' : ''); ?>">

                                <!-- Item Name & Type Indicator -->
                                <td class="px-5 py-4 font-medium text-white cursor-pointer" onclick="window.location='view_item.php?id=<?php echo (int)$row['id']; ?>'">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 bg-slate-900 rounded-lg flex items-center justify-center border border-slate-700 overflow-hidden flex-shrink-0">
                                            <?php if (!empty($row['image_path'])): ?>
                                                <img src="../assets/uploads/<?php echo htmlspecialchars($row['image_path']); ?>" class="h-full w-full object-cover">
                                            <?php else: ?>
                                                <i class="fa-solid <?php echo $item_type === 'Unique' ? 'fa-barcode text-cyan-400' : 'fa-microchip text-amber-400'; ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <a href="view_item.php?id=<?php echo (int)$row['id']; ?>" class="group-hover:text-blue-400 transition font-bold">
                                                    <?php echo htmlspecialchars($row['item_name']); ?>
                                                </a>
                                                <?php if ($item_type === 'Bulk'): ?>
                                                    <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-700 text-slate-300 font-semibold" title="Small / Bulk Component">Bulk</span>
                                                <?php else: ?>
                                                    <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-cyan-950 text-cyan-300 border border-cyan-700/50 font-semibold" title="Serialized / Unique Hardware">Unit</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($row['specifications'])): ?>
                                                <span class="text-xs text-slate-400 block max-w-xs truncate mt-0.5"><?php echo htmlspecialchars($row['specifications']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- High-Contrast Value/Rating & Serial Number Distinction Column -->
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <?php if (!empty($row['component_rating'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-400/50 shadow-sm" title="Component Value / Rating / Package">
                                                <i class="fa-solid fa-bolt text-[10px] text-amber-400"></i>
                                                <?php echo htmlspecialchars($row['component_rating']); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($row['model_number'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono bg-slate-900 text-slate-200 border border-slate-600" title="Model / Part Number">
                                                <span class="text-slate-500">M/N:</span> <?php echo htmlspecialchars($row['model_number']); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($row['serial_number'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono bg-cyan-950/80 text-cyan-300 border border-cyan-500/50" title="Unique Serial Number">
                                                <i class="fa-solid fa-fingerprint text-[10px]"></i> S/N: <?php echo htmlspecialchars($row['serial_number']); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($item_type === 'Unique' && !empty($row['condition_status'])): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium border <?php echo condition_badge_class($row['condition_status']); ?>">
                                                <?php echo htmlspecialchars($row['condition_status']); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($multi_unit_info): ?>
                                            <a href="?search=<?php echo urlencode($grp_key); ?>" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] bg-blue-950/70 text-blue-300 border border-blue-600/40 hover:bg-blue-900 transition" title="Click to compare all units of this model/name">
                                                <i class="fa-solid fa-clone text-[10px]"></i> <?php echo $multi_unit_info['units']; ?> variants/units
                                            </a>
                                        <?php endif; ?>

                                        <?php if (empty($row['component_rating']) && empty($row['model_number']) && empty($row['serial_number'])): ?>
                                            <span class="text-xs text-slate-500 italic">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Category & Sub-Category Hierarchy -->
                                <td class="px-5 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                        <?php echo ($row['category'] == 'Electronic') ? 'bg-blue-900/80 text-blue-200 border border-blue-700/50' : (($row['category'] == 'Computer') ? 'bg-purple-900/80 text-purple-200 border border-purple-700/50' : (($row['category'] == 'Tool') ? 'bg-teal-900/80 text-teal-200 border border-teal-700/50' : 'bg-slate-700 text-slate-200')); ?>">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                        <?php if (!empty($row['sub_category'])): ?>
                                            <a href="?cat=<?php echo urlencode($row['category']); ?>&subcat=<?php echo urlencode($row['sub_category']); ?>" class="text-xs text-slate-400 hover:text-blue-400 transition">
                                                <i class="fa-solid fa-folder-tree text-[10px] mr-1 opacity-60"></i><?php echo htmlspecialchars($row['sub_category']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Location -->
                                <td class="px-5 py-4 text-sm">
                                    <a href="?loc=<?php echo urlencode(!empty($row['location']) ? $row['location'] : '__unassigned__'); ?>" class="hover:text-blue-400 transition flex items-center gap-1" title="Filter by this storage location">
                                        <i class="fa-solid fa-location-dot text-slate-500 text-xs"></i>
                                        <?php echo htmlspecialchars($row['location'] ?: 'Unassigned'); ?>
                                    </a>
                                </td>

                                <!-- Quantity Controls & Stock Status Badge -->
                                <td class="px-5 py-4 text-center">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <div class="inline-flex items-center gap-2 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1">
                                            <button type="button" onclick="adjustQty(<?php echo (int)$row['id']; ?>, -1, event)"
                                                class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs transition cursor-pointer" title="Decrease quantity">
                                                <i class="fa-solid fa-minus"></i>
                                            </button>
                                            <span class="qty-display-<?php echo (int)$row['id']; ?> font-bold text-white min-w-[1.5rem] text-center">
                                                <?php echo (int)$row['quantity']; ?>
                                            </span>
                                            <button type="button" onclick="adjustQty(<?php echo (int)$row['id']; ?>, 1, event)"
                                                class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs transition cursor-pointer" title="Increase quantity">
                                                <i class="fa-solid fa-plus"></i>
                                            </button>
                                        </div>

                                        <span class="stock-badge-<?php echo (int)$row['id']; ?> inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border <?php echo $stock['class']; ?> <?php echo $stock['code'] === 'ok' ? 'hidden' : ''; ?>">
                                            <i class="stock-icon-<?php echo (int)$row['id']; ?> fa-solid <?php echo $stock['icon']; ?>"></i>
                                            <span class="stock-label-<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars($stock['label']); ?></span>
                                        </span>
                                    </div>
                                </td>

                                <!-- Actions (Datasheet + Clone Variant/Unit + Edit) -->
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-3">
                                        <?php if (!empty($row['datasheet_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['datasheet_url']); ?>" target="_blank" rel="noopener noreferrer" class="text-red-400 hover:text-red-300 transition text-sm" title="Open Datasheet / Documentation">
                                                <i class="fa-solid fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="add_item.php?clone_id=<?php echo (int)$row['id']; ?>" class="text-slate-400 hover:text-cyan-400 transition text-sm" title="Add another variant (e.g. 1/4W) or another unit with a different Serial #">
                                            <i class="fa-solid fa-copy"></i>
                                        </a>
                                        <a href="edit_item.php?id=<?php echo (int)$row['id']; ?>" class="text-blue-400 hover:text-blue-300 text-sm" title="Edit Item">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Grid View -->
    <div id="view-grid" class="hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($items as $row): ?>
                <?php
                $item_type = $row['item_type'] ?? 'Bulk';
                $grp_key = !empty($row['model_number']) ? $row['model_number'] : $row['item_name'];
                $multi_unit_info = $model_counts[$grp_key] ?? null;
                $stock = get_stock_status((int)$row['quantity'], (int)($row['min_quantity'] ?? 0));
                ?>
                <div class="bg-slate-800 rounded-xl border <?php echo $stock['code'] === 'out' ? 'border-red-500/60' : ($stock['code'] === 'low' ? 'border-orange-500/60' : 'border-slate-700'); ?> shadow-lg overflow-hidden flex flex-col group hover:border-blue-500/50 transition">
                    <div class="h-48 bg-slate-900 flex items-center justify-center overflow-hidden relative cursor-pointer" onclick="window.location='view_item.php?id=<?php echo (int)$row['id']; ?>'">
                        <?php if (!empty($row['image_path'])): ?>
                            <img src="../assets/uploads/<?php echo htmlspecialchars($row['image_path']); ?>"
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        <?php else: ?>
                            <i class="fa-solid fa-microchip text-4xl text-slate-700"></i>
                        <?php endif; ?>

                        <!-- Top-Left Badges: Bulk/Condition + Stock Alert -->
                        <div class="absolute top-2 left-2 flex flex-col items-start gap-1">
                            <?php if ($item_type === 'Unique'): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border backdrop-blur-sm <?php echo condition_badge_class($row['condition_status'] ?? 'Working'); ?>">
                                    <?php echo htmlspecialchars($row['condition_status'] ?? 'Working'); ?>
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-900/85 text-slate-300 border border-slate-700 backdrop-blur-sm">
                                    Bulk Part
                                </span>
                            <?php endif; ?>

                            <span class="stock-badge-<?php echo (int)$row['id']; ?> inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border backdrop-blur-sm shadow <?php echo $stock['class']; ?> <?php echo $stock['code'] === 'ok' ? 'hidden' : ''; ?>">
                                <i class="stock-icon-<?php echo (int)$row['id']; ?> fa-solid <?php echo $stock['icon']; ?>"></i>
                                <span class="stock-label-<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars($stock['label']); ?></span>
                            </span>
                        </div>

                        <!-- Top-Right Quantity Stepper -->
                        <div class="absolute top-2 right-2 bg-slate-900/90 text-white text-xs font-bold px-1.5 py-1 rounded-lg backdrop-blur-sm border border-slate-700 flex items-center gap-1.5" onclick="event.stopPropagation()">
                            <button type="button" onclick="adjustQty(<?php echo (int)$row['id']; ?>, -1, event)"
                                class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition cursor-pointer" title="Decrease quantity">
                                <i class="fa-solid fa-minus text-[10px]"></i>
                            </button>
                            <span class="px-1">x<span class="qty-display-<?php echo (int)$row['id']; ?>"><?php echo (int)$row['quantity']; ?></span></span>
                            <button type="button" onclick="adjustQty(<?php echo (int)$row['id']; ?>, 1, event)"
                                class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition cursor-pointer" title="Increase quantity">
                                <i class="fa-solid fa-plus text-[10px]"></i>
                            </button>
                        </div>

                        <!-- Prominent Bottom-Left Overlay: Value / Rating Badge -->
                        <?php if (!empty($row['component_rating'])): ?>
                            <div class="absolute bottom-2 left-2 right-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-slate-950/90 text-amber-300 border border-amber-500/60 shadow-md backdrop-blur-sm max-w-full truncate">
                                    <i class="fa-solid fa-bolt text-amber-400 text-[10px]"></i>
                                    <?php echo htmlspecialchars($row['component_rating']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-4 flex flex-col flex-grow">
                        <div class="flex justify-between items-start mb-1.5">
                            <h3 class="font-bold text-lg text-white truncate" title="<?php echo htmlspecialchars($row['item_name']); ?>">
                                <a href="view_item.php?id=<?php echo (int)$row['id']; ?>" class="hover:text-blue-400 transition">
                                    <?php echo htmlspecialchars($row['item_name']); ?>
                                </a>
                            </h3>
                        </div>

                        <!-- Category & Sub-category badges -->
                        <div class="mb-2.5 flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] px-2 py-0.5 rounded bg-slate-700 text-slate-200 font-medium">
                                <?php echo htmlspecialchars($row['category']); ?>
                            </span>
                            <?php if (!empty($row['sub_category'])): ?>
                                <span class="text-[11px] px-2 py-0.5 rounded border border-slate-600 text-slate-400 truncate">
                                    <?php echo htmlspecialchars($row['sub_category']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Model & Serial Number Badges -->
                        <?php if (!empty($row['model_number']) || !empty($row['serial_number'])): ?>
                            <div class="mb-2.5 flex flex-wrap gap-1.5">
                                <?php if (!empty($row['model_number'])): ?>
                                    <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-900 text-slate-300 border border-slate-700 truncate">
                                        M/N: <?php echo htmlspecialchars($row['model_number']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($row['serial_number'])): ?>
                                    <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-cyan-950/80 text-cyan-300 border border-cyan-600/50 truncate" title="Serial Number">
                                        S/N: <?php echo htmlspecialchars($row['serial_number']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <p class="text-xs text-slate-400 mb-4 line-clamp-2 flex-grow">
                            <?php echo !empty($row['specifications']) ? htmlspecialchars($row['specifications']) : 'No extra notes.'; ?>
                        </p>

                        <div class="flex justify-between items-center text-xs text-slate-500 border-t border-slate-700 pt-3 mt-auto">
                            <a href="?loc=<?php echo urlencode(!empty($row['location']) ? $row['location'] : '__unassigned__'); ?>" class="flex items-center gap-1 truncate max-w-[60%] hover:text-blue-400 transition" title="Filter by this storage location">
                                <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($row['location'] ?: 'Unassigned'); ?>
                            </a>
                            <div class="flex items-center gap-3">
                                <?php if (!empty($row['datasheet_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($row['datasheet_url']); ?>" target="_blank" rel="noopener noreferrer" class="text-red-400 hover:text-red-300 transition" title="Open Datasheet / Documentation">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="add_item.php?clone_id=<?php echo (int)$row['id']; ?>" class="text-slate-400 hover:text-cyan-400 transition" title="Add another variant or serial unit">
                                    <i class="fa-solid fa-copy"></i>
                                </a>
                                <a href="edit_item.php?id=<?php echo (int)$row['id']; ?>" class="text-blue-400 hover:text-blue-300 font-medium">Edit</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
        <?php
        $base_qs = 'search=' . urlencode($search)
                 . '&cat=' . urlencode($cat_filter)
                 . '&subcat=' . urlencode($subcat_filter)
                 . '&loc=' . urlencode($loc_filter)
                 . '&type=' . urlencode($type_filter)
                 . '&stock=' . urlencode($stock_filter)
                 . '&sort=' . urlencode($sort_by);
        ?>
        <div class="mt-8 flex justify-center">
            <!-- Mobile Pagination (sm:hidden) -->
            <nav class="sm:hidden flex items-center justify-between w-full max-w-xs px-2 gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo $base_qs; ?>"
                        class="px-3.5 py-2 bg-slate-800 border border-slate-700 text-slate-200 rounded-lg text-sm font-medium hover:bg-slate-700 active:bg-slate-600 transition flex items-center gap-1">
                        <i class="fa-solid fa-chevron-left text-xs"></i> Prev
                    </a>
                <?php else: ?>
                    <span class="px-3.5 py-2 bg-slate-800/40 border border-slate-700/50 text-slate-500 rounded-lg text-sm font-medium cursor-not-allowed flex items-center gap-1">
                        <i class="fa-solid fa-chevron-left text-xs"></i> Prev
                    </span>
                <?php endif; ?>

                <span class="text-xs font-semibold text-slate-300">
                    Page <span class="text-white font-bold"><?php echo $page; ?></span> of <?php echo $total_pages; ?>
                </span>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo $base_qs; ?>"
                        class="px-3.5 py-2 bg-slate-800 border border-slate-700 text-slate-200 rounded-lg text-sm font-medium hover:bg-slate-700 active:bg-slate-600 transition flex items-center gap-1">
                        Next <i class="fa-solid fa-chevron-right text-xs"></i>
                    </a>
                <?php else: ?>
                    <span class="px-3.5 py-2 bg-slate-800/40 border border-slate-700/50 text-slate-500 rounded-lg text-sm font-medium cursor-not-allowed flex items-center gap-1">
                        Next <i class="fa-solid fa-chevron-right text-xs"></i>
                    </span>
                <?php endif; ?>
            </nav>

            <!-- Tablet & Desktop Pagination (hidden sm:flex) -->
            <nav class="hidden sm:flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo $base_qs; ?>"
                        class="px-3 py-2 bg-slate-800 border border-slate-700 text-slate-300 rounded-lg hover:bg-slate-700 transition">
                        Previous
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&<?php echo $base_qs; ?>"
                        class="px-3 py-2 border rounded-lg transition <?php echo ($i == $page) ? 'bg-blue-600 border-blue-600 text-white font-semibold' : 'bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-700'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo $base_qs; ?>"
                        class="px-3 py-2 bg-slate-800 border border-slate-700 text-slate-300 rounded-lg hover:bg-slate-700 transition">
                        Next
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>

<?php endif; ?>

<script>
    const csrfToken = <?php echo json_encode(csrf_token()); ?>;
    const savedView = localStorage.getItem('inventory_view') || 'list';
    switchView(savedView);

    function switchView(viewType) {
        const listView = document.getElementById('view-list');
        const gridView = document.getElementById('view-grid');
        const btnList = document.getElementById('btn-list');
        const btnGrid = document.getElementById('btn-grid');

        if (!listView || !gridView) {
            return;
        }

        btnList.classList.remove('bg-blue-600', 'text-white');
        btnGrid.classList.remove('bg-blue-600', 'text-white');
        listView.classList.add('hidden');
        gridView.classList.add('hidden');

        if (viewType === 'grid') {
            gridView.classList.remove('hidden');
            btnGrid.classList.add('bg-blue-600', 'text-white');
        } else {
            listView.classList.remove('hidden');
            btnList.classList.add('bg-blue-600', 'text-white');
        }
        localStorage.setItem('inventory_view', viewType);
    }

    async function adjustQty(itemId, delta, event) {
        if (event) {
            event.stopPropagation();
        }
        try {
            const response = await fetch('update_quantity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: itemId,
                    delta: delta,
                    csrf_token: csrfToken
                })
            });
            const data = await response.json();
            if (data.success) {
                document.querySelectorAll('.qty-display-' + itemId).forEach(el => {
                    el.textContent = data.quantity;
                });

                if (data.stock_status) {
                    const st = data.stock_status;
                    document.querySelectorAll('.stock-badge-' + itemId).forEach(badge => {
                        badge.className = `stock-badge-${itemId} inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border backdrop-blur-sm ${st.class} ${st.code === 'ok' ? 'hidden' : ''}`;
                    });
                    document.querySelectorAll('.stock-icon-' + itemId).forEach(icon => {
                        icon.className = `stock-icon-${itemId} fa-solid ${st.icon}`;
                    });
                    document.querySelectorAll('.stock-label-' + itemId).forEach(lbl => {
                        lbl.textContent = st.label;
                    });
                }
            } else {
                alert(data.error || 'Failed to update quantity.');
            }
        } catch (err) {
            console.error('Error updating quantity:', err);
        }
    }
</script>

<?php require '../includes/footer.php'; ?>