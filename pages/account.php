<?php
require '../includes/db_conn.php';
require '../includes/header.php';

$current_user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = "Invalid session token. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. CHANGE CURRENT USER'S PASSWORD
        if ($action === 'change_password') {
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass     = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';

            if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
                $error = "All password fields are required.";
            } elseif (strlen($new_pass) < 6) {
                $error = "New password must be at least 6 characters long.";
            } elseif ($new_pass !== $confirm_pass) {
                $error = "New password and confirmation do not match.";
            } else {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $current_user_id);
                $stmt->execute();
                $stmt->bind_result($db_hash);
                $stmt->fetch();
                $stmt->close();

                if (!password_verify($current_pass, $db_hash)) {
                    $error = "Current password is incorrect.";
                } else {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $up_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $up_stmt->bind_param("si", $new_hash, $current_user_id);
                    if ($up_stmt->execute()) {
                        $message = "Your password has been changed successfully!";
                    } else {
                        $error = "Failed to update password: " . $conn->error;
                    }
                    $up_stmt->close();
                }
            }
        }

        // 2. CREATE NEW LAB USER / OPERATOR (Admin Only)
        elseif ($action === 'create_user') {
            if (($_SESSION['role'] ?? 'Operator') !== 'Admin') {
                $error = "Administrator privileges are required to create new lab accounts.";
            } else {
                $new_username = trim($_POST['new_username'] ?? '');
                $new_role     = in_array($_POST['new_role'] ?? '', ['Admin', 'Operator'], true) ? $_POST['new_role'] : 'Operator';
                $new_pass     = $_POST['new_user_password'] ?? '';
                $confirm_pass = $_POST['confirm_user_password'] ?? '';

            if (empty($new_username) || empty($new_pass) || empty($confirm_pass)) {
                $error = "All fields are required to create a new user.";
            } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]{3,30}$/', $new_username)) {
                $error = "Username must be 3–30 characters long and contain only letters, numbers, hyphens, dots, or underscores.";
            } elseif (strlen($new_pass) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif ($new_pass !== $confirm_pass) {
                $error = "Passwords do not match.";
            } else {
                // Check if username already exists
                $chk_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $chk_stmt->bind_param("s", $new_username);
                $chk_stmt->execute();
                $chk_stmt->store_result();
                if ($chk_stmt->num_rows > 0) {
                    $error = "Username '" . htmlspecialchars($new_username) . "' is already taken.";
                } else {
                    $chk_stmt->close();
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $ins_stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $ins_stmt->bind_param("sss", $new_username, $new_hash, $new_role);
                    if ($ins_stmt->execute()) {
                        $message = "New user '" . htmlspecialchars($new_username) . "' (" . htmlspecialchars($new_role) . ") created successfully!";
                    } else {
                        $error = "Failed to create user: " . $conn->error;
                    }
                    $ins_stmt->close();
                }
                if (isset($chk_stmt) && $chk_stmt) {
                    @$chk_stmt->close();
                }
            }
        }
    }

        // 3. DELETE LAB USER (Admin Only)
        elseif ($action === 'delete_user') {
            if (($_SESSION['role'] ?? 'Operator') !== 'Admin') {
                $error = "Administrator privileges are required to delete lab accounts.";
            } else {
                $target_id = (int)($_POST['target_user_id'] ?? 0);

                if ($target_id === $current_user_id) {
                    $error = "You cannot delete your own logged-in account.";
                } else {
                    // Check total user count so we don't delete the last user
                    $cnt_res = $conn->query("SELECT COUNT(*) AS total FROM users");
                    $total_users = (int)($cnt_res->fetch_assoc()['total'] ?? 0);

                    if ($total_users <= 1) {
                        $error = "Cannot delete user. At least one administrator account must exist.";
                    } else {
                        $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $del_stmt->bind_param("i", $target_id);
                        if ($del_stmt->execute()) {
                            $message = "User deleted successfully.";
                        } else {
                            $error = "Failed to delete user: " . $conn->error;
                        }
                        $del_stmt->close();
                    }
                }
            }
        }
    }
}

// Fetch current user details
$cur_stmt = $conn->prepare("SELECT username, role, created_at FROM users WHERE id = ?");
$cur_stmt->bind_param("i", $current_user_id);
$cur_stmt->execute();
$current_user = $cur_stmt->get_result()->fetch_assoc();
$cur_stmt->close();

// Fetch all users list
$users_res = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY id ASC");
$all_users = [];
while ($u = $users_res->fetch_assoc()) {
    $all_users[] = $u;
}
?>

<div class="max-w-5xl mx-auto space-y-6">

    <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-user-gear text-blue-500"></i> Account &amp; System Settings
            </h1>
            <p class="text-sm text-slate-400 mt-1">Manage your credentials, lab operator accounts, and system access.</p>
        </div>
        <a href="home.php" class="text-sm text-slate-400 hover:text-white transition flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($message)): ?>
        <div class="p-4 rounded-lg bg-green-900/50 border border-green-500/60 text-green-200 flex items-center gap-3 shadow">
            <i class="fa-solid fa-circle-check text-green-400 text-lg"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-lg bg-red-900/50 border border-red-500/60 text-red-200 flex items-center gap-3 shadow">
            <i class="fa-solid fa-triangle-exclamation text-red-400 text-lg"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Aligned 2x2 Container Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">

        <!-- ROW 1, LEFT: Profile Summary Card -->
        <div class="lg:col-span-5 flex flex-col">
            <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 shadow-lg h-full flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 rounded-full bg-blue-600/20 border border-blue-500/50 flex items-center justify-center text-blue-400 text-2xl font-bold flex-shrink-0">
                            <?php echo strtoupper(substr($current_user['username'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($current_user['username'] ?? 'User'); ?></h2>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded uppercase tracking-wider <?php echo ($current_user['role'] ?? 'Admin') === 'Admin' ? 'bg-purple-900/80 text-purple-200 border border-purple-700/50' : 'bg-slate-700 text-slate-300'; ?>">
                                    <?php echo htmlspecialchars($current_user['role'] ?? 'Admin'); ?>
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">Currently Logged In</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-700/80 pt-4 mt-auto text-xs text-slate-400 space-y-2.5">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">User ID:</span>
                        <span class="font-mono text-slate-200 font-semibold">#<?php echo $current_user_id; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Role Privilege:</span>
                        <span class="text-slate-200 font-medium"><?php echo htmlspecialchars($current_user['role'] ?? 'Admin'); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Account Created:</span>
                        <span class="text-slate-200"><?php echo !empty($current_user['created_at']) ? date("M d, Y", strtotime($current_user['created_at'])) : '—'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 1, RIGHT: Lab System Accounts Table -->
        <div class="lg:col-span-7 flex flex-col">
            <div class="bg-slate-800 rounded-xl border border-slate-700 shadow-lg overflow-hidden h-full flex flex-col justify-between">
                <div>
                    <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center bg-slate-800/80">
                        <div>
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-users text-cyan-400"></i> Lab System Accounts
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">Users who can log in to view and manage inventory</p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-900 text-slate-300 border border-slate-700">
                            <?php echo count($all_users); ?> Account(s)
                        </span>
                    </div>

                    <div class="overflow-x-auto max-h-[170px] overflow-y-auto">
                        <table class="w-full text-left text-sm text-slate-300">
                            <thead class="bg-slate-900/60 text-slate-400 uppercase text-xs sticky top-0">
                                <tr>
                                    <th class="px-5 py-2.5">User</th>
                                    <th class="px-5 py-2.5">Role</th>
                                    <th class="px-5 py-2.5">Created</th>
                                    <th class="px-5 py-2.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                                <?php foreach ($all_users as $u): ?>
                                    <?php $is_self = ((int)$u['id'] === $current_user_id); ?>
                                    <tr class="hover:bg-slate-700/30 transition <?php echo $is_self ? 'bg-blue-950/20' : ''; ?>">
                                        <td class="px-5 py-3 font-medium text-white flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-300 flex-shrink-0">
                                                <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span><?php echo htmlspecialchars($u['username']); ?></span>
                                                    <?php if ($is_self): ?>
                                                        <span class="text-[9px] uppercase font-bold px-1.5 py-0.2 rounded bg-blue-900/80 text-blue-200 border border-blue-700/50">You</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-[10px] text-slate-500 font-mono">ID: #<?php echo (int)$u['id']; ?></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-xs">
                                            <span class="px-2 py-0.5 rounded font-medium <?php echo ($u['role'] ?? 'Admin') === 'Admin' ? 'bg-purple-900/70 text-purple-200 border border-purple-700/40' : 'bg-slate-700 text-slate-300'; ?>">
                                                <?php echo htmlspecialchars($u['role'] ?? 'Admin'); ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-slate-400">
                                            <?php echo !empty($u['created_at']) ? date("M d, Y", strtotime($u['created_at'])) : '—'; ?>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <?php if ($is_self): ?>
                                                <span class="text-xs text-slate-500 italic">Current User</span>
                                            <?php else: ?>
                                                <form method="POST" action="account.php" class="inline" onsubmit="return confirm('Are you sure you want to delete user \'<?php echo htmlspecialchars($u['username']); ?>\'?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="target_user_id" value="<?php echo (int)$u['id']; ?>">
                                                    <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition hover:bg-red-950/40 px-2 py-1 rounded cursor-pointer" title="Delete this user">
                                                        <i class="fa-solid fa-trash mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="px-6 py-2.5 bg-slate-900/40 border-t border-slate-700/70 text-[11px] text-slate-500 flex justify-between items-center">
                    <span>Authorized access to local inventory</span>
                    <span>Multi-user mode active</span>
                </div>
            </div>
        </div>

        <!-- ROW 2, LEFT: Change Password Card -->
        <div class="lg:col-span-5 flex flex-col">
            <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 shadow-lg h-full flex flex-col justify-between">
                <div>
                    <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                        <i class="fa-solid fa-key text-blue-400"></i> Change Password
                    </h3>
                    <p class="text-xs text-slate-400 mb-4">Update your account login credentials</p>

                    <form method="POST" action="account.php" class="space-y-3.5">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label class="block text-slate-400 text-xs font-bold uppercase mb-1">Current Password</label>
                            <input type="password" name="current_password" required autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full bg-slate-900 border border-slate-600 text-white rounded px-3.5 py-2 text-sm focus:outline-none focus:border-blue-500 transition">
                        </div>

                        <div>
                            <label class="block text-slate-400 text-xs font-bold uppercase mb-1">New Password</label>
                            <input type="password" name="new_password" required minlength="6" autocomplete="new-password"
                                placeholder="At least 6 characters"
                                class="w-full bg-slate-900 border border-slate-600 text-white rounded px-3.5 py-2 text-sm focus:outline-none focus:border-blue-500 transition">
                        </div>

                        <div>
                            <label class="block text-slate-400 text-xs font-bold uppercase mb-1">Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password"
                                placeholder="Re-enter new password"
                                class="w-full bg-slate-900 border border-slate-600 text-white rounded px-3.5 py-2 text-sm focus:outline-none focus:border-blue-500 transition">
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 px-4 rounded shadow transition flex items-center justify-center gap-2 cursor-pointer text-sm">
                                <i class="fa-solid fa-shield-halved"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ROW 2, RIGHT: Add New Lab Account Card -->
        <div class="lg:col-span-7 flex flex-col">
            <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 shadow-lg h-full flex flex-col justify-between">
                <div>
                    <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-emerald-400"></i> Add New Lab Account
                    </h3>
                    <p class="text-xs text-slate-400 mb-4">Grant access to another lab member, technician, or assistant</p>

                    <form method="POST" action="account.php" class="space-y-3.5">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="action" value="create_user">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-400 text-xs font-bold uppercase mb-1">Username</label>
                                <input type="text" name="new_username" required placeholder="e.g. tech_john"
                                    class="w-full bg-slate-900 border border-slate-600 text-white rounded px-3.5 py-2 text-sm focus:outline-none focus:border-blue-500 transition">
                                <p class="text-[11px] text-slate-500 mt-1">Letters, numbers, _, -</p>
                            </div>

                            <div>
                                <label class="block text-slate-400 text-xs font-bold uppercase mb-1">Role / Permission</label>
                                <select name="new_role" class="w-full bg-slate-900 border border-slate-600 text-white rounded px-3.5 py-2 text-sm focus:outline-none focus:border-blue-500 transition">
                                    <option value="Operator">Operator (Standard Access)</option>
                                    <option value="Admin">Admin (Full Access)</option>
                                </select>
                                <p class="text-[11px] text-slate-500 mt-1">Access privileges level</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-400 text-xs font-bold uppercase mb-1">Password</label>
                                <input type="password" name="new_user_password" required minlength="6" autocomplete="new-password"
                                    placeholder="At least 6 characters"
                                    class="w-full bg-slate-900 border border-slate-600 text-white rounded px-3.5 py-2 text-sm focus:outline-none focus:border-blue-500 transition">
                                <p class="text-[11px] text-slate-500 mt-1">Min. 6 characters</p>
                            </div>

                            <div>
                                <label class="block text-slate-400 text-xs font-bold uppercase mb-1">Confirm Password</label>
                                <input type="password" name="confirm_user_password" required minlength="6" autocomplete="new-password"
                                    placeholder="Re-enter password"
                                    class="w-full bg-slate-900 border border-slate-600 text-white rounded px-3.5 py-2 text-sm focus:outline-none focus:border-blue-500 transition">
                                <p class="text-[11px] text-slate-500 mt-1">Must match password</p>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 px-4 rounded shadow transition flex items-center justify-center gap-2 cursor-pointer text-sm">
                                <i class="fa-solid fa-user-plus"></i> Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

</div>

<?php require '../includes/footer.php'; ?>
