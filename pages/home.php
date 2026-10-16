<?php
require '../includes/db_conn.php';
require '../includes/header.php';

// // Quick Stats Queries
// $total_items_query = "SELECT COUNT(*) as count FROM inventory";
// $total_elec_query = "SELECT COUNT(*) as count FROM inventory WHERE category = 'Electronic'";
// $total_pc_query   = "SELECT COUNT(*) as count FROM inventory WHERE category = 'Computer'";

// // Fetch Data
// $total_items = $conn->query($total_items_query)->fetch_assoc()['count'];
// $total_elec  = $conn->query($total_elec_query)->fetch_assoc()['count'];
// $total_pc    = $conn->query($total_pc_query)->fetch_assoc()['count'];

// // Fetch Recent Items (Last 5 added)
// $recent_sql = "SELECT * FROM inventory ORDER BY id DESC LIMIT 5";
// $recent_result = $conn->query($recent_sql);
// 

// 1. Get Summary Metrics (Total Distinct Items, Total Units, Low Stock, Out of Stock)
$stats_sql = "SELECT 
    COUNT(*) as total_items,
    COALESCE(SUM(quantity), 0) as total_units,
    SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN quantity > 0 AND min_quantity > 0 AND quantity <= min_quantity THEN 1 ELSE 0 END) as low_stock
FROM inventory";
$stats_row = $conn->query($stats_sql)->fetch_assoc();
$total_assets = (int)$stats_row['total_items'];
$total_units  = (int)$stats_row['total_units'];
$out_count    = (int)$stats_row['out_of_stock'];
$low_count    = (int)$stats_row['low_stock'];

// 2. Get Data for Category Distribution & Counters
$cat_colors = [
    'Electronic' => ['hex' => '#3B82F6', 'bg' => 'bg-blue-500', 'text' => 'text-blue-400', 'border' => 'border-blue-500/40'],
    'Computer'   => ['hex' => '#A855F7', 'bg' => 'bg-purple-500', 'text' => 'text-purple-400', 'border' => 'border-purple-500/40'],
    'Tool'       => ['hex' => '#10B981', 'bg' => 'bg-emerald-500', 'text' => 'text-emerald-400', 'border' => 'border-emerald-500/40'],
    'Other'      => ['hex' => '#F59E0B', 'bg' => 'bg-amber-500', 'text' => 'text-amber-400', 'border' => 'border-amber-500/40']
];

$chart_sql = "SELECT category, COUNT(*) as count, COALESCE(SUM(quantity), 0) as total_qty FROM inventory GROUP BY category ORDER BY count DESC";
$chart_result = $conn->query($chart_sql);
$labels = [];
$counts = [];
$chart_hex_colors = [];
$categories_summary = [];

while ($row = $chart_result->fetch_assoc()) {
    $cat_name = !empty($row['category']) ? $row['category'] : "Other";
    $count = (int)$row['count'];
    $qty = (int)$row['total_qty'];
    $pct = $total_assets > 0 ? round(($count / $total_assets) * 100, 1) : 0;
    $color = $cat_colors[$cat_name] ?? ['hex' => '#64748B', 'bg' => 'bg-slate-500', 'text' => 'text-slate-400', 'border' => 'border-slate-500/40'];

    $labels[] = $cat_name;
    $counts[] = $count;
    $chart_hex_colors[] = $color['hex'];

    $categories_summary[] = [
        'category' => $cat_name,
        'count'    => $count,
        'qty'      => $qty,
        'percent'  => $pct,
        'color'    => $color
    ];
}

function category_badge_class(string $cat): string {
    return match ($cat) {
        'Electronic' => 'bg-blue-900/80 text-blue-200 border-blue-700/50',
        'Computer'   => 'bg-purple-900/80 text-purple-200 border-purple-700/50',
        'Tool'       => 'bg-emerald-900/80 text-emerald-200 border-emerald-700/50',
        'Other'      => 'bg-amber-900/80 text-amber-200 border-amber-700/50',
        default      => 'bg-slate-800 text-slate-300 border-slate-600'
    };
}

// 3. Get 5 Latest Items
$latest_sql = "SELECT * FROM inventory ORDER BY created_at DESC LIMIT 5";
$latest_result = $conn->query($latest_sql);
$latest_items = [];
if ($latest_result) {
    while ($row = $latest_result->fetch_assoc()) {
        $latest_items[] = $row;
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="max-w-6xl mx-auto space-y-6 sm:space-y-8">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 sm:gap-6">
        <div class="md:col-span-1 flex flex-col gap-6">
            <div class="bg-app-card border border-app-border rounded-xl p-5 sm:p-6 shadow-lg flex flex-col items-center justify-center flex-grow">
                <div class="p-3.5 bg-blue-500/10 rounded-full mb-3">
                    <i class="fa-solid fa-boxes-stacked text-brand text-3xl"></i>
                </div>
                <h2 class="text-app-muted text-xs sm:text-sm font-medium uppercase tracking-wider">Total Catalog Items</h2>
                <p class="text-4xl sm:text-5xl font-bold text-app-text mt-1"><?php echo $total_assets; ?></p>
                <p class="text-xs text-app-muted mt-2 text-center"><strong class="text-white"><?php echo $total_units; ?></strong> total physical units in lab</p>

                <!-- Low Stock & Out of Stock Quick Alert Pills -->
                <div class="grid grid-cols-2 gap-2 sm:gap-2.5 w-full mt-4 sm:mt-5 pt-4 border-t border-app-border">
                    <a href="inventory.php?stock=low" class="flex flex-col items-center p-2 sm:p-2.5 rounded-lg bg-orange-950/40 border border-orange-500/40 hover:bg-orange-900/40 transition">
                        <span class="text-lg sm:text-xl font-bold text-orange-300"><?php echo $low_count; ?></span>
                        <span class="text-[10px] sm:text-[11px] text-orange-200/80 uppercase font-semibold flex items-center gap-1 mt-0.5">
                            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> Low Stock
                        </span>
                    </a>
                    <a href="inventory.php?stock=out" class="flex flex-col items-center p-2 sm:p-2.5 rounded-lg bg-red-950/40 border border-red-500/40 hover:bg-red-900/40 transition">
                        <span class="text-lg sm:text-xl font-bold text-red-300"><?php echo $out_count; ?></span>
                        <span class="text-[10px] sm:text-[11px] text-red-200/80 uppercase font-semibold flex items-center gap-1 mt-0.5">
                            <i class="fa-solid fa-circle-xmark text-[10px]"></i> Out of Stock
                        </span>
                    </a>
                </div>

                <a href="inventory.php" class="mt-4 sm:mt-5 w-full text-center px-6 py-2.5 sm:py-2 bg-brand hover:bg-brand-hover text-white font-bold rounded-lg transition text-sm shadow">
                    View All Items
                </a>
            </div>
        </div>

        <div class="md:col-span-2">
            <div class="bg-app-card border border-app-border rounded-xl p-5 sm:p-6 shadow-lg h-full flex flex-col justify-between">
                <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                    <h3 class="text-app-text font-bold text-base sm:text-lg flex items-center gap-2">
                        <i class="fa-solid fa-chart-pie text-brand"></i> Inventory Distribution
                    </h3>
                    <span class="text-xs text-app-muted bg-slate-800 px-2.5 py-1 rounded-full border border-slate-700">
                        <?php echo count($categories_summary); ?> Active Categories
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-5 sm:gap-6 items-center flex-grow">
                    <!-- Left: Donut Chart -->
                    <div class="sm:col-span-5 relative h-48 sm:h-56 w-full flex items-center justify-center">
                        <canvas id="categoryChart"></canvas>
                    </div>

                    <!-- Right: Visible Number Counters & Breakdown -->
                    <div class="sm:col-span-7 space-y-2.5">
                        <?php foreach ($categories_summary as $cat): ?>
                            <a href="inventory.php?cat=<?php echo urlencode($cat['category']); ?>"
                                class="block p-3 rounded-lg bg-slate-900/60 border border-slate-700/80 hover:border-slate-500 hover:bg-slate-900/90 transition group shadow-sm"
                                title="Click to view all <?php echo htmlspecialchars($cat['category']); ?> items">
                                <div class="flex items-center justify-between gap-2 mb-1.5 flex-wrap sm:flex-nowrap">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 <?php echo $cat['color']['bg']; ?>"></span>
                                        <span class="font-semibold text-sm text-white group-hover:text-blue-400 transition truncate">
                                            <?php echo htmlspecialchars($cat['category']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0 text-xs">
                                        <span class="text-slate-200 font-bold">
                                            <?php echo $cat['count']; ?> <span class="text-[11px] font-normal text-slate-400">items</span>
                                        </span>
                                        <span class="text-[11px] text-slate-400 font-mono hidden xs:inline">
                                            (<?php echo $cat['qty']; ?> units)
                                        </span>
                                        <span class="text-[11px] font-semibold px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
                                            <?php echo $cat['percent']; ?>%
                                        </span>
                                    </div>
                                </div>

                                <!-- Mini Progress Bar -->
                                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                    <div class="<?php echo $cat['color']['bg']; ?> h-1.5 rounded-full transition-all duration-500" style="width: <?php echo max(5, $cat['percent']); ?>%"></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recently Added Section -->
    <div class="bg-app-card border border-app-border rounded-xl shadow-lg overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-app-border flex justify-between items-center">
            <h3 class="text-app-text font-bold text-base sm:text-lg flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-brand"></i> Recently Added
            </h3>
            <a href="add_item.php" class="text-sm text-brand hover:text-brand-hover font-medium">
                <i class="fa-solid fa-plus mr-1"></i> Add New
            </a>
        </div>

        <!-- Phone Mobile List View (< sm screens) -->
        <div class="sm:hidden divide-y divide-app-border">
            <?php if (!empty($latest_items)): ?>
                <?php foreach ($latest_items as $row): ?>
                    <?php $stock = get_stock_status((int)$row['quantity'], (int)($row['min_quantity'] ?? 0)); ?>
                    <a href="view_item.php?id=<?php echo (int)$row['id']; ?>" class="p-4 flex items-center justify-between gap-3 hover:bg-app-bg/50 transition active:bg-app-bg">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-11 w-11 bg-app-bg rounded-lg flex items-center justify-center border border-app-border overflow-hidden flex-shrink-0">
                                <?php if (!empty($row['image_path'])): ?>
                                    <img src="../assets/uploads/<?php echo htmlspecialchars($row['image_path']); ?>" class="h-full w-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-microchip text-app-muted text-base"></i>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-white text-sm truncate flex items-center gap-1.5">
                                    <span class="truncate"><?php echo htmlspecialchars($row['item_name']); ?></span>
                                    <?php if (!empty($row['component_rating'])): ?>
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-400/50 flex-shrink-0">
                                            <?php echo htmlspecialchars($row['component_rating']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span class="inline-block px-2 py-0.5 text-[10px] rounded-full font-medium border <?php echo category_badge_class($row['category'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($row['category']); ?>
                                    </span>
                                    <?php if (!empty($row['location'])): ?>
                                        <span class="text-xs text-app-muted truncate">
                                            <i class="fa-solid fa-location-dot text-[9px] mr-0.5"></i><?php echo htmlspecialchars($row['location']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="text-right">
                                <span class="font-bold text-white text-sm">Qty: <?php echo (int)$row['quantity']; ?></span>
                                <?php if ($stock['code'] !== 'ok'): ?>
                                    <div class="text-[10px] font-bold uppercase mt-0.5 text-orange-400 flex items-center justify-end gap-1">
                                        <i class="fa-solid <?php echo $stock['icon']; ?> text-[9px]"></i> <?php echo htmlspecialchars($stock['label']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <i class="fa-solid fa-chevron-right text-slate-500 text-xs"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-6 text-center text-app-muted text-sm">
                    No items found. <a href="add_item.php" class="text-brand hover:underline font-semibold">Add your first item!</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tablet & Desktop Table View (hidden sm:block) -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-app-text">
                <thead class="bg-app-bg text-app-muted uppercase text-xs font-bold">
                    <tr>
                        <th class="px-5 sm:px-6 py-3">Item</th>
                        <th class="px-5 sm:px-6 py-3">Category</th>
                        <th class="px-5 sm:px-6 py-3 text-center">Qty &amp; Status</th>
                        <th class="px-5 sm:px-6 py-3 hidden md:table-cell">Added</th>
                        <th class="px-5 sm:px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-app-border">
                    <?php if (!empty($latest_items)): ?>
                        <?php foreach ($latest_items as $row): ?>
                            <?php $stock = get_stock_status((int)$row['quantity'], (int)($row['min_quantity'] ?? 0)); ?>
                            <tr class="hover:bg-app-bg/50 transition cursor-pointer" onclick="window.location='view_item.php?id=<?php echo (int)$row['id']; ?>'">
                                <td class="px-5 sm:px-6 py-3.5 sm:py-4 flex items-center gap-3">
                                    <div class="h-10 w-10 bg-app-bg rounded flex items-center justify-center border border-app-border overflow-hidden flex-shrink-0">
                                        <?php if (!empty($row['image_path'])): ?>
                                            <img src="../assets/uploads/<?php echo htmlspecialchars($row['image_path']); ?>" class="h-full w-full object-cover">
                                        <?php else: ?>
                                            <i class="fa-solid fa-microchip text-app-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-app-text flex flex-wrap items-center gap-2">
                                            <span><?php echo htmlspecialchars($row['item_name']); ?></span>
                                            <?php if (!empty($row['component_rating'])): ?>
                                                <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-amber-500/20 text-amber-300 border border-amber-400/50">
                                                    <?php echo htmlspecialchars($row['component_rating']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($row['serial_number'])): ?>
                                                <span class="px-1.5 py-0.5 text-[11px] font-mono rounded bg-cyan-950/80 text-cyan-300 border border-cyan-500/50">
                                                    S/N: <?php echo htmlspecialchars($row['serial_number']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-app-muted"><?php echo htmlspecialchars($row['location'] ?? ''); ?><?php echo !empty($row['sub_category']) ? ' &bull; ' . htmlspecialchars($row['sub_category']) : ''; ?></div>
                                    </div>
                                </td>

                                <td class="px-5 sm:px-6 py-3.5 sm:py-4">
                                    <a href="inventory.php?cat=<?php echo urlencode($row['category']); ?>"
                                        class="inline-block px-2.5 py-0.5 text-xs rounded-full font-medium border transition hover:opacity-85 <?php echo category_badge_class($row['category'] ?? ''); ?>"
                                        title="Filter by <?php echo htmlspecialchars($row['category']); ?>">
                                        <?php echo htmlspecialchars($row['category']); ?>
                                    </a>
                                </td>

                                <td class="px-5 sm:px-6 py-3.5 sm:py-4 text-center">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="font-bold text-app-text"><?php echo (int)$row['quantity']; ?></span>
                                        <?php if ($stock['code'] !== 'ok'): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?php echo $stock['class']; ?>">
                                                <i class="fa-solid <?php echo $stock['icon']; ?>"></i> <?php echo htmlspecialchars($stock['label']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-5 sm:px-6 py-3.5 sm:py-4 text-app-muted hidden md:table-cell">
                                    <?php echo date("M d, Y", strtotime($row['created_at'])); ?>
                                </td>

                                <td class="px-5 sm:px-6 py-3.5 sm:py-4 text-right">
                                    <a href="view_item.php?id=<?php echo (int)$row['id']; ?>" class="text-brand hover:text-brand-hover">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-app-muted">
                                No items found. <a href="add_item.php" class="text-brand hover:underline">Add your first item!</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    const chartLabels = <?php echo json_encode($labels); ?>;
    const chartData = <?php echo json_encode($counts); ?>;
    const chartColors = <?php echo json_encode($chart_hex_colors); ?>;
    const ctx = document.getElementById('categoryChart').getContext('2d');

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Catalog Items',
                data: chartData,
                backgroundColor: chartColors,
                borderColor: '#0f172a',
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            const total = <?php echo $total_assets; ?>;
                            const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return ` ${context.label}: ${val} items (${pct}%)`;
                        }
                    }
                }
            },
            layout: {
                padding: 4
            }
        }
    });
</script>

<?php require '../includes/footer.php'; ?>