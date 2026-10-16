<?php
require '../includes/db_conn.php';
require '../includes/header.php';

// 1. Security Check & Fetch
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: inventory.php");
    exit();
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $stmt->close();
    echo "<div class='text-center text-white mt-10'>Item not found.</div>";
    require '../includes/footer.php';
    exit();
}

$item = $result->fetch_assoc();
$stmt->close();

// Fetch sibling variants (same item_name) or other serialized units (same model_number)
$siblings = [];
$sib_stmt = $conn->prepare("SELECT * FROM inventory WHERE id != ? AND (item_name = ? OR (model_number IS NOT NULL AND model_number != '' AND model_number = ?)) ORDER BY component_rating ASC, serial_number ASC");
$model_val = $item['model_number'] ?? '';
$sib_stmt->bind_param("iss", $id, $item['item_name'], $model_val);
$sib_stmt->execute();
$sib_res = $sib_stmt->get_result();
while ($srow = $sib_res->fetch_assoc()) {
    $siblings[] = $srow;
}
$sib_stmt->close();

$item_type = $item['item_type'] ?? 'Bulk';
$cond = $item['condition_status'] ?? 'Working';
$cond_badge = match ($cond) {
    'New'       => 'bg-emerald-950/80 text-emerald-300 border-emerald-600/60',
    'Working'   => 'bg-green-950/80 text-green-300 border-green-600/60',
    'Untested'  => 'bg-amber-950/80 text-amber-300 border-amber-600/60',
    'For Parts' => 'bg-red-950/80 text-red-300 border-red-600/60',
    default     => 'bg-slate-800 text-slate-300 border-slate-600'
};
$stock = get_stock_status((int)$item['quantity'], (int)($item['min_quantity'] ?? 0));
// Fetch recent audit logs for this item
$item_logs = [];
$log_stmt = $conn->prepare("SELECT * FROM inventory_logs WHERE item_id = ? ORDER BY created_at DESC LIMIT 8");
$log_stmt->bind_param("i", $id);
$log_stmt->execute();
$lres = $log_stmt->get_result();
while ($lrow = $lres->fetch_assoc()) {
    $item_logs[] = $lrow;
}
$log_stmt->close();
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex flex-wrap justify-between items-center gap-3">
        <a href="inventory.php" class="text-slate-400 hover:text-white transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Back to Inventory
        </a>
        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="openStockModal()" class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm px-3.5 py-2 rounded shadow transition flex items-center gap-1.5 font-medium" title="Log a restock, issue, or physical count">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Adjust Stock</span>
            </button>
            <a href="print_labels.php?item_id=<?php echo (int)$item['id']; ?>" class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 text-sm px-3 py-2 rounded shadow transition flex items-center gap-1.5" title="Print QR code label for this part">
                <i class="fa-solid fa-qrcode text-cyan-400"></i>
                <span>Print Tag</span>
            </a>
            <?php if (!empty($item['datasheet_url'])): ?>
                <a href="<?php echo htmlspecialchars($item['datasheet_url']); ?>" target="_blank" rel="noopener noreferrer"
                    class="bg-red-600/20 hover:bg-red-600/30 border border-red-500/60 text-red-300 hover:text-white text-sm px-3.5 py-2 rounded shadow transition flex items-center gap-1.5 font-medium"
                    title="Open official datasheet, pinout, or manual">
                    <i class="fa-solid fa-file-pdf text-red-400"></i>
                    <span>Datasheet</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-75"></i>
                </a>
            <?php endif; ?>
            <a href="add_item.php?clone_id=<?php echo (int)$item['id']; ?>" class="bg-slate-800 hover:bg-slate-700 border border-cyan-500/50 text-cyan-300 text-sm px-3.5 py-2 rounded shadow transition flex items-center gap-1.5">
                <i class="fa-solid fa-copy"></i>
                <span><?php echo $item_type === 'Unique' ? 'Add Unit (New S/N)' : 'Add Variant'; ?></span>
            </a>
            <a href="edit_item.php?id=<?php echo (int)$item['id']; ?>" class="bg-blue-600 hover:bg-blue-500 text-white text-sm px-4 py-2 rounded shadow transition flex items-center gap-1.5 font-medium">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>Edit</span>
            </a>
        </div>
    </div>

    <div class="bg-slate-800 rounded-xl border border-slate-700 shadow-2xl overflow-hidden">

        <div class="md:flex">
            <div class="md:w-1/3 bg-slate-900 p-4 flex items-center justify-center border-b md:border-b-0 md:border-r border-slate-700">
                <?php if (!empty($item['image_path'])): ?>
                    <img src="../assets/uploads/<?php echo htmlspecialchars($item['image_path']); ?>"
                        class="max-h-64 object-contain rounded shadow-lg border border-slate-700 cursor-zoom-in"
                        onclick="window.open(this.src, '_blank')">
                <?php else: ?>
                    <div class="text-slate-600 flex flex-col items-center py-8">
                        <i class="fa-solid fa-microchip text-6xl mb-2"></i>
                        <span class="text-sm">No Image</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="md:w-2/3 p-4 sm:p-6 md:p-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="inline-block px-2.5 py-0.5 text-xs rounded bg-slate-700 text-slate-200 font-medium">
                                <?php echo htmlspecialchars($item['category']); ?><?php echo !empty($item['sub_category']) ? ' / ' . htmlspecialchars($item['sub_category']) : ''; ?>
                            </span>
                            <?php if ($item_type === 'Unique'): ?>
                                <span class="px-2 py-0.5 text-xs rounded border font-semibold <?php echo $cond_badge; ?>">
                                    <?php echo htmlspecialchars($cond); ?>
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 text-xs rounded bg-slate-900 border border-slate-700 text-slate-400">
                                    Bulk Component
                                </span>
                            <?php endif; ?>
                        </div>

                        <h1 class="text-3xl font-bold text-white mb-2"><?php echo htmlspecialchars($item['item_name']); ?></h1>

                        <!-- High-Contrast Value / Rating Badge -->
                        <?php if (!empty($item['component_rating'])): ?>
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-500/20 border border-amber-400/60 text-amber-300 font-bold text-sm shadow-sm mt-1">
                                <i class="fa-solid fa-bolt text-amber-400"></i>
                                <?php echo htmlspecialchars($item['component_rating']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-slate-900 text-white px-4 py-3 rounded-lg text-center border border-slate-700 flex-shrink-0 flex flex-col items-center">
                        <span class="block text-xs text-slate-400 uppercase font-bold mb-1">Quantity</span>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="adjustItemQty(<?php echo (int)$item['id']; ?>, -1)"
                                class="w-7 h-7 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-300 hover:text-white transition cursor-pointer" title="Decrease quantity">
                                <i class="fa-solid fa-minus text-xs"></i>
                            </button>
                            <span id="item-qty" class="text-2xl font-bold min-w-[2rem]"><?php echo (int)$item['quantity']; ?></span>
                            <button type="button" onclick="adjustItemQty(<?php echo (int)$item['id']; ?>, 1)"
                                class="w-7 h-7 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-300 hover:text-white transition cursor-pointer" title="Increase quantity">
                                <i class="fa-solid fa-plus text-xs"></i>
                            </button>
                        </div>
                        <span id="item-stock-badge" class="mt-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider border <?php echo $stock['class']; ?>">
                            <i id="item-stock-icon" class="fa-solid <?php echo $stock['icon']; ?>"></i>
                            <span id="item-stock-label"><?php echo htmlspecialchars($stock['label']); ?></span>
                        </span>
                        <?php if ((int)($item['min_quantity'] ?? 0) > 0): ?>
                            <span class="text-[10px] text-slate-500 mt-1">Alert threshold: &le; <?php echo (int)$item['min_quantity']; ?></span>
                        <?php endif; ?>
                        <button type="button" onclick="openStockModal()" class="mt-2.5 text-[11px] text-cyan-400 hover:text-cyan-300 font-semibold inline-flex items-center gap-1 transition">
                            <i class="fa-solid fa-boxes-stacked text-[10px]"></i> Log Restock / Issue
                        </button>
                    </div>
                </div>

                <!-- Hardware Model & Serial Number Row -->
                <?php if (!empty($item['model_number']) || !empty($item['serial_number'])): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 my-5 p-3.5 bg-slate-900/70 rounded-lg border border-slate-700">
                        <div>
                            <span class="block text-[11px] text-slate-500 uppercase font-bold">Model / Part Number</span>
                            <span class="font-mono text-sm text-white"><?php echo htmlspecialchars($item['model_number'] ?: '—'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[11px] text-cyan-400 uppercase font-bold"><i class="fa-solid fa-fingerprint mr-1"></i>Serial Number (S/N)</span>
                            <span class="font-mono text-sm text-cyan-300 font-semibold"><?php echo htmlspecialchars($item['serial_number'] ?: '—'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-6 mt-6">
                    <div>
                        <span class="block text-xs text-slate-500 uppercase font-bold mb-1">Storage Location</span>
                        <a href="inventory.php?loc=<?php echo urlencode(!empty($item['location']) ? $item['location'] : '__unassigned__'); ?>" class="text-lg text-blue-300 hover:text-blue-200 transition inline-flex items-center gap-2" title="View all items in this location">
                            <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($item['location'] ?: 'Unassigned'); ?>
                        </a>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 uppercase font-bold mb-1">Added On</span>
                        <div class="text-slate-300 text-sm">
                            <?php echo date("M d, Y", strtotime($item['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-8 border-t border-slate-700 bg-slate-800/50">
            <h3 class="text-white font-bold text-lg mb-4 flex items-center gap-2">
                <i class="fa-solid fa-align-left text-slate-500"></i> Specifications &amp; Notes
            </h3>

            <?php if (!empty($item['datasheet_url'])): ?>
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 p-4 bg-slate-900/90 rounded-lg border border-red-500/40 shadow-sm">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-red-950/80 border border-red-600/50 flex items-center justify-center text-red-400 flex-shrink-0">
                            <i class="fa-solid fa-file-pdf text-lg"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[11px] uppercase tracking-wider font-bold text-slate-400">External Documentation / Datasheet</span>
                            <a href="<?php echo htmlspecialchars($item['datasheet_url']); ?>" target="_blank" rel="noopener noreferrer"
                                class="text-sm text-red-300 hover:text-red-200 underline font-mono truncate block max-w-md md:max-w-xl" title="<?php echo htmlspecialchars($item['datasheet_url']); ?>">
                                <?php echo htmlspecialchars($item['datasheet_url']); ?>
                            </a>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($item['datasheet_url']); ?>" target="_blank" rel="noopener noreferrer"
                        class="px-3.5 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-white text-xs font-semibold flex items-center gap-2 transition shadow flex-shrink-0">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in New Tab
                    </a>
                </div>
            <?php endif; ?>

            <div class="bg-slate-900/50 rounded-lg p-6 border border-slate-700/50 text-slate-300 leading-relaxed whitespace-pre-wrap font-mono text-sm"><?php echo !empty($item['specifications']) ? htmlspecialchars($item['specifications']) : "No additional specifications recorded."; ?></div>
        </div>

    </div>

    <!-- Related Variants (e.g. 1/4W vs 1/2W Resistor) or Other Units of Same Model (Different Serial Numbers) -->
    <?php if (!empty($siblings)): ?>
        <div class="bg-slate-800 rounded-xl border border-slate-700 shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center">
                <h3 class="text-white font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-cyan-400"></i>
                    Other Variants / Serialized Units of "<?php echo htmlspecialchars($item['item_name']); ?>"
                </h3>
                <span class="text-xs text-slate-400"><?php echo count($siblings); ?> related item(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Item</th>
                            <th class="px-6 py-3">Value / Rating Badge</th>
                            <th class="px-6 py-3">Model / Serial #</th>
                            <th class="px-6 py-3">Condition</th>
                            <th class="px-6 py-3">Location</th>
                            <th class="px-6 py-3 text-center">Docs</th>
                            <th class="px-6 py-3 text-center">Qty</th>
                            <th class="px-6 py-3 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach ($siblings as $sib): ?>
                            <tr class="hover:bg-slate-700/40 transition cursor-pointer" onclick="window.location='view_item.php?id=<?php echo (int)$sib['id']; ?>'">
                                <td class="px-6 py-3 font-medium text-white"><?php echo htmlspecialchars($sib['item_name']); ?></td>
                                <td class="px-6 py-3">
                                    <?php if (!empty($sib['component_rating'])): ?>
                                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-400/50">
                                            <?php echo htmlspecialchars($sib['component_rating']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs">
                                    <?php if (!empty($sib['serial_number'])): ?>
                                        <span class="text-cyan-300">S/N: <?php echo htmlspecialchars($sib['serial_number']); ?></span>
                                    <?php elseif (!empty($sib['model_number'])): ?>
                                        <span class="text-slate-300">M/N: <?php echo htmlspecialchars($sib['model_number']); ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-xs"><?php echo htmlspecialchars($sib['condition_status'] ?? 'Working'); ?></td>
                                <td class="px-6 py-3 text-xs"><?php echo htmlspecialchars($sib['location'] ?: 'Unassigned'); ?></td>
                                <td class="px-6 py-3 text-center" onclick="event.stopPropagation()">
                                    <?php if (!empty($sib['datasheet_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($sib['datasheet_url']); ?>" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded bg-red-950/80 border border-red-600/50 text-red-400 hover:text-red-200 transition"
                                            title="Open Datasheet">
                                            <i class="fa-solid fa-file-pdf text-xs"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-600">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-center font-bold text-white"><?php echo (int)$sib['quantity']; ?></td>
                                <td class="px-6 py-3 text-right">
                                    <a href="view_item.php?id=<?php echo (int)$sib['id']; ?>" class="text-blue-400 hover:text-blue-300">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Component Stock Movement & Audit History -->
    <div class="bg-slate-800 rounded-xl border border-slate-700 shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 flex flex-wrap justify-between items-center gap-2">
            <h3 class="text-white font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-blue-400"></i>
                Stock Movement &amp; Audit Trail
            </h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400"><?php echo count($item_logs); ?> recent event(s)</span>
                <a href="stock_log.php?search=<?php echo urlencode($item['item_name']); ?>" class="text-xs text-blue-400 hover:text-blue-300 font-medium inline-flex items-center gap-1">
                    Full Ledger <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>
        </div>

        <?php if (empty($item_logs)): ?>
            <div class="p-8 text-center text-slate-400 text-sm">
                <i class="fa-solid fa-clipboard-list text-2xl text-slate-600 mb-2 block"></i>
                No stock movement events recorded for this component yet.
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Timestamp</th>
                            <th class="px-6 py-3">Action</th>
                            <th class="px-6 py-3 text-center">Movement</th>
                            <th class="px-6 py-3 text-center">Balance</th>
                            <th class="px-6 py-3">User</th>
                            <th class="px-6 py-3">Notes / Purpose</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60">
                        <?php foreach ($item_logs as $lg): ?>
                            <?php
                            $action_badges = [
                                'quick_adjust' => ['label' => 'Stepper', 'class' => 'bg-slate-700 text-slate-200 border-slate-600'],
                                'restock'      => ['label' => 'Restock', 'class' => 'bg-emerald-950/80 text-emerald-300 border-emerald-700'],
                                'manual_edit'  => ['label' => 'Form Edit', 'class' => 'bg-blue-950/80 text-blue-300 border-blue-700'],
                                'item_created' => ['label' => 'Created', 'class' => 'bg-purple-950/80 text-purple-300 border-purple-700'],
                                'item_deleted' => ['label' => 'Deleted', 'class' => 'bg-rose-950/80 text-rose-300 border-rose-700'],
                            ];
                            $act = $action_badges[$lg['change_type']] ?? ['label' => $lg['change_type'], 'class' => 'bg-slate-800 text-slate-300 border-slate-700'];
                            $delta_num = (int)$lg['delta'];
                            ?>
                            <tr class="hover:bg-slate-700/30 transition">
                                <td class="px-6 py-3 whitespace-nowrap text-xs text-slate-400">
                                    <span class="text-slate-300 font-mono"><?php echo date("M d, Y", strtotime($lg['created_at'])); ?></span>
                                    <span class="text-[11px] text-slate-500 block"><?php echo date("h:i A", strtotime($lg['created_at'])); ?></span>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-xs border font-medium <?php echo $act['class']; ?>">
                                        <?php echo htmlspecialchars($act['label']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-center whitespace-nowrap">
                                    <?php if ($delta_num > 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-600/50">
                                            <i class="fa-solid fa-arrow-up text-[10px]"></i> +<?php echo $delta_num; ?>
                                        </span>
                                    <?php elseif ($delta_num < 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-rose-950/80 text-rose-300 border border-rose-600/50">
                                            <i class="fa-solid fa-arrow-down text-[10px]"></i> <?php echo $delta_num; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-500 font-mono text-xs">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-center whitespace-nowrap font-mono text-xs">
                                    <span class="text-slate-400"><?php echo (int)$lg['old_quantity']; ?></span>
                                    <i class="fa-solid fa-arrow-right text-[10px] text-slate-600 mx-1"></i>
                                    <span class="text-white font-bold"><?php echo (int)$lg['new_quantity']; ?></span>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-xs text-slate-300">
                                    <i class="fa-solid fa-user-circle text-slate-500 mr-1"></i><?php echo htmlspecialchars($lg['username']); ?>
                                </td>
                                <td class="px-6 py-3 text-xs text-slate-400 max-w-xs truncate" title="<?php echo htmlspecialchars($lg['notes'] ?? ''); ?>">
                                    <?php echo !empty($lg['notes']) ? htmlspecialchars($lg['notes']) : '<span class="text-slate-600 italic">—</span>'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card Feed (< sm) -->
            <div class="sm:hidden divide-y divide-slate-700/60">
                <?php foreach ($item_logs as $lg): ?>
                    <?php $delta_num = (int)$lg['delta']; ?>
                    <div class="p-3.5 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-mono"><?php echo date("M d, Y · h:i A", strtotime($lg['created_at'])); ?></span>
                            <?php if ($delta_num > 0): ?>
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-600/50">
                                    +<?php echo $delta_num; ?>
                                </span>
                            <?php elseif ($delta_num < 0): ?>
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-rose-950/80 text-rose-300 border border-rose-600/50">
                                    <?php echo $delta_num; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-500 font-mono text-xs">0</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-300">
                            <div>
                                <span class="text-slate-400">Balance:</span>
                                <span class="font-mono text-slate-300"><?php echo (int)$lg['old_quantity']; ?></span>
                                &rarr;
                                <span class="font-mono text-white font-bold"><?php echo (int)$lg['new_quantity']; ?></span>
                            </div>
                            <span class="text-slate-400 text-[11px]"><i class="fa-solid fa-user-circle mr-1"></i><?php echo htmlspecialchars($lg['username']); ?></span>
                        </div>
                        <?php if (!empty($lg['notes'])): ?>
                            <div class="text-xs text-slate-400 bg-slate-900/60 p-2 rounded border border-slate-700/40">
                                <?php echo htmlspecialchars($lg['notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Stock Adjustment Modal -->
<div id="stock-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center bg-slate-900/60">
            <h3 class="text-white font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-boxes-stacked text-emerald-400"></i> Stock Adjustment
            </h3>
            <button type="button" onclick="closeStockModal()" class="text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form id="stock-modal-form" onsubmit="submitStockAdjustment(event)" class="p-6 space-y-4">
            <div id="modal-error" class="hidden p-3 bg-red-950/80 border border-red-600/60 rounded text-red-200 text-xs"></div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Operation Type</label>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" data-mode="restock" onclick="setStockMode('restock')" class="stock-mode-btn py-2 px-3 text-xs font-semibold rounded border transition text-center bg-emerald-600 text-white border-emerald-500">
                        <i class="fa-solid fa-plus-circle block text-sm mb-1"></i> Restock (+)
                    </button>
                    <button type="button" data-mode="issue" onclick="setStockMode('issue')" class="stock-mode-btn py-2 px-3 text-xs font-semibold rounded border transition text-center bg-slate-900 text-slate-400 border-slate-700 hover:text-white">
                        <i class="fa-solid fa-minus-circle block text-sm mb-1"></i> Issue (-)
                    </button>
                    <button type="button" data-mode="count" onclick="setStockMode('count')" class="stock-mode-btn py-2 px-3 text-xs font-semibold rounded border transition text-center bg-slate-900 text-slate-400 border-slate-700 hover:text-white">
                        <i class="fa-solid fa-scale-balanced block text-sm mb-1"></i> Count (=)
                    </button>
                </div>
            </div>

            <div>
                <label id="stock-amount-label" class="block text-xs font-bold text-slate-400 uppercase mb-1">Units to Add (+)</label>
                <input type="number" id="stock-amount" min="1" step="1" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono text-base focus:outline-none focus:border-blue-500">
                <span class="text-[11px] text-slate-500 mt-1 block">Current balance: <strong id="modal-current-qty" class="text-white"><?php echo (int)$item['quantity']; ?></strong></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Reason / Notes / PO #</label>
                <input type="text" id="stock-notes" placeholder="e.g. Received batch order, Lab project checkout..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500">
            </div>

            <div class="pt-2 flex justify-end gap-2.5">
                <button type="button" onclick="closeStockModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg text-sm font-medium transition">Cancel</button>
                <button type="submit" id="stock-submit-btn" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span>Apply Movement</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const csrfToken = <?php echo json_encode(csrf_token()); ?>;
    let currentStockMode = 'restock';

    function openStockModal() {
        document.getElementById('modal-error').classList.add('hidden');
        document.getElementById('stock-amount').value = '';
        document.getElementById('stock-notes').value = '';
        setStockMode('restock');
        document.getElementById('stock-modal').classList.remove('hidden');
        document.getElementById('stock-amount').focus();
    }

    function closeStockModal() {
        document.getElementById('stock-modal').classList.add('hidden');
    }

    function setStockMode(mode) {
        currentStockMode = mode;
        document.querySelectorAll('.stock-mode-btn').forEach(btn => {
            if (btn.dataset.mode === mode) {
                btn.className = 'stock-mode-btn py-2 px-3 text-xs font-semibold rounded border transition text-center bg-emerald-600 text-white border-emerald-500';
            } else {
                btn.className = 'stock-mode-btn py-2 px-3 text-xs font-semibold rounded border transition text-center bg-slate-900 text-slate-400 border-slate-700 hover:text-white';
            }
        });

        const lbl = document.getElementById('stock-amount-label');
        const input = document.getElementById('stock-amount');
        if (mode === 'restock') {
            lbl.textContent = 'Units to Add (+)';
            input.min = '1';
        } else if (mode === 'issue') {
            lbl.textContent = 'Units to Deduct (-)';
            input.min = '1';
        } else {
            lbl.textContent = 'New Exact Count (=)';
            input.min = '0';
        }
    }

    async function submitStockAdjustment(e) {
        e.preventDefault();
        const amountVal = parseInt(document.getElementById('stock-amount').value, 10);
        const notesVal = document.getElementById('stock-notes').value.trim();
        const errBox = document.getElementById('modal-error');
        const submitBtn = document.getElementById('stock-submit-btn');

        if (isNaN(amountVal) || (currentStockMode !== 'count' && amountVal <= 0) || (currentStockMode === 'count' && amountVal < 0)) {
            errBox.textContent = 'Please enter a valid quantity.';
            errBox.classList.remove('hidden');
            return;
        }

        let payload = {
            id: <?php echo (int)$item['id']; ?>,
            csrf_token: csrfToken,
            notes: notesVal
        };

        if (currentStockMode === 'restock') {
            payload.delta = amountVal;
            payload.change_type = 'restock';
        } else if (currentStockMode === 'issue') {
            payload.delta = -amountVal;
            payload.change_type = 'restock';
        } else {
            payload.new_quantity = amountVal;
            payload.change_type = 'manual_edit';
        }

        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            const res = await fetch('update_quantity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                errBox.textContent = data.error || 'Failed to update stock.';
                errBox.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Apply Movement';
            }
        } catch (err) {
            errBox.textContent = 'Network or server error.';
            errBox.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Apply Movement';
        }
    }

    // Close modal on backdrop click or ESC
    document.getElementById('stock-modal').addEventListener('click', (e) => {
        if (e.target.id === 'stock-modal') closeStockModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeStockModal();
    });

    async function adjustItemQty(itemId, delta) {
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
                document.getElementById('item-qty').textContent = data.quantity;
                const modalCur = document.getElementById('modal-current-qty');
                if (modalCur) modalCur.textContent = data.quantity;

                if (data.stock_status) {
                    const st = data.stock_status;
                    const badge = document.getElementById('item-stock-badge');
                    const icon = document.getElementById('item-stock-icon');
                    const label = document.getElementById('item-stock-label');
                    if (badge && icon && label) {
                        badge.className = `mt-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider border ${st.class}`;
                        icon.className = `fa-solid ${st.icon}`;
                        label.textContent = st.label;
                    }
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