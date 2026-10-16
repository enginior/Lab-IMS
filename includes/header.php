<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Inventory</title>
    <?php
    // Get the modification time of the CSS file
    $cssFile = __DIR__ . '/../assets/css/styles.css';
    $version = file_exists($cssFile) ? filemtime($cssFile) : time();
    ?>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Favicons & App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="../favicon.ico">
</head>

<body class="bg-slate-900 text-slate-200 font-sans antialiased min-h-screen flex flex-col">

    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
    <nav class="bg-slate-800 border-b border-slate-700 shadow-lg relative">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">

                <a href="home.php" class="flex items-center gap-2.5 hover:opacity-90 transition group">
                    <img src="../assets/img/logo.png" alt="Lab-IMS Logo" class="w-8 h-8 rounded-lg shadow-sm object-contain border border-blue-500/30 group-hover:border-blue-400/60 transition">
                    <span class="text-xl font-bold tracking-wide text-white">Lab<span class="text-blue-500">Inventory</span></span>
                </a>

                <div class="hidden md:flex space-x-4">
                    <a href="home.php" class="<?php echo $current_page === 'home.php' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-sm font-medium transition">Dashboard</a>
                    <a href="inventory.php" class="<?php echo in_array($current_page, ['inventory.php', 'add_item.php', 'edit_item.php', 'view_item.php']) ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-sm font-medium transition">Parts List</a>
                    <a href="stock_log.php" class="<?php echo $current_page === 'stock_log.php' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-sm font-medium transition flex items-center gap-1.5"><i class="fa-solid fa-clock-rotate-left text-xs text-blue-400"></i> Audit Log</a>
                    <a href="about.php" class="<?php echo $current_page === 'about.php' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-sm font-medium transition">About</a>
                </div>

                <div class="hidden md:flex items-center gap-3">
                    <a href="account.php" class="<?php echo $current_page === 'account.php' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-2.5 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5" title="Account &amp; User Settings">
                        <i class="fa-solid fa-user-gear text-slate-400"></i>
                        <span>Settings</span>
                    </a>
                    <span class="text-xs text-slate-400 font-medium">Hi, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded shadow transition">Logout</a>
                </div>

                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" class="text-slate-300 hover:text-white focus:outline-none">
                        <i class="fa-solid fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden bg-slate-800 border-t border-slate-700 absolute w-full left-0 z-50 shadow-xl">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="home.php" class="block <?php echo $current_page === 'home.php' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
                <a href="inventory.php" class="block <?php echo in_array($current_page, ['inventory.php', 'add_item.php', 'edit_item.php', 'view_item.php']) ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-base font-medium">Parts List</a>
                <a href="stock_log.php" class="block <?php echo $current_page === 'stock_log.php' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-base font-medium flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-blue-400"></i> Stock Audit Log
                </a>
                <a href="about.php" class="block <?php echo $current_page === 'about.php' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-base font-medium">About</a>
                <a href="account.php" class="block <?php echo $current_page === 'account.php' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?> px-3 py-2 rounded-md text-base font-medium flex items-center gap-2">
                    <i class="fa-solid fa-user-gear"></i> Settings / Accounts
                </a>

                <div class="border-t border-slate-700 my-2"></div>

                <div class="px-3 py-2 text-sm text-slate-400">Hi, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                <a href="logout.php" class="block text-red-400 hover:text-red-300 hover:bg-slate-700 px-3 py-2 rounded-md text-base font-medium">Logout</a>
            </div>
        </div>
    </nav>

    <script>
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    </script>

    <main class="container mx-auto px-4 py-8 flex-grow">