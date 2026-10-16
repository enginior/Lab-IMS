<?php
require '../includes/db_conn.php';
require '../includes/header.php';

$message = "";
$error = "";
$item = null;
$category_presets = get_category_presets();

// Determine item ID from GET or POST
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    echo "<script>window.location.href='inventory.php';</script>";
    exit();
}

// 1. Fetch the existing item first
$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $item = $result->fetch_assoc();
} else {
    $stmt->close();
    echo "<script>window.location.href='inventory.php';</script>";
    exit();
}
$stmt->close();

// 2. POST LOGIC: Handle the Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = "Invalid session token. Please refresh the page and try again.";
    } else {
        $item_name = trim($_POST['item_name'] ?? '');
        $category = $_POST['category'] ?? 'Electronic';
        $allowed_categories = ['Electronic', 'Computer', 'Tool', 'Other'];
        if (!in_array($category, $allowed_categories, true)) {
            $category = 'Other';
        }
        $sub_category = trim($_POST['sub_category'] ?? '');
        $item_type = ($_POST['item_type'] ?? 'Bulk') === 'Unique' ? 'Unique' : 'Bulk';
        $component_rating = trim($_POST['component_rating'] ?? '');
        $model_number = trim($_POST['model_number'] ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');

        $condition_status = $_POST['condition_status'] ?? 'Working';
        $allowed_conditions = ['New', 'Working', 'Untested', 'For Parts'];
        if (!in_array($condition_status, $allowed_conditions, true)) {
            $condition_status = 'Working';
        }

        $quantity_raw = $_POST['quantity'] ?? '';
        $quantity = is_numeric($quantity_raw) ? max(0, (int)$quantity_raw) : -1;
        $min_quantity_raw = $_POST['min_quantity'] ?? 0;
        $min_quantity = is_numeric($min_quantity_raw) ? max(0, (int)$min_quantity_raw) : 0;
        $location = trim($_POST['location'] ?? '');
        $specifications = trim($_POST['specifications'] ?? '');
        $datasheet_url = sanitize_external_url($_POST['datasheet_url'] ?? null);

        if ($item_name === '' || $quantity < 0) {
            $error = "Item Name and a valid Quantity (0 or more) are required.";
        }

        // Logic: If user uploads a new image, use it. If not, keep the old one.
        $new_image_filename = null;
        if (empty($error) && isset($_FILES['item_image'])) {
            $upload = handle_item_image_upload($_FILES['item_image']);
            if ($upload['error']) {
                $error = $upload['error'];
            } elseif ($upload['filename']) {
                $new_image_filename = $upload['filename'];
            }
        }

        if (empty($error)) {
            if ($new_image_filename !== null) {
                $sql = "UPDATE inventory SET item_name=?, category=?, sub_category=?, item_type=?, component_rating=?, model_number=?, serial_number=?, condition_status=?, quantity=?, min_quantity=?, location=?, specifications=?, datasheet_url=?, image_path=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "ssssssssiissssi",
                    $item_name,
                    $category,
                    $sub_category,
                    $item_type,
                    $component_rating,
                    $model_number,
                    $serial_number,
                    $condition_status,
                    $quantity,
                    $min_quantity,
                    $location,
                    $specifications,
                    $datasheet_url,
                    $new_image_filename,
                    $id
                );
            } else {
                $sql = "UPDATE inventory SET item_name=?, category=?, sub_category=?, item_type=?, component_rating=?, model_number=?, serial_number=?, condition_status=?, quantity=?, min_quantity=?, location=?, specifications=?, datasheet_url=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "ssssssssiisssi",
                    $item_name,
                    $category,
                    $sub_category,
                    $item_type,
                    $component_rating,
                    $model_number,
                    $serial_number,
                    $condition_status,
                    $quantity,
                    $min_quantity,
                    $location,
                    $specifications,
                    $datasheet_url,
                    $id
                );
            }

            if ($stmt && $stmt->execute()) {
                $old_qty = (int)$item['quantity'];
                if ($quantity !== $old_qty) {
                    $qty_diff = $quantity - $old_qty;
                    log_stock_movement(
                        $conn,
                        $id,
                        $item_name,
                        (int)($_SESSION['user_id'] ?? 0),
                        $_SESSION['username'] ?? 'System',
                        'manual_edit',
                        $qty_diff,
                        $old_qty,
                        $quantity,
                        'Updated via item edit form'
                    );
                }

                // Safely delete old image file from disk using DB record (never from untrusted POST)
                if ($new_image_filename !== null && !empty($item['image_path'])) {
                    $safe_old_image = basename($item['image_path']);
                    $old_file_path = "../assets/uploads/" . $safe_old_image;
                    if (is_file($old_file_path)) {
                        unlink($old_file_path);
                    }
                }

                $message = "Item updated successfully!";
                // Refresh item data so the form shows new values
                $stmt->close();
                $refresh_stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
                $refresh_stmt->bind_param("i", $id);
                $refresh_stmt->execute();
                $item = $refresh_stmt->get_result()->fetch_assoc();
                $refresh_stmt->close();
            } else {
                $error = "Error updating record: " . ($stmt ? $stmt->error : $conn->error);
                if ($stmt) {
                    $stmt->close();
                }
            }
        }
    }
}
?>

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Edit Item</h1>
        <div class="flex items-center gap-4">
            <a href="add_item.php?clone_id=<?php echo (int)$item['id']; ?>" class="text-cyan-400 hover:text-cyan-300 transition text-sm">
                <i class="fa-solid fa-copy"></i> Clone / Add Unit
            </a>
            <a href="view_item.php?id=<?php echo (int)$item['id']; ?>" class="text-slate-400 hover:text-white transition text-sm">
                <i class="fa-solid fa-eye"></i> View Item
            </a>
            <a href="inventory.php" class="text-slate-400 hover:text-white transition text-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-900/50 border border-green-500 text-green-200 p-4 rounded mb-6">
            <i class="fa-solid fa-check-circle mr-2"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded mb-6">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-800 p-8 rounded-xl border border-slate-700 shadow-lg">
        <form id="edit-form" action="edit_item.php?id=<?php echo (int)$item['id']; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">

            <?php $cur_type = $item['item_type'] ?? 'Bulk'; ?>
            <!-- Tracking Mode Selector -->
            <div class="mb-6">
                <label class="block text-slate-400 text-xs uppercase tracking-wider font-bold mb-2">Tracking Type</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label id="label-type-bulk" class="flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition <?php echo $cur_type === 'Bulk' ? 'bg-blue-950/40 border-blue-500 text-white' : 'bg-slate-900 border-slate-700 text-slate-300'; ?>">
                        <input type="radio" name="item_type" value="Bulk" class="mt-1" <?php echo $cur_type === 'Bulk' ? 'checked' : ''; ?> onchange="updateTrackingMode()">
                        <div>
                            <div class="font-bold text-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-layer-group text-amber-400"></i> Small / Bulk Component
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">Resistors, capacitors, ICs, sensors (distinguished by value/rating badge)</p>
                        </div>
                    </label>
                    <label id="label-type-unique" class="flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition <?php echo $cur_type === 'Unique' ? 'bg-blue-950/40 border-blue-500 text-white' : 'bg-slate-900 border-slate-700 text-slate-300'; ?>">
                        <input type="radio" name="item_type" value="Unique" class="mt-1" <?php echo $cur_type === 'Unique' ? 'checked' : ''; ?> onchange="updateTrackingMode()">
                        <div>
                            <div class="font-bold text-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-barcode text-cyan-400"></i> Serialized / Large Hardware
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">GPUs, HDDs, RAM, MCUs, multimeters (tracked by Model # &amp; Serial #)</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Category & Guided Sub-Category -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Category *</label>
                    <select id="category-select" name="category" onchange="updateSubCategoryOptions()" class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500">
                        <option value="Electronic" <?php if ($item['category'] == 'Electronic') echo 'selected'; ?>>Electronic Component</option>
                        <option value="Computer" <?php if ($item['category'] == 'Computer') echo 'selected'; ?>>Computer Part</option>
                        <option value="Tool" <?php if ($item['category'] == 'Tool') echo 'selected'; ?>>Tool / Equipment</option>
                        <option value="Other" <?php if ($item['category'] == 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Sub-Category</label>
                    <div class="flex gap-2">
                        <select id="subcat-preset" onchange="applyPresetSubCategory()" class="w-1/2 bg-slate-900 border border-slate-600 text-slate-300 text-sm rounded px-3 py-2 focus:outline-none focus:border-blue-500">
                            <option value="">-- Choose Preset --</option>
                        </select>
                        <input id="subcat-input" type="text" name="sub_category" value="<?php echo htmlspecialchars($item['sub_category'] ?? ''); ?>"
                            class="w-1/2 bg-slate-900 border border-slate-600 text-white text-sm rounded px-3 py-2 focus:outline-none focus:border-blue-500">
                    </div>
                </div>
            </div>

            <!-- Item Name & Value / Rating / Spec Highlight Badge -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Item Name *</label>
                    <input type="text" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-amber-400 text-sm font-bold mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-tag"></i> Value / Rating / Key Spec Badge
                    </label>
                    <input type="text" name="component_rating" value="<?php echo htmlspecialchars($item['component_rating'] ?? ''); ?>"
                        placeholder="e.g. 4kΩ | 1/2W | ±5%  or  16GB | DDR5-5600"
                        class="w-full bg-slate-900 border border-amber-500/60 text-white rounded px-4 py-2 focus:outline-none focus:border-amber-400 placeholder-slate-500">
                </div>
            </div>

            <!-- Model / Part Number, Serial Number, and Condition -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-slate-900/60 rounded-lg border border-slate-700/80">
                <div>
                    <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-1.5">Model / Part #</label>
                    <input type="text" name="model_number" value="<?php echo htmlspecialchars($item['model_number'] ?? ''); ?>" placeholder="e.g. CD4017BE, WD10EZEX"
                        class="w-full bg-slate-900 border border-slate-600 text-white text-sm rounded px-3 py-2 focus:outline-none focus:border-blue-500 font-mono">
                </div>
                <div>
                    <label class="block text-cyan-400 text-xs font-bold uppercase tracking-wider mb-1.5">
                        <i class="fa-solid fa-fingerprint mr-1"></i> Serial Number (S/N)
                    </label>
                    <input type="text" name="serial_number" value="<?php echo htmlspecialchars($item['serial_number'] ?? ''); ?>" placeholder="Unique S/N"
                        class="w-full bg-slate-900 border border-slate-600 text-white text-sm rounded px-3 py-2 focus:outline-none focus:border-cyan-400 font-mono">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-1.5">Condition</label>
                    <?php $cur_cond = $item['condition_status'] ?? 'Working'; ?>
                    <select name="condition_status" class="w-full bg-slate-900 border border-slate-600 text-white text-sm rounded px-3 py-2 focus:outline-none focus:border-blue-500">
                        <option value="Working" <?php if ($cur_cond === 'Working') echo 'selected'; ?>>Working / Tested</option>
                        <option value="New" <?php if ($cur_cond === 'New') echo 'selected'; ?>>New / Unused</option>
                        <option value="Untested" <?php if ($cur_cond === 'Untested') echo 'selected'; ?>>Untested</option>
                        <option value="For Parts" <?php if ($cur_cond === 'For Parts') echo 'selected'; ?>>Defective / For Parts</option>
                    </select>
                </div>
            </div>

            <!-- Quantity, Low-Stock Threshold & Storage Location -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Quantity *</label>
                    <input type="number" name="quantity" min="0" value="<?php echo (int)$item['quantity']; ?>" required
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-orange-400 text-sm font-bold mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-bell"></i> Low-Stock Alert At
                    </label>
                    <input type="number" name="min_quantity" min="0" value="<?php echo (int)($item['min_quantity'] ?? 0); ?>"
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-orange-400">
                    <p class="text-[11px] text-slate-500 mt-1">Set <code class="text-slate-400">0</code> to disable Low-Stock badge.</p>
                </div>
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Storage Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>"
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div class="mb-6 border border-slate-700 bg-slate-900/50 rounded-lg p-4">
                <label class="block text-slate-400 text-sm font-bold mb-3">Item Image</label>
                <div class="flex flex-col sm:flex-row items-center gap-6">
                    <div class="w-32 h-32 bg-slate-800 rounded border border-slate-600 flex items-center justify-center overflow-hidden flex-shrink-0">
                        <?php if (!empty($item['image_path'])): ?>
                            <img id="current-or-preview-img" src="../assets/uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="object-cover w-full h-full">
                        <?php else: ?>
                            <img id="current-or-preview-img" src="#" class="object-cover w-full h-full hidden">
                            <i id="no-image-icon" class="fa-solid fa-image text-4xl text-slate-600"></i>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow w-full">
                        <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-28 border-2 border-slate-600 border-dashed rounded-lg cursor-pointer bg-slate-900 hover:bg-slate-700 hover:border-blue-500 transition px-4 text-center">
                            <div class="flex flex-col items-center justify-center py-3">
                                <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400 mb-1"></i>
                                <p class="text-sm text-slate-400"><span class="font-semibold">Click to replace</span>, drag & drop, or press <kbd class="px-1.5 py-0.5 bg-slate-800 border border-slate-600 rounded text-xs">Ctrl+V</kbd></p>
                                <p id="selected-file-label" class="text-xs text-slate-500 mt-1">PNG, JPG or WEBP (MAX. 5MB)</p>
                            </div>
                            <input id="dropzone-file" type="file" name="item_image" class="hidden" accept="image/*" onchange="previewEditImage(event)" />
                        </label>
                    </div>
                </div>
            </div>

            <!-- Datasheet / Pinout / External Reference URL -->
            <div class="mb-6">
                <label class="block text-slate-400 text-sm font-bold mb-2 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-file-pdf text-red-400"></i>
                        <span>Datasheet / Pinout / Reference URL</span>
                        <span class="text-xs font-normal text-slate-500">(Optional)</span>
                    </span>
                    <?php if (!empty($item['datasheet_url'])): ?>
                        <a href="<?php echo htmlspecialchars($item['datasheet_url']); ?>" target="_blank" rel="noopener noreferrer"
                            class="text-xs text-blue-400 hover:text-blue-300 font-normal inline-flex items-center gap-1">
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> Open current link
                        </a>
                    <?php endif; ?>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-link text-xs"></i>
                    </div>
                    <input type="url" name="datasheet_url" value="<?php echo htmlspecialchars($item['datasheet_url'] ?? ''); ?>"
                        placeholder="https://www.ti.com/lit/ds/...pdf or manufacturer documentation link"
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-blue-500 transition font-mono">
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Direct link to manufacturer PDF, pinout diagram, or forum schematic. A clickable button will be generated automatically.</p>
            </div>

            <div class="mb-8">
                <label class="block text-slate-400 text-sm font-bold mb-2">Additional Specifications / Notes</label>
                <textarea name="specifications" rows="4"
                    class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500"><?php echo htmlspecialchars($item['specifications'] ?? ''); ?></textarea>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-700">
                <button type="button"
                    onclick="if(confirm('Are you sure you want to delete this item? This cannot be undone.')) { document.getElementById('delete-item-form').submit(); }"
                    class="text-red-500 hover:text-red-400 text-sm font-medium px-4 py-2 flex items-center gap-2 transition hover:bg-red-900/20 rounded cursor-pointer">
                    <i class="fa-solid fa-trash"></i> Delete Item
                </button>

                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-6 rounded shadow-lg transition cursor-pointer">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> Save Changes
                </button>
            </div>
        </form>

        <form id="delete-item-form" action="delete_item.php" method="POST" class="hidden">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
        </form>
    </div>
</div>

<script>
    const categoryPresets = <?php echo json_encode($category_presets); ?>;

    function updateSubCategoryOptions() {
        const cat = document.getElementById('category-select').value;
        const presetSelect = document.getElementById('subcat-preset');
        const currentInput = document.getElementById('subcat-input').value;
        const options = categoryPresets[cat] || [];

        presetSelect.innerHTML = '<option value="">-- Choose Preset --</option>';
        options.forEach(opt => {
            const el = document.createElement('option');
            el.value = opt;
            el.textContent = opt;
            if (opt === currentInput) {
                el.selected = true;
            }
            presetSelect.appendChild(el);
        });
    }

    function applyPresetSubCategory() {
        const presetVal = document.getElementById('subcat-preset').value;
        if (presetVal) {
            document.getElementById('subcat-input').value = presetVal;
        }
    }

    function updateTrackingMode() {
        const isUnique = document.querySelector('input[name="item_type"]:checked').value === 'Unique';
        const labelBulk = document.getElementById('label-type-bulk');
        const labelUnique = document.getElementById('label-type-unique');

        if (isUnique) {
            labelUnique.className = 'flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition bg-blue-950/40 border-blue-500 text-white';
            labelBulk.className = 'flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition bg-slate-900 border-slate-700 text-slate-300';
        } else {
            labelBulk.className = 'flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition bg-blue-950/40 border-blue-500 text-white';
            labelUnique.className = 'flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition bg-slate-900 border-slate-700 text-slate-300';
        }
    }

    updateSubCategoryOptions();

    function previewEditImage(event) {
        const file = event.target.files[0];
        if (file) {
            showEditPreview(file);
        }
    }

    document.addEventListener('paste', function(event) {
        const items = (event.clipboardData || event.originalEvent.clipboardData).items;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                const blob = items[i].getAsFile();
                const timestamp = new Date().getTime();
                const newFile = new File([blob], "pasted_image_" + timestamp + ".png", {
                    type: "image/png"
                });

                const fileInput = document.getElementById('dropzone-file');
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(newFile);
                fileInput.files = dataTransfer.files;

                showEditPreview(newFile);
            }
        }
    });

    function showEditPreview(file) {
        const previewImg = document.getElementById('current-or-preview-img');
        const noImgIcon = document.getElementById('no-image-icon');
        const dropzoneLabel = document.querySelector('label[for="dropzone-file"]');
        const fileLabel = document.getElementById('selected-file-label');

        previewImg.src = URL.createObjectURL(file);
        previewImg.classList.remove('hidden');
        if (noImgIcon) {
            noImgIcon.classList.add('hidden');
        }
        if (fileLabel) {
            fileLabel.textContent = "Selected: " + file.name;
            fileLabel.classList.replace('text-slate-500', 'text-green-400');
        }
        if (dropzoneLabel) {
            dropzoneLabel.classList.add('border-green-500');
            dropzoneLabel.classList.remove('border-slate-600');
        }
    }
</script>

<?php require '../includes/footer.php'; ?>