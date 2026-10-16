<?php
require '../includes/db_conn.php';
require '../includes/header.php';

// Get some real server details to make it look "Pro"
$php_version = phpversion();
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'CLI / Unknown';
$server_os = php_uname('s') . ' ' . php_uname('r'); // e.g., Linux 5.15...
$db_connected = isset($conn) && $conn->ping();
?>

<div class="max-w-5xl mx-auto space-y-8">

    <div class="text-center mb-6">
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">About The System</h1>
        <p class="text-slate-400 text-sm sm:text-base">Electronic Component & Computer Hardware Inventory Management System</p>
    </div>

    <!-- Quick Overview & Live Server Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 sm:p-8 shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-4 mb-4">
                    <img src="../assets/img/logo.png" alt="Lab-IMS Logo" class="w-14 h-14 rounded-xl shadow-md border border-blue-500/30 object-contain">
                    <div>
                        <h2 class="text-xl font-bold text-white">Lab-IMS Project</h2>
                        <p class="text-xs text-slate-400">Purpose &amp; Architecture</p>
                    </div>
                </div>

                <p class="text-slate-300 text-sm leading-relaxed mb-5">
                    Lab-IMS was engineered to solve the "messy lab drawer" problem. It brings unified cataloging, high-contrast rating distinctions, serialized hardware tracking, and printable QR codes to electronics and computer workshops.
                    Built to run lightweight on localhost or inside a <strong>Proxmox Debian LXC</strong> container.
                </p>
            </div>

            <div>
                <h3 class="font-bold text-white mb-2.5 text-xs uppercase tracking-wider text-slate-400">Tech Stack</h3>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-2 p-2 rounded bg-slate-900 border border-slate-700 text-slate-300">
                        <i class="fa-brands fa-php text-purple-400 text-base w-4"></i>
                        <span>PHP 8.2+ Backend</span>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded bg-slate-900 border border-slate-700 text-slate-300">
                        <i class="fa-solid fa-database text-orange-400 text-base w-4"></i>
                        <span>MariaDB / MySQL</span>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded bg-slate-900 border border-slate-700 text-slate-300">
                        <i class="fa-brands fa-css3 text-cyan-400 text-base w-4"></i>
                        <span>Tailwind CSS v4</span>
                    </div>
                    <div class="flex items-center gap-2 p-2 rounded bg-slate-900 border border-slate-700 text-slate-300">
                        <i class="fa-solid fa-server text-emerald-400 text-base w-4"></i>
                        <span>Apache2 / Proxmox</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 sm:p-8 shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-4 mb-4">
                    <div class="bg-emerald-600/20 p-3 rounded-xl text-emerald-400 border border-emerald-500/30">
                        <i class="fa-solid fa-server text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">System Status</h2>
                        <p class="text-xs text-slate-400">Live Environment Metrics</p>
                    </div>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center border-b border-slate-700/80 pb-2.5">
                        <span class="text-slate-400 text-xs">PHP Runtime</span>
                        <span class="font-mono text-white text-xs bg-slate-900 px-2 py-0.5 rounded border border-slate-700">v<?php echo $php_version; ?></span>
                    </div>

                    <div class="flex justify-between items-center border-b border-slate-700/80 pb-2.5">
                        <span class="text-slate-400 text-xs">Database Connection</span>
                        <?php if ($db_connected): ?>
                            <span class="font-mono text-emerald-400 text-xs font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-xs"></i> Connected &amp; Synced
                            </span>
                        <?php else: ?>
                            <span class="font-mono text-red-400 text-xs font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-xmark text-xs"></i> Disconnected
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-between items-center border-b border-slate-700/80 pb-2.5">
                        <span class="text-slate-400 text-xs">Server Host OS</span>
                        <span class="font-mono text-white text-xs truncate max-w-[200px]" title="<?php echo htmlspecialchars($server_os); ?>">
                            <?php echo htmlspecialchars($server_os); ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center pt-0.5">
                        <span class="text-slate-400 text-xs">Server Local Time</span>
                        <span class="font-mono text-white text-xs"><?php echo date("Y-m-d H:i:s T"); ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-700/80">
                <p class="text-[11px] text-slate-400 mb-1 font-medium">Uploaded Assets Directory:</p>
                <code class="block bg-slate-950 text-slate-300 text-[11px] p-2 rounded border border-slate-700 overflow-x-auto no-scrollbar font-mono">
                    <?php echo realpath('../assets/uploads') ?: 'assets/uploads'; ?>
                </code>
            </div>
        </div>

    </div>

    <!-- HOW IT WORKS SECTION -->
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 sm:p-8 shadow-lg">
        <div class="flex items-center gap-3 mb-6 border-b border-slate-700 pb-4">
            <div class="p-2.5 bg-blue-600/20 text-blue-400 rounded-lg">
                <i class="fa-solid fa-gears text-xl"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-white">How It Works</h2>
                <p class="text-xs text-slate-400">Core workflows and lab inventory management lifecycle</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Step 1: Catalog & Distinguish -->
            <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-400 bg-amber-950/60 border border-amber-600/50 px-2 py-0.5 rounded">1. Catalog &amp; Distinguish</span>
                        <i class="fa-solid fa-barcode text-slate-500"></i>
                    </div>
                    <h3 class="font-bold text-white text-base mb-2">Bulk Parts vs. Unique Units</h3>
                    <p class="text-xs text-slate-300 leading-relaxed mb-3">
                        Items are categorized into two primary tracking modes:
                    </p>
                    <ul class="text-xs text-slate-400 space-y-1.5 list-disc list-inside">
                        <li><strong class="text-amber-300">Bulk Components</strong>: Tracked by count with high-contrast electrical ratings (e.g., <code class="text-amber-300 font-mono">10kΩ 1/4W</code>).</li>
                        <li><strong class="text-cyan-300">Serialized Units</strong>: Tracked individually with specific Serial Numbers (<code class="text-cyan-300 font-mono">S/N</code>) and condition states.</li>
                    </ul>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-800 text-[11px] text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-copy text-cyan-400"></i> 1-click "Clone Variant / Unit" feature
                </div>
            </div>

            <!-- Step 2: Storage & QR Scanning -->
            <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-cyan-400 bg-cyan-950/60 border border-cyan-600/50 px-2 py-0.5 rounded">2. Locate &amp; Print QR</span>
                        <i class="fa-solid fa-qrcode text-slate-500"></i>
                    </div>
                    <h3 class="font-bold text-white text-base mb-2">Drawer &amp; Bin QR Labels</h3>
                    <p class="text-xs text-slate-300 leading-relaxed mb-3">
                        Items link directly to physical storage locations (e.g., <code class="text-slate-200">A1-Drawer-04</code>, <code class="text-slate-200">Rack-02</code>):
                    </p>
                    <ul class="text-xs text-slate-400 space-y-1.5 list-disc list-inside">
                        <li><strong class="text-white">Drawer Labels</strong>: Generate printable QR sheets for component organizer drawers.</li>
                        <li><strong class="text-white">Part Tags</strong>: Stick individual QR stickers onto anti-static bags and hardware boxes.</li>
                        <li><strong class="text-white">Mobile Camera Scan</strong>: Scanning opens the drawer's inventory directly on phones or tablets.</li>
                    </ul>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-800 text-[11px] text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-print text-cyan-400"></i> Zero-CDN local QR generator
                </div>
            </div>

            <!-- Step 3: Stock Auditing & Docs -->
            <div class="bg-slate-900/70 border border-slate-700/80 rounded-xl p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 bg-emerald-950/60 border border-emerald-600/50 px-2 py-0.5 rounded">3. Audit &amp; Maintain</span>
                        <i class="fa-solid fa-chart-line text-slate-500"></i>
                    </div>
                    <h3 class="font-bold text-white text-base mb-2">Live Steppers &amp; Alerts</h3>
                    <p class="text-xs text-slate-300 leading-relaxed mb-3">
                        Stock auditing is effortless and responsive:
                    </p>
                    <ul class="text-xs text-slate-400 space-y-1.5 list-disc list-inside">
                        <li><strong class="text-white">AJAX Steppers</strong>: Adjust stock on the fly with <code class="text-blue-400 font-bold">[ + ]</code> and <code class="text-blue-400 font-bold">[ - ]</code> buttons without page reloads.</li>
                        <li><strong class="text-orange-400">Restock Badges</strong>: Automatic Out-of-Stock and Low-Stock flags trigger when quantities drop.</li>
                        <li><strong class="text-red-400">Datasheet Links</strong>: 1-click access to official pinouts and manufacturer manuals.</li>
                    </ul>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-800 text-[11px] text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-file-csv text-emerald-400"></i> 1-click CSV export with UTF-8 BOM
                </div>
            </div>
        </div>
    </div>

    <!-- ARCHITECTURE BLOCK DIAGRAM SECTION -->
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 sm:p-8 shadow-lg">
        <div class="flex items-center gap-3 mb-6 border-b border-slate-700 pb-4">
            <div class="p-2.5 bg-cyan-600/20 text-cyan-400 rounded-lg">
                <i class="fa-solid fa-diagram-project text-xl"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-white">System Architecture Block Diagram</h2>
                <p class="text-xs text-slate-400">Logical tier separation, request lifecycles, and component interactions</p>
            </div>
        </div>

        <!-- The Block Diagram Container -->
        <div class="space-y-4">

            <!-- TIER 1: Presentation & Client Tier -->
            <div class="bg-slate-900/80 border border-blue-500/30 rounded-xl p-4 sm:p-5 relative">
                <div class="flex items-center justify-between mb-3 border-b border-slate-800 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-300">Tier 1: Presentation &amp; Client Devices</span>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400">HTML5 / Responsive Viewport / Tailwind v4</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-slate-950/80 border border-slate-700/80 p-3 rounded-lg text-center">
                        <div class="text-blue-400 text-lg mb-1"><i class="fa-solid fa-desktop"></i></div>
                        <div class="font-bold text-white text-xs">Desktop Workstation</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Admin Management, CSV Export, Full 6-Col Catalog</div>
                    </div>
                    <div class="bg-slate-950/80 border border-slate-700/80 p-3 rounded-lg text-center">
                        <div class="text-cyan-400 text-lg mb-1"><i class="fa-solid fa-mobile-screen-button"></i></div>
                        <div class="font-bold text-white text-xs">Mobile &amp; Tablet</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Camera QR Scanning, Touch Steppers, Mobile Card List</div>
                    </div>
                    <div class="bg-slate-950/80 border border-slate-700/80 p-3 rounded-lg text-center">
                        <div class="text-emerald-400 text-lg mb-1"><i class="fa-solid fa-print"></i></div>
                        <div class="font-bold text-white text-xs">Label Printers</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Drawer Organizers &amp; Component Part Bag Tags</div>
                    </div>
                </div>
            </div>

            <!-- Inter-Tier Flow Connector 1 -->
            <div class="flex items-center justify-center gap-2 text-xs font-mono text-slate-400 py-0.5">
                <i class="fa-solid fa-arrows-up-down text-blue-400 text-xs"></i>
                <span class="bg-slate-900 px-3 py-1 rounded-full border border-slate-700 text-[11px]">HTTP(S) &bull; Asynchronous AJAX Fetch (JSON) &bull; Printable @media CSS</span>
                <i class="fa-solid fa-arrows-up-down text-blue-400 text-xs"></i>
            </div>

            <!-- TIER 2: Application & Logic Tier (PHP + Apache) -->
            <div class="bg-slate-900/80 border border-purple-500/30 rounded-xl p-4 sm:p-5 relative">
                <div class="flex items-center justify-between mb-3 border-b border-slate-800 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-purple-300">Tier 2: Application &amp; Middleware Tier (PHP 8.2+)</span>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400">Apache2 / Nginx &bull; Session Guard</span>
                </div>

                <!-- Sub-Block: Security & Gatekeeper -->
                <div class="bg-purple-950/30 border border-purple-500/40 rounded-lg p-2.5 mb-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span class="font-bold text-purple-200 flex items-center gap-1.5">
                            <i class="fa-solid fa-shield-halved text-purple-400"></i> Security &amp; Gatekeeper Middleware:
                        </span>
                        <div class="flex flex-wrap gap-1.5 text-[11px]">
                            <span class="px-2 py-0.5 rounded bg-slate-900 text-slate-300 border border-slate-700">Session Auth Guard</span>
                            <span class="px-2 py-0.5 rounded bg-slate-900 text-slate-300 border border-slate-700">Role-Based Access (Admin/Operator)</span>
                            <span class="px-2 py-0.5 rounded bg-slate-900 text-slate-300 border border-slate-700">CSRF Token Verification</span>
                            <span class="px-2 py-0.5 rounded bg-slate-900 text-slate-300 border border-slate-700">MIME Type Validator</span>
                        </div>
                    </div>
                </div>

                <!-- Sub-Block: Core Functional Controllers -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 text-center text-xs">
                    <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-700/80">
                        <i class="fa-solid fa-chart-pie text-blue-400 text-sm mb-1"></i>
                        <div class="font-semibold text-white text-[11px]">Dashboard</div>
                        <div class="text-[10px] text-slate-500">home.php</div>
                    </div>
                    <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-700/80">
                        <i class="fa-solid fa-boxes-stacked text-emerald-400 text-sm mb-1"></i>
                        <div class="font-semibold text-white text-[11px]">Parts Catalog</div>
                        <div class="text-[10px] text-slate-500">inventory.php</div>
                    </div>
                    <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-700/80">
                        <i class="fa-solid fa-bolt text-amber-400 text-sm mb-1"></i>
                        <div class="font-semibold text-white text-[11px]">AJAX Stepper</div>
                        <div class="text-[10px] text-slate-500">update_quantity.php</div>
                    </div>
                    <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-700/80">
                        <i class="fa-solid fa-qrcode text-cyan-400 text-sm mb-1"></i>
                        <div class="font-semibold text-white text-[11px]">QR &amp; Export</div>
                        <div class="text-[10px] text-slate-500">print_labels.php</div>
                    </div>
                    <div class="col-span-2 sm:col-span-1 p-2.5 rounded-lg bg-slate-950 border border-slate-700/80">
                        <i class="fa-solid fa-user-gear text-purple-400 text-sm mb-1"></i>
                        <div class="font-semibold text-white text-[11px]">Account / Setup</div>
                        <div class="text-[10px] text-slate-500">account.php / login.php</div>
                    </div>
                </div>
            </div>

            <!-- Inter-Tier Flow Connector 2 -->
            <div class="flex items-center justify-center gap-2 text-xs font-mono text-slate-400 py-0.5">
                <i class="fa-solid fa-arrows-up-down text-purple-400 text-xs"></i>
                <span class="bg-slate-900 px-3 py-1 rounded-full border border-slate-700 text-[11px]">MySQLi Parameterized Prepared Statements &bull; Local Secure File I/O</span>
                <i class="fa-solid fa-arrows-up-down text-purple-400 text-xs"></i>
            </div>

            <!-- TIER 3: Data & Storage Tier -->
            <div class="bg-slate-900/80 border border-emerald-500/30 rounded-xl p-4 sm:p-5 relative">
                <div class="flex items-center justify-between mb-3 border-b border-slate-800 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-300">Tier 3: Data &amp; Storage Persistence Tier</span>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400">MariaDB Relational Tables &bull; Uploads Vault</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-slate-950/80 border border-slate-700/80 p-3.5 rounded-lg">
                        <div class="flex items-center gap-2 font-bold text-white text-xs mb-1.5">
                            <i class="fa-solid fa-database text-orange-400"></i> MariaDB Database (`inventory_db`)
                        </div>
                        <ul class="text-[11px] text-slate-400 space-y-1 list-disc list-inside">
                            <li><strong class="text-slate-200">`inventory` table</strong>: Item names, component ratings, quantities, min_thresholds, serials, datasheets, storage locations</li>
                            <li><strong class="text-slate-200">`users` table</strong>: User credentials, bcrypt/argon2 hashes, roles (`Admin`/`Operator`)</li>
                            <li><strong class="text-slate-200">Auto-Migrations</strong>: Schema self-updates transparently on bootstrap</li>
                        </ul>
                    </div>

                    <div class="bg-slate-950/80 border border-slate-700/80 p-3.5 rounded-lg">
                        <div class="flex items-center gap-2 font-bold text-white text-xs mb-1.5">
                            <i class="fa-solid fa-folder-closed text-cyan-400"></i> Local Storage &amp; Asset Vault
                        </div>
                        <ul class="text-[11px] text-slate-400 space-y-1 list-disc list-inside">
                            <li><strong class="text-slate-200">`assets/uploads/`</strong>: Uploaded component photos protected by `.htaccess` (prohibits PHP script execution)</li>
                            <li><strong class="text-slate-200">`assets/js/qrcode.min.js`</strong>: Local offline QR code engine (zero external CDN dependency)</li>
                            <li><strong class="text-slate-200">`assets/css/styles.css`</strong>: Compiled standalone Tailwind CSS v4 design system</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Inter-Tier Flow Connector 3 -->
            <div class="flex items-center justify-center gap-2 text-xs font-mono text-slate-400 py-0.5">
                <i class="fa-solid fa-arrows-up-down text-emerald-400 text-xs"></i>
                <span class="bg-slate-900 px-3 py-1 rounded-full border border-slate-700 text-[11px]">Virtualization Layer &bull; Linux Container Isolation</span>
                <i class="fa-solid fa-arrows-up-down text-emerald-400 text-xs"></i>
            </div>

            <!-- TIER 4: Host & Infrastructure Tier -->
            <div class="bg-slate-900/80 border border-orange-500/30 rounded-xl p-4 sm:p-5 relative">
                <div class="flex items-center justify-between mb-3 border-b border-slate-800 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-orange-400"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-orange-300">Tier 4: Host &amp; Infrastructure Deployment Tier</span>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400">Proxmox VE / Debian 12 / Baremetal</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center text-xs">
                    <div class="bg-slate-950/80 border border-slate-700/80 p-3 rounded-lg">
                        <div class="text-orange-400 text-base mb-1"><i class="fa-solid fa-network-wired"></i></div>
                        <div class="font-bold text-white text-xs">Proxmox LXC Container</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Debian 12 Bookworm, low-memory footprint (~64MB RAM)</div>
                    </div>
                    <div class="bg-slate-950/80 border border-slate-700/80 p-3 rounded-lg">
                        <div class="text-emerald-400 text-base mb-1"><i class="fa-solid fa-terminal"></i></div>
                        <div class="font-bold text-white text-xs">Automated Deployment</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">`deploy_lxc.sh` single-command provisioning script</div>
                    </div>
                    <div class="bg-slate-950/80 border border-slate-700/80 p-3 rounded-lg">
                        <div class="text-blue-400 text-base mb-1"><i class="fa-solid fa-laptop-code"></i></div>
                        <div class="font-bold text-white text-xs">Local Development</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">PHP built-in server or XAMPP for rapid offline hacking</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- SYSTEM FILE & DIRECTORY STRUCTURE -->
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 sm:p-8 shadow-lg">
        <div class="flex items-center gap-3 mb-6 border-b border-slate-700 pb-4">
            <div class="p-2.5 bg-purple-600/20 text-purple-400 rounded-lg">
                <i class="fa-solid fa-folder-tree text-xl"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-white">System Architecture &amp; Directory Structure</h2>
                <p class="text-xs text-slate-400">File organization and responsibilities across the application</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            <!-- Left: Directory Tree Structure -->
            <div class="md:col-span-5 bg-slate-950 p-4 rounded-xl border border-slate-700/80 font-mono text-xs text-slate-300 overflow-x-auto no-scrollbar">
                <div class="text-blue-400 font-bold mb-2">📁 Lab-IMS /</div>
                <div class="space-y-1.5 pl-3 border-l-2 border-slate-700">
                    <div>├── <span class="text-amber-400 font-bold">includes/</span></div>
                    <div class="pl-4 text-slate-400">
                        <div>├── <span class="text-white">db_conn.php</span> <span class="text-slate-500">// DB, CSRF, security</span></div>
                        <div>├── <span class="text-white">header.php</span> <span class="text-slate-500">// Auth &amp; navigation</span></div>
                        <div>└── <span class="text-white">footer.php</span> <span class="text-slate-500">// Layout closure</span></div>
                    </div>
                    <div>├── <span class="text-emerald-400 font-bold">pages/</span></div>
                    <div class="pl-4 text-slate-400">
                        <div>├── <span class="text-white">home.php</span> <span class="text-slate-500">// Dashboard metrics</span></div>
                        <div>├── <span class="text-white">inventory.php</span> <span class="text-slate-500">// Parts catalog</span></div>
                        <div>├── <span class="text-white">view_item.php</span> <span class="text-slate-500">// Detailed component</span></div>
                        <div>├── <span class="text-white">add_item.php</span> <span class="text-slate-500">// Create &amp; Clone</span></div>
                        <div>├── <span class="text-white">edit_item.php</span> <span class="text-slate-500">// Update item</span></div>
                        <div>├── <span class="text-white">update_quantity.php</span> <span class="text-slate-500">// AJAX stepper</span></div>
                        <div>├── <span class="text-white">print_labels.php</span> <span class="text-slate-500">// QR code print engine</span></div>
                        <div>├── <span class="text-white">export_csv.php</span> <span class="text-slate-500">// CSV export</span></div>
                        <div>├── <span class="text-white">account.php</span> <span class="text-slate-500">// Roles &amp; passwords</span></div>
                        <div>├── <span class="text-white">login.php</span> <span class="text-slate-500">// Auth &amp; setup wizard</span></div>
                        <div>└── <span class="text-white">about.php</span> <span class="text-slate-500">// System info</span></div>
                    </div>
                    <div>├── <span class="text-purple-400 font-bold">assets/</span></div>
                    <div class="pl-4 text-slate-400">
                        <div>├── <span class="text-cyan-300">css/styles.css</span> <span class="text-slate-500">// Tailwind CSS</span></div>
                        <div>├── <span class="text-cyan-300">js/qrcode.min.js</span> <span class="text-slate-500">// QR engine</span></div>
                        <div>└── <span class="text-cyan-300">uploads/</span> <span class="text-slate-500">// Protected images</span></div>
                    </div>
                    <div>├── <span class="text-blue-300 font-bold">inventory_db.sql</span> <span class="text-slate-500">// Schema &amp; defaults</span></div>
                    <div>└── <span class="text-orange-300 font-bold">deploy_lxc.sh</span> <span class="text-slate-500">// Proxmox LXC setup</span></div>
                </div>
            </div>

            <!-- Right: Module Descriptions -->
            <div class="md:col-span-7 space-y-3 text-xs">
                <div class="p-3 bg-slate-900/60 rounded-lg border border-slate-700/70">
                    <div class="font-bold text-white flex items-center gap-2 mb-1">
                        <i class="fa-solid fa-shield-halved text-blue-400"></i>
                        <span>Security &amp; Core Engine (`includes/db_conn.php`)</span>
                    </div>
                    <p class="text-slate-400 leading-relaxed">
                        Manages MariaDB database connectivity, transparent schema auto-migrations (`component_rating`, `item_type`, `datasheet_url`, `role`), parameterized prepared statements, CSRF tokens, and URL sanitization.
                    </p>
                </div>

                <div class="p-3 bg-slate-900/60 rounded-lg border border-slate-700/70">
                    <div class="font-bold text-white flex items-center gap-2 mb-1">
                        <i class="fa-solid fa-table-list text-emerald-400"></i>
                        <span>Parts Catalog &amp; Multi-Filtering (`pages/inventory.php`)</span>
                    </div>
                    <p class="text-slate-400 leading-relaxed">
                        Supports List and Grid layouts, search across 7 fields, category &amp; subcategory filters, location filters, stock alert filters, interactive sorting, and responsive pagination.
                    </p>
                </div>

                <div class="p-3 bg-slate-900/60 rounded-lg border border-slate-700/70">
                    <div class="font-bold text-white flex items-center gap-2 mb-1">
                        <i class="fa-solid fa-qrcode text-cyan-400"></i>
                        <span>Labels &amp; Physical Organization (`pages/print_labels.php`)</span>
                    </div>
                    <p class="text-slate-400 leading-relaxed">
                        Renders printable high-density QR code tags for both storage bins/drawers and individual serialized parts, featuring custom LAN base URL overrides and print media styling.
                    </p>
                </div>

                <div class="p-3 bg-slate-900/60 rounded-lg border border-slate-700/70">
                    <div class="font-bold text-white flex items-center gap-2 mb-1">
                        <i class="fa-solid fa-user-lock text-purple-400"></i>
                        <span>Access Control &amp; Setup (`pages/account.php` &amp; `pages/login.php`)</span>
                    </div>
                    <p class="text-slate-400 leading-relaxed">
                        Offers self-service password changes, user management with role separation (<span class="text-blue-300 font-semibold">Admin</span> &amp; <span class="text-slate-300 font-semibold">Operator</span>), and automatic detection of fresh database installs to trigger the Initial Setup Wizard.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Credit Card -->
    <div class="bg-gradient-to-r from-blue-900/80 via-slate-800 to-slate-850 rounded-xl border border-blue-700/40 p-6 flex flex-col md:flex-row items-center justify-between shadow-lg">
        <div class="flex items-center gap-4 mb-4 md:mb-0">
            <img src="../assets/img/logo.png" alt="Lab-IMS Logo" class="h-12 w-12 rounded-xl shadow-md border border-blue-400/40 object-contain">
            <div>
                <h3 class="font-bold text-white text-base sm:text-lg">Lab Inventory Management System (Lab-IMS)</h3>
                <p class="text-blue-200 text-xs">Self-Hosted Electronics &amp; Hardware Management</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="inventory.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow transition flex items-center gap-1.5">
                <i class="fa-solid fa-boxes-stacked"></i> Browse Catalog
            </a>
            <a href="account.php" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 text-xs font-medium rounded-lg shadow transition flex items-center gap-1.5">
                <i class="fa-solid fa-user-gear"></i> Settings
            </a>
        </div>
    </div>

</div>

<?php require '../includes/footer.php'; ?>