<?php
require '../includes/db_conn.php';
require '../includes/header.php';

$message = "";
$error = "";
$category_presets = get_category_presets();

// Optional prefill when clicking "Add Another Unit / Variant" from an existing item
$prefill = [
    'item_name'        => '',
    'category'         => 'Electronic',
    'sub_category'     => '',
    'item_type'        => 'Bulk',
    'component_rating' => '',
    'model_number'     => '',
    'serial_number'    => '',
    'condition_status' => 'Working',
    'quantity'         => 1,
    'min_quantity'     => 5,
    'location'         => '',
    'specifications'   => '',
    'datasheet_url'    => ''
];

if (isset($_GET['clone_id']) && is_numeric($_GET['clone_id'])) {
    $clone_id = (int)$_GET['clone_id'];
    $cstmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
    $cstmt->bind_param("i", $clone_id);
    $cstmt->execute();
    $cres = $cstmt->get_result();
    if ($crow = $cres->fetch_assoc()) {
        $prefill['item_name']        = $crow['item_name'];
        $prefill['category']         = $crow['category'];
        $prefill['sub_category']     = $crow['sub_category'] ?? '';
        $prefill['item_type']        = $crow['item_type'] ?? 'Unique';
        $prefill['component_rating'] = $crow['component_rating'] ?? '';
        $prefill['model_number']     = $crow['model_number'] ?? '';
        $prefill['serial_number']    = ''; // Leave blank for the new unit's unique serial number
        $prefill['condition_status'] = $crow['condition_status'] ?? 'Working';
        $prefill['quantity']         = 1;
        $prefill['min_quantity']     = (int)($crow['min_quantity'] ?? ($prefill['item_type'] === 'Bulk' ? 5 : 0));
        $prefill['location']         = $crow['location'] ?? '';
        $prefill['specifications']   = $crow['specifications'] ?? '';
        $prefill['datasheet_url']    = $crow['datasheet_url'] ?? '';
    }
    $cstmt->close();
}

// Handle Form Submission
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

        // Default image path is null
        $image_db_path = NULL;

        // HANDLE IMAGE UPLOAD
        if (isset($_FILES['item_image'])) {
            $upload = handle_item_image_upload($_FILES['item_image']);
            if ($upload['error']) {
                $error = $upload['error'];
            } else {
                $image_db_path = $upload['filename'];
            }
        }

        if ($item_name === '' || $quantity < 0) {
            $error = "Item Name and a valid Quantity (0 or more) are required.";
        } elseif (empty($error)) { // Only proceed if no upload errors

            $sql = "INSERT INTO inventory (item_name, category, sub_category, item_type, component_rating, model_number, serial_number, condition_status, image_path, quantity, min_quantity, location, specifications, datasheet_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param(
                    "sssssssssiisss",
                    $item_name,
                    $category,
                    $sub_category,
                    $item_type,
                    $component_rating,
                    $model_number,
                    $serial_number,
                    $condition_status,
                    $image_db_path,
                    $quantity,
                    $min_quantity,
                    $location,
                    $specifications,
                    $datasheet_url
                );

                if ($stmt->execute()) {
                    $new_id = (int)$stmt->insert_id;
                    $message = "Item added successfully!";

                    log_stock_movement(
                        $conn,
                        $new_id,
                        $item_name,
                        (int)($_SESSION['user_id'] ?? 0),
                        $_SESSION['username'] ?? 'System',
                        'item_created',
                        $quantity,
                        0,
                        $quantity,
                        'Initial stock on component creation'
                    );
                } else {
                    $error = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
?>

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Add New Item</h1>
            <p class="text-sm text-slate-400 mt-1">Record a bulk electronic component or a serialized hardware unit</p>
        </div>
        <a href="inventory.php" class="text-slate-400 hover:text-white transition">
            <i class="fa-solid fa-arrow-left"></i> Back to Inventory
        </a>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-900/50 border border-green-500 text-green-200 p-4 rounded mb-6 flex justify-between items-center">
            <span><i class="fa-solid fa-check-circle mr-2"></i> <?php echo htmlspecialchars($message); ?></span>
            <a href="inventory.php" class="text-xs bg-green-700 hover:bg-green-600 text-white px-3 py-1.5 rounded font-semibold transition">View Inventory</a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded mb-6">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-800 p-8 rounded-xl border border-slate-700 shadow-lg">
        <form action="add_item.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

            <!-- Tracking Mode Selector: Bulk Component vs Unique Hardware Unit -->
            <div class="mb-6">
                <label class="block text-slate-400 text-xs uppercase tracking-wider font-bold mb-2">Tracking Type</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label id="label-type-bulk" class="flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition <?php echo $prefill['item_type'] === 'Bulk' ? 'bg-blue-950/40 border-blue-500 text-white' : 'bg-slate-900 border-slate-700 text-slate-300'; ?>">
                        <input type="radio" name="item_type" value="Bulk" class="mt-1" <?php echo $prefill['item_type'] === 'Bulk' ? 'checked' : ''; ?> onchange="updateTrackingMode()">
                        <div>
                            <div class="font-bold text-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-layer-group text-amber-400"></i> Small / Bulk Component
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">Resistors, capacitors, ICs, sensors, screws (distinguished by value/rating like <code class="text-amber-300">4kΩ | 1/2W</code>)</p>
                        </div>
                    </label>
                    <label id="label-type-unique" class="flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition <?php echo $prefill['item_type'] === 'Unique' ? 'bg-blue-950/40 border-blue-500 text-white' : 'bg-slate-900 border-slate-700 text-slate-300'; ?>">
                        <input type="radio" name="item_type" value="Unique" class="mt-1" <?php echo $prefill['item_type'] === 'Unique' ? 'checked' : ''; ?> onchange="updateTrackingMode()">
                        <div>
                            <div class="font-bold text-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-barcode text-cyan-400"></i> Serialized / Large Hardware
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">GPUs, HDDs, RAM, MCUs, multimeters (low qty, tracked by Model # &amp; unique Serial #)</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Category & Guided Sub-Category -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Category *</label>
                    <select id="category-select" name="category" onchange="updateSubCategoryOptions()" class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500 transition">
                        <option value="Electronic" <?php if ($prefill['category'] === 'Electronic') echo 'selected'; ?>>Electronic Component</option>
                        <option value="Computer" <?php if ($prefill['category'] === 'Computer') echo 'selected'; ?>>Computer Part</option>
                        <option value="Tool" <?php if ($prefill['category'] === 'Tool') echo 'selected'; ?>>Tool / Equipment</option>
                        <option value="Other" <?php if ($prefill['category'] === 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Sub-Category</label>
                    <div class="flex gap-2">
                        <select id="subcat-preset" onchange="applyPresetSubCategory()" class="w-1/2 bg-slate-900 border border-slate-600 text-slate-300 text-sm rounded px-3 py-2 focus:outline-none focus:border-blue-500 transition">
                            <option value="">-- Choose Preset --</option>
                        </select>
                        <input id="subcat-input" type="text" name="sub_category" value="<?php echo htmlspecialchars($prefill['sub_category']); ?>" placeholder="Or type custom..."
                            class="w-1/2 bg-slate-900 border border-slate-600 text-white text-sm rounded px-3 py-2 focus:outline-none focus:border-blue-500 transition">
                    </div>
                </div>
            </div>

            <!-- Item Name & Value / Rating / Spec Highlight Badge -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Item Name *</label>
                    <input type="text" name="item_name" value="<?php echo htmlspecialchars($prefill['item_name']); ?>" required placeholder="e.g. Carbon Film Resistor, WD Blue HDD"
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500 transition">
                </div>
                <div>
                    <label class="block text-amber-400 text-sm font-bold mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-tag"></i> Value / Rating / Key Spec Badge
                    </label>
                    <input type="text" name="component_rating" value="<?php echo htmlspecialchars($prefill['component_rating']); ?>"
                        placeholder="e.g. 4kΩ | 1/2W | ±5%  or  16GB | DDR5-5600"
                        class="w-full bg-slate-900 border border-amber-500/60 text-white rounded px-4 py-2 focus:outline-none focus:border-amber-400 transition placeholder-slate-500">
                    <p class="text-[11px] text-slate-400 mt-1">Highlighted prominently in the list so you can spot <span class="text-amber-300">1/2W 4kΩ</span> vs <span class="text-amber-300">1/4W 4kΩ</span> immediately.</p>
                </div>
            </div>

            <!-- Model / Part Number, Serial Number, and Condition -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-slate-900/60 rounded-lg border border-slate-700/80">
                <div>
                    <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-1.5">Model / Part #</label>
                    <input type="text" name="model_number" value="<?php echo htmlspecialchars($prefill['model_number']); ?>" placeholder="e.g. CD4017BE, WD10EZEX"
                        class="w-full bg-slate-900 border border-slate-600 text-white text-sm rounded px-3 py-2 focus:outline-none focus:border-blue-500 font-mono">
                </div>
                <div>
                    <label class="block text-cyan-400 text-xs font-bold uppercase tracking-wider mb-1.5">
                        <i class="fa-solid fa-fingerprint mr-1"></i> Serial Number (S/N)
                    </label>
                    <input type="text" name="serial_number" value="<?php echo htmlspecialchars($prefill['serial_number']); ?>" placeholder="Unique S/N (if applicable)"
                        class="w-full bg-slate-900 border border-slate-600 text-white text-sm rounded px-3 py-2 focus:outline-none focus:border-cyan-400 font-mono">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-1.5">Condition</label>
                    <select name="condition_status" class="w-full bg-slate-900 border border-slate-600 text-white text-sm rounded px-3 py-2 focus:outline-none focus:border-blue-500">
                        <option value="Working" <?php if ($prefill['condition_status'] === 'Working') echo 'selected'; ?>>Working / Tested</option>
                        <option value="New" <?php if ($prefill['condition_status'] === 'New') echo 'selected'; ?>>New / Unused</option>
                        <option value="Untested" <?php if ($prefill['condition_status'] === 'Untested') echo 'selected'; ?>>Untested</option>
                        <option value="For Parts" <?php if ($prefill['condition_status'] === 'For Parts') echo 'selected'; ?>>Defective / For Parts</option>
                    </select>
                </div>
            </div>

            <!-- Quantity, Low-Stock Threshold & Storage Location -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Quantity *</label>
                    <input id="quantity-input" type="number" name="quantity" required value="<?php echo (int)$prefill['quantity']; ?>" min="0"
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500 transition">
                </div>
                <div>
                    <label class="block text-orange-400 text-sm font-bold mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-bell"></i> Low-Stock Alert At
                    </label>
                    <input id="min-quantity-input" type="number" name="min_quantity" value="<?php echo (int)$prefill['min_quantity']; ?>" min="0"
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-orange-400 transition">
                    <p class="text-[11px] text-slate-500 mt-1">Set <code class="text-slate-400">0</code> to disable Low-Stock badge.</p>
                </div>
                <div>
                    <label class="block text-slate-400 text-sm font-bold mb-2">Storage Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($prefill['location']); ?>" placeholder="e.g. Drawer 1 - Bin A, Rack 2"
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500 transition">
                </div>
            </div>

            <!-- Image Upload -->
            <div class="mb-6">
                <label class="block text-slate-400 text-sm font-bold mb-2">Item Image (Optional)</label>
                <div class="flex items-center justify-center w-full">
                    <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-600 border-dashed rounded-lg cursor-pointer bg-slate-900 hover:bg-slate-700 hover:border-blue-500 transition">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400 mb-2"></i>
                            <p class="mb-2 text-sm text-slate-400"><span class="font-semibold">Click to upload</span>, drag & drop, or press <kbd class="px-1.5 py-0.5 bg-slate-800 border border-slate-600 rounded text-xs">Ctrl+V</kbd></p>
                            <p class="text-xs text-slate-500">PNG, JPG or WEBP (MAX. 5MB)</p>
                        </div>
                        <input id="dropzone-file" type="file" name="item_image" class="hidden" accept="image/*" onchange="previewImage(event)" />
                    </label>
                </div>
                <div id="preview-container" class="mt-4 hidden text-center">
                    <p class="text-sm text-slate-400 mb-2">Selected Image:</p>
                    <img id="preview-img" src="#" alt="Preview" class="h-40 mx-auto rounded border border-slate-600 object-cover">
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
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-link text-xs"></i>
                    </div>
                    <input type="url" name="datasheet_url" value="<?php echo htmlspecialchars($prefill['datasheet_url']); ?>"
                        placeholder="https://www.ti.com/lit/ds/...pdf or manufacturer documentation link"
                        class="w-full bg-slate-900 border border-slate-600 text-white rounded pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-blue-500 transition font-mono">
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Direct link to manufacturer PDF, pinout diagram, or forum schematic. A clickable button will be generated automatically.</p>
            </div>

            <div class="mb-6">
                <label class="block text-slate-400 text-sm font-bold mb-2">Additional Specifications / Notes</label>
                <textarea name="specifications" rows="3" placeholder="Pinout notes, tolerance, or test remarks..."
                    class="w-full bg-slate-900 border border-slate-600 text-white rounded px-4 py-2 focus:outline-none focus:border-blue-500 transition"><?php echo htmlspecialchars($prefill['specifications']); ?></textarea>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 px-6 rounded shadow-lg transition transform hover:-translate-y-0.5 cursor-pointer">
                    <i class="fa-solid fa-plus mr-2"></i> Add Item
                </button>
            </div>

        </form>

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
                const minQtyInput = document.getElementById('min-quantity-input');

                if (isUnique) {
                    labelUnique.className = 'flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition bg-blue-950/40 border-blue-500 text-white';
                    labelBulk.className = 'flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition bg-slate-900 border-slate-700 text-slate-300';
                    if (minQtyInput && minQtyInput.value === '5') {
                        minQtyInput.value = '0';
                    }
                } else {
                    labelBulk.className = 'flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition bg-blue-950/40 border-blue-500 text-white';
                    labelUnique.className = 'flex items-start gap-3 p-3.5 rounded-lg border cursor-pointer transition bg-slate-900 border-slate-700 text-slate-300';
                    if (minQtyInput && minQtyInput.value === '0') {
                        minQtyInput.value = '5';
                    }
                }
            }

            // Initialize sub-category dropdown on page load
            updateSubCategoryOptions();

            // 1. Standard "Browse File" Preview
            function previewImage(event) {
                const file = event.target.files[0];
                if (file) {
                    displayPreview(file);
                }
            }

            // 2. The Robust "Ctrl+V" Paste Logic
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

                        displayPreview(newFile);
                    }
                }
            });

            // 3. Helper to display the image
            function displayPreview(file) {
                const previewContainer = document.getElementById('preview-container');
                const previewImg = document.getElementById('preview-img');
                const dropzoneLabel = document.querySelector('label[for="dropzone-file"]');

                previewImg.src = URL.createObjectURL(file);
                previewContainer.classList.remove('hidden');

                if (dropzoneLabel) {
                    dropzoneLabel.classList.add('border-green-500');
                    dropzoneLabel.classList.remove('border-slate-600');
                }
            }
        </script>
    </div>
</div>

<?php require '../includes/footer.php'; ?>