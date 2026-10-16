<?php
session_start();
require '../includes/db_conn.php';

// Security check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $id = (int)$_POST['id'];

        // 1. Get item details first so we can delete the file from the folder and log the event
        $stmt = $conn->prepare("SELECT item_name, quantity, image_path FROM inventory WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $item_name = $row['item_name'];
            $current_qty = (int)$row['quantity'];
            $image_path = $row['image_path'];
            $stmt->close();

            // 2. Delete the record from Database
            $del_stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
            $del_stmt->bind_param("i", $id);
            if ($del_stmt->execute()) {
                // Log item deletion in audit trail
                log_stock_movement(
                    $conn,
                    null,
                    $item_name,
                    (int)($_SESSION['user_id'] ?? 0),
                    $_SESSION['username'] ?? 'System',
                    'item_deleted',
                    -$current_qty,
                    $current_qty,
                    0,
                    'Component permanently deleted from catalog'
                );

                // 3. If DB delete successful, delete the actual image file safely
                if (!empty($image_path)) {
                    $safe_image = basename($image_path);
                    $file_full_path = "../assets/uploads/" . $safe_image;
                    if (is_file($file_full_path)) {
                        unlink($file_full_path);
                    }
                }
            }
            $del_stmt->close();
        } else {
            $stmt->close();
        }
    }
}

// Redirect back to inventory list
header("Location: inventory.php");
exit();
?>