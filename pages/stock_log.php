<?php
require '../includes/db_conn.php';
require '../includes/header.php';

// Pagination Settings
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search & Filter Parameters
$search       = trim($_GET['search'] ?? '');
$type_filter  = trim($_GET['type'] ?? '');
$flow_filter  = trim($_GET['flow'] ?? ''); // 'in' or 'out'
$user_filter  = trim($_GET['user'] ?? '');
$sort_by      = trim($_GET['sort'] ?? 'newest');

$order_by_sql = match ($sort_by) {
    'oldest'     => 'l.created_at ASC, l.id ASC',
    'delta_desc' => 'ABS(l.delta) DESC, l.created_at DESC',
    default      => 'l.created_at DESC, l.id DESC'
};

// Fetch distinct usernames for user filter dropdown
$users_available = [];
$u_res = $conn->query("SELECT DISTINCT username FROM inventory_logs WHERE username IS NOT NULL AND username != '' ORDER BY username ASC");
if ($u_res) {
    while ($urow = $u_res->fetch_assoc()) {
        $users_available[] = $urow['username'];
    }
}

// Build Parameterized WHERE Clause
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($search !== '') {
    $where_clauses[] = "(l.item_name LIKE ? OR l.username LIKE ? OR l.notes LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($type_filter !== '' && in_array($type_filter, ['quick_adjust', 'manual_edit', 'item_created', 'item_deleted', 'restock'], true)) {
    $where_clauses[] = "l.change_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($flow_filter === 'in') {
    $where_clauses[] = "l.delta > 0";
} elseif ($flow_filter === 'out') {
    $where_clauses[] = "l.delta < 0";
}

if ($user_filter !== '') {
    $where_clauses[] = "l.username = ?";
    $params[] = $user_filter;
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// 1. Get Summary Metrics (Total Events, Total Units Added, Total Units Deducted)
$metrics_sql = "SELECT 
    COUNT(*) as total_events,
    COALESCE(SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END), 0) as units_in,
    COALESCE(SUM(CASE WHEN delta < 0 THEN ABS(delta) ELSE 0 END), 0) as units_out
FROM inventory_logs l $where_sql";

$m_stmt = $conn->prepare($metrics_sql);
if ($types !== "") {
    $m_stmt->bind_param($types, ...$params);
}
$m_stmt->execute();
$metrics_row = $m_stmt->get_result()->fetch_assoc();
$total_events = (int)($metrics_row['total_events'] ?? 0);
$total_in     = (int)($metrics_row['units_in'] ?? 0);
$total_out    = (int)($metrics_row['units_out'] ?? 0);
$m_stmt->close();

$total_pages = (int)ceil($total_events / $limit);

// 2. Fetch Actual Logs (Joining inventory to check if item still exists and get its image)
$fetch_sql = "SELECT 
    l.*,
    i.id as current_item_id,
    i.category as item_category,
    i.image_path as item_image
FROM inventory_logs l
LEFT JOIN inventory i ON l.item_id = i.id
$where_sql
ORDER BY $order_by_sql
LIMIT ? OFFSET ?";

$fetch_stmt = $conn->prepare($fetch_sql);
$fetch_types = $types . "ii";
$fetch_params = array_merge($params, [$limit, $offset]);
$fetch_stmt->bind_param($fetch_types, ...$fetch_params);
$fetch_stmt->execute();
$logs_res = $fetch_stmt->get_result();
$logs = [];
while ($lrow = $logs_res->fetch_assoc()) {
    $logs[] = $lrow;
}
$fetch_stmt->close();

function format_change_badge(string $type, int $delta): array {
    return match ($type) {
        'item_created' => [
            'label' => 'New Item',
            'icon'  => 'fa-plus',
            'class' => 'bg-blue-950/80 text-blue-300 border-blue-600/60'
        ],
        'restock' => [
            'label' => 'Restock',
            'icon'  => 'fa-boxes-packing',
            'class' => 'bg-emerald-950/80 text-emerald-300 border-emerald-600/60'
        ],
        'quick_adjust' => [
            'label' => $delta > 0 ? 'Quick +' : 'Quick -',
            'icon'  => $delta > 0 ? 'fa-arrow-up' : 'fa-arrow-down',
            'class' => $delta > 0 ? 'bg-green-950/80 text-green-300 border-green-600/60' : 'bg-orange-950/80 text-orange-300 border-orange-600/60'
        ],
        'manual_edit' => [
            'label' => 'Form Edit',
            'icon'  => 'fa-pen-to-square',
            'class' => 'bg-purple-950/80 text-purple-300 border-purple-600/60'
        ],
        'item_deleted' => [
            'label' => 'Deleted',
            'icon'  => 'fa-trash',
            'class' => 'bg-red-950/80 text-red-300 border-red-600/60'
        ],
        default => [
            'label' => 'Adjusted',
            'icon'  => 'fa-clock-rotate-left',
            'class' => 'bg-slate-800 text-slate-300 border-slate-600'
        ]
    };
}
?>

<div class="max-w-6xl mx-auto space-y-6">

    <!-- Header & Metric Cards -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2.5">
                <i class="fa-solid fa-clock-rotate-left text-blue-500"></i> Stock Movement &amp; Audit Log
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Complete traceability ledger tracking every component receipt, consumption, and adjustment
            </p>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto">
            <a href="inventory.php" class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 text-xs sm:text-sm px-3 py-2 rounded-lg shadow transition flex items-center gap-1.5">
                <i class="fa-solid fa-boxes-stacked text-blue-400"></i>
                <span>Inventory Catalog</span>
            </a>
        </div>
    </div>

    <!-- Quick Stat Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 flex items-center gap-4 shadow">
            <div class="h-12 w-12 bg-blue-500/10 rounded-xl border border-blue-500/30 flex items-center justify-center text-blue-400 text-xl flex-shrink-0">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Total Logged Events</span>
                <span class="text-2xl font-bold text-white"><?php echo number_format($total_events); ?></span>
            </div>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 flex items-center gap-4 shadow">
            <div class="h-12 w-12 bg-emerald-500/10 rounded-xl border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xl flex-shrink-0">
                <i class="fa-solid fa-arrow-trend-up"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Total Units In (+)</span>
                <span class="text-2xl font-bold text-emerald-400">+<?php echo number_format($total_in); ?></span>
            </div>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 flex items-center gap-4 shadow">
            <div class="h-12 w-12 bg-orange-500/10 rounded-xl border border-orange-500/30 flex items-center justify-center text-orange-400 text-xl flex-shrink-0">
                <i class="fa-solid fa-arrow-trend-down"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Total Units Out (-)</span>
                <span class="text-2xl font-bold text-orange-400">-<?php echo number_format($total_out); ?></span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-slate-800/90 border border-slate-700 rounded-xl p-3.5 shadow">
        <form id="audit-filter-form" method="GET" action="stock_log.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
            <!-- Search Input -->
            <div class="relative sm:col-span-2">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search component name, user, notes..."
                    class="bg-slate-900 border border-slate-600 text-white text-xs sm:text-sm rounded-lg px-3 py-2 pl-8 focus:outline-none focus:border-blue-500 w-full">
                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 sm:top-3 text-slate-400 text-xs"></i>
            </div>

            <!-- Flow Filter (In vs Out) -->
            <select name="flow" onchange="this.form.submit()" class="bg-slate-900 border border-slate-600 text-white text-xs sm:text-sm rounded-lg px-2.5 py-2 focus:outline-none focus:border-blue-500">
                <option value="">All Flow Movements</option>
                <option value="in" <?php if ($flow_filter === 'in') echo 'selected'; ?>>🟢 Units In (+)</option>
                <option value="out" <?php if ($flow_filter === 'out') echo 'selected'; ?>>🔴 Units Out (-)</option>
            </select>

            <!-- Change Type Filter -->
            <select name="type" onchange="this.form.submit()" class="bg-slate-900 border border-slate-600 text-white text-xs sm:text-sm rounded-lg px-2.5 py-2 focus:outline-none focus:border-blue-500">
                <option value="">All Action Types</option>
                <option value="quick_adjust" <?php if ($type_filter === 'quick_adjust') echo 'selected'; ?>>Quick Stepper (+/-)</option>
                <option value="restock" <?php if ($type_filter === 'restock') echo 'selected'; ?>>Batch Restock</option>
                <option value="item_created" <?php if ($type_filter === 'item_created') echo 'selected'; ?>>New Item Creation</option>
                <option value="manual_edit" <?php if ($type_filter === 'manual_edit') echo 'selected'; ?>>Edit Form Adjustment</option>
                <option value="item_deleted" <?php if ($type_filter === 'item_deleted') echo 'selected'; ?>>Item Deletion</option>
            </select>

            <!-- User Filter -->
            <select name="user" onchange="this.form.submit()" class="bg-slate-900 border border-slate-600 text-white text-xs sm:text-sm rounded-lg px-2.5 py-2 focus:outline-none focus:border-blue-500">
                <option value="">All Lab Users</option>
                <?php foreach ($users_available as $u_item): ?>
                    <option value="<?php echo htmlspecialchars($u_item); ?>" <?php if ($user_filter === $u_item) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($u_item); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Sort By -->
            <select name="sort" onchange="this.form.submit()" class="bg-slate-900 border border-slate-600 text-white text-xs sm:text-sm rounded-lg px-2.5 py-2 focus:outline-none focus:border-blue-500">
                <option value="newest" <?php if ($sort_by === 'newest') echo 'selected'; ?>>Newest First</option>
                <option value="oldest" <?php if ($sort_by === 'oldest') echo 'selected'; ?>>Oldest First</option>
                <option value="delta_desc" <?php if ($sort_by === 'delta_desc') echo 'selected'; ?>>Largest Quantity Change</option>
            </select>
        </form>

        <?php if ($search !== '' || $type_filter !== '' || $flow_filter !== '' || $user_filter !== '' || $sort_by !== 'newest'): ?>
            <div class="mt-2.5 pt-2 border-t border-slate-700/80 flex justify-between items-center text-xs">
                <span class="text-slate-400">Filtered view active</span>
                <a href="stock_log.php" class="text-red-400 hover:text-red-300 font-medium flex items-center gap-1">
                    <i class="fa-solid fa-rotate-left"></i> Reset Filters
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Audit Log Feed & Ledger Table -->
    <div class="bg-slate-800 rounded-xl border border-slate-700 shadow-lg overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-700 flex justify-between items-center">
            <h3 class="text-white font-bold text-base sm:text-lg flex items-center gap-2">
                <i class="fa-solid fa-timeline text-blue-400"></i> Activity Feed
            </h3>
            <span class="text-xs text-slate-400 font-mono">
                Showing <?php echo count($logs); ?> of <?php echo $total_events; ?> entries
            </span>
        </div>

        <?php if (empty($logs)): ?>
            <div class="p-12 text-center text-slate-400">
                <i class="fa-solid fa-clock-rotate-left text-4xl mb-3 text-slate-600"></i>
                <p class="text-base font-semibold text-white">No stock movement events found.</p>
                <p class="text-xs text-slate-500 mt-1">Adjust item quantities in the catalog or reset filters to see history.</p>
            </div>
        <?php else: ?>

            <!-- Mobile Card View (< md screens) -->
            <div class="md:hidden divide-y divide-slate-700/80">
                <?php foreach ($logs as $log): ?>
                    <?php
                    $delta = (int)$log['delta'];
                    $badge = format_change_badge($log['change_type'], $delta);
                    $exists = !empty($log['current_item_id']);
                    ?>
                    <div class="p-4 hover:bg-slate-700/20 transition">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <?php if ($exists): ?>
                                    <a href="view_item.php?id=<?php echo (int)$log['current_item_id']; ?>" class="font-bold text-white text-sm hover:text-blue-400 transition truncate">
                                        <?php echo htmlspecialchars($log['item_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="font-bold text-slate-300 text-sm truncate" title="Deleted item">
                                        <?php echo htmlspecialchars($log['item_name']); ?> <span class="text-[10px] text-red-400 font-normal">(deleted)</span>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Delta Pill -->
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-mono font-bold flex-shrink-0 <?php echo $delta > 0 ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-600/60' : ($delta < 0 ? 'bg-orange-950/80 text-orange-300 border border-orange-600/60' : 'bg-slate-900 text-slate-400 border border-slate-700'); ?>">
                                <?php echo $delta > 0 ? "+{$delta}" : "{$delta}"; ?>
                            </span>
                        </div>

                        <!-- Meta details: Action type + Balance + User + Date -->
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400 mb-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold border <?php echo $badge['class']; ?>">
                                <i class="fa-solid <?php echo $badge['icon']; ?> text-[9px]"></i> <?php echo htmlspecialchars($badge['label']); ?>
                            </span>
                            <span class="font-mono text-slate-300 text-[11px]">
                                <?php echo (int)$log['old_quantity']; ?> &rarr; <strong class="text-white"><?php echo (int)$log['new_quantity']; ?></strong> units
                            </span>
                            <span class="text-slate-400 text-[11px]">
                                <i class="fa-solid fa-user-circle mr-0.5 opacity-60"></i><?php echo htmlspecialchars($log['username']); ?>
                            </span>
                        </div>

                        <?php if (!empty($log['notes'])): ?>
                            <div class="text-xs text-slate-300 bg-slate-900/60 border border-slate-700/60 rounded px-2.5 py-1.5 font-sans">
                                <i class="fa-solid fa-comment-dots text-[10px] text-slate-500 mr-1"></i><?php echo htmlspecialchars($log['notes']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="text-[10px] text-slate-500 mt-2 font-mono flex items-center justify-between">
                            <span><?php echo date("M d, Y &bull; H:i:s", strtotime($log['created_at'])); ?></span>
                            <?php if ($exists): ?>
                                <a href="view_item.php?id=<?php echo (int)$log['current_item_id']; ?>" class="text-blue-400 hover:text-blue-300">View Part &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Desktop & Tablet Table (hidden md:block) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-700/50 text-slate-200 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-5 py-3.5">Timestamp</th>
                            <th class="px-5 py-3.5">Component</th>
                            <th class="px-5 py-3.5 text-center">Movement</th>
                            <th class="px-5 py-3.5 text-center">Balance</th>
                            <th class="px-5 py-3.5">Action &amp; Reason</th>
                            <th class="px-5 py-3.5 text-right">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/70 font-sans">
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $delta = (int)$log['delta'];
                            $badge = format_change_badge($log['change_type'], $delta);
                            $exists = !empty($log['current_item_id']);
                            ?>
                            <tr class="hover:bg-slate-700/25 transition">
                                <!-- Date & Time -->
                                <td class="px-5 py-3.5 whitespace-nowrap text-xs text-slate-400 font-mono">
                                    <div class="text-slate-200"><?php echo date("M d, Y", strtotime($log['created_at'])); ?></div>
                                    <div class="text-[11px] text-slate-500"><?php echo date("H:i:s", strtotime($log['created_at'])); ?></div>
                                </td>

                                <!-- Component Name & Link -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-8 w-8 bg-slate-900 rounded flex items-center justify-center border border-slate-700 overflow-hidden flex-shrink-0">
                                            <?php if (!empty($log['item_image'])): ?>
                                                <img src="../assets/uploads/<?php echo htmlspecialchars($log['item_image']); ?>" class="h-full w-full object-cover">
                                            <?php else: ?>
                                                <i class="fa-solid fa-microchip text-slate-500 text-xs"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0">
                                            <?php if ($exists): ?>
                                                <a href="view_item.php?id=<?php echo (int)$log['current_item_id']; ?>" class="font-bold text-white hover:text-blue-400 transition truncate block max-w-xs">
                                                    <?php echo htmlspecialchars($log['item_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="font-bold text-slate-300 truncate block max-w-xs" title="Item deleted from catalog">
                                                    <?php echo htmlspecialchars($log['item_name']); ?>
                                                    <span class="text-[10px] text-red-400 font-normal ml-1">(deleted)</span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Movement Delta -->
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-md text-xs font-mono font-bold <?php echo $delta > 0 ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-600/60' : ($delta < 0 ? 'bg-orange-950/80 text-orange-300 border border-orange-600/60' : 'bg-slate-900 text-slate-400 border border-slate-700'); ?>">
                                        <i class="fa-solid <?php echo $delta > 0 ? 'fa-arrow-up text-[10px]' : ($delta < 0 ? 'fa-arrow-down text-[10px]' : 'fa-minus text-[10px]'); ?>"></i>
                                        <?php echo $delta > 0 ? "+{$delta}" : "{$delta}"; ?>
                                    </span>
                                </td>

                                <!-- Balance (Old -> New) -->
                                <td class="px-5 py-3.5 text-center whitespace-nowrap text-xs font-mono">
                                    <span class="text-slate-400"><?php echo (int)$log['old_quantity']; ?></span>
                                    <span class="text-slate-600 mx-1.5">&rarr;</span>
                                    <span class="font-bold text-white"><?php echo (int)$log['new_quantity']; ?></span>
                                </td>

                                <!-- Action Type Badge & Reason Notes -->
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold border <?php echo $badge['class']; ?>">
                                            <i class="fa-solid <?php echo $badge['icon']; ?> text-[9px]"></i> <?php echo htmlspecialchars($badge['label']); ?>
                                        </span>
                                        <?php if (!empty($log['notes'])): ?>
                                            <span class="text-xs text-slate-300 truncate max-w-sm" title="<?php echo htmlspecialchars($log['notes']); ?>">
                                                <?php echo htmlspecialchars($log['notes']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- User -->
                                <td class="px-5 py-3.5 text-right whitespace-nowrap text-xs">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-900 text-slate-300 border border-slate-700 font-medium">
                                        <i class="fa-solid fa-user-circle text-blue-400 text-xs"></i>
                                        <?php echo htmlspecialchars($log['username']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
        <?php
        $base_qs = 'search=' . urlencode($search)
                 . '&flow=' . urlencode($flow_filter)
                 . '&type=' . urlencode($type_filter)
                 . '&user=' . urlencode($user_filter)
                 . '&sort=' . urlencode($sort_by);
        ?>
        <div class="mt-8 flex justify-center">
            <!-- Mobile Navigation (sm:hidden) -->
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

</div>

<?php require '../includes/footer.php'; ?>
