<?php
session_start();
require '../includes/db_conn.php';

if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

$error = '';
$message = '';

// Check if this is a fresh install with 0 registered users
$user_count_res = $conn->query("SELECT COUNT(*) as cnt FROM users");
$total_users = (int)($user_count_res ? ($user_count_res->fetch_assoc()['cnt'] ?? 0) : 0);
$is_first_run = ($total_users === 0);

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // FIRST-TIME ADMIN SETUP
    if ($is_first_run && isset($_POST['setup_admin'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required to setup your administrator account.";
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]{3,30}$/', $username)) {
            $error = "Username must be 3–30 characters (letters, numbers, _, -, .).";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Admin';
            $ins_stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $ins_stmt->bind_param("sss", $username, $hashed, $role);
            if ($ins_stmt->execute()) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $ins_stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'Admin';
                header("Location: home.php");
                exit();
            } else {
                $error = "Failed to create administrator account: " . $conn->error;
            }
            $ins_stmt->close();
        }
    }

    // REGULAR LOGIN
    else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Prepare SQL to prevent SQL Injection
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password, $role);
            $stmt->fetch();

            // Verify the password
            if (password_verify($password, $hashed_password)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role ?: 'Admin';
                header("Location: home.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_first_run ? 'Initial Setup' : 'Login'; ?> - Lab Inventory</title>
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Favicons & App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="../favicon.ico">
</head>

<body class="bg-slate-900 flex items-center justify-center min-h-screen text-slate-200 p-4">

    <div class="w-full max-w-md p-8 bg-slate-800 rounded-xl shadow-2xl border border-slate-700">

        <?php if ($is_first_run): ?>
            <!-- Initial First-Run Setup Header -->
            <div class="mb-6 text-center">
                <img src="../assets/img/logo.png" alt="Lab-IMS Logo" class="w-16 h-16 rounded-xl shadow-lg mx-auto mb-3 object-contain border border-emerald-500/40 bg-slate-900/50 p-1">
                <h1 class="text-2xl font-bold text-white">Initial System Setup</h1>
                <p class="text-xs text-slate-400 mt-1">Welcome! No accounts exist yet. Create your primary Administrator account to start.</p>
            </div>
        <?php else: ?>
            <!-- Standard Login Header -->
            <div class="mb-6 text-center">
                <img src="../assets/img/logo.png" alt="Lab-IMS Logo" class="w-16 h-16 rounded-xl shadow-lg mx-auto mb-3 object-contain border border-blue-500/30 bg-slate-900/50 p-1">
                <h1 class="text-2xl font-bold text-white tracking-wide">Lab<span class="text-blue-500">Inventory</span></h1>
                <p class="text-xs text-slate-400 mt-1">Electronics &amp; Hardware Management System</p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-5 p-3.5 bg-red-950/60 border border-red-500/70 text-red-200 text-xs rounded-lg flex items-center gap-2.5">
                <i class="fa-solid fa-triangle-exclamation text-red-400"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($is_first_run): ?>
            <!-- Setup Form -->
            <form action="login.php" method="POST" class="space-y-4">
                <input type="hidden" name="setup_admin" value="1">

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Administrator Username</label>
                    <input type="text" name="username" value="admin" required autofocus
                        placeholder="e.g. admin"
                        class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-600 rounded-lg focus:outline-none focus:border-emerald-500 text-white placeholder-slate-500 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Create Password</label>
                    <input type="password" name="password" required minlength="6" autocomplete="new-password"
                        placeholder="At least 6 characters"
                        class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-600 rounded-lg focus:outline-none focus:border-emerald-500 text-white placeholder-slate-500 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password"
                        placeholder="Re-enter password"
                        class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-600 rounded-lg focus:outline-none focus:border-emerald-500 text-white placeholder-slate-500 text-sm">
                </div>

                <button type="submit"
                    class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg transition duration-200 shadow-lg flex items-center justify-center gap-2 cursor-pointer text-sm mt-2">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Create Administrator &amp; Sign In</span>
                </button>
            </form>
        <?php else: ?>
            <!-- Regular Login Form -->
            <form action="login.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Username</label>
                    <input type="text" name="username" required autofocus
                        placeholder="Enter your username"
                        class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-600 rounded-lg focus:outline-none focus:border-blue-500 text-white placeholder-slate-500 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Password</label>
                    <input type="password" name="password" required autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-600 rounded-lg focus:outline-none focus:border-blue-500 text-white placeholder-slate-500 text-sm">
                </div>

                <button type="submit"
                    class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg transition duration-200 shadow-lg flex items-center justify-center gap-2 cursor-pointer text-sm mt-2">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Sign In</span>
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-700/70 text-center text-[11px] text-slate-500">
                Default install login: <code class="text-slate-300 font-mono bg-slate-900 px-1.5 py-0.5 rounded border border-slate-700">admin</code> / <code class="text-slate-300 font-mono bg-slate-900 px-1.5 py-0.5 rounded border border-slate-700">admin123</code>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>