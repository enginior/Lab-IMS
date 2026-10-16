# Lab-IMS (Laboratory Inventory Management System)

## Project Overview

**Lab-IMS** is a self-hosted web application built to solve the "messy drawer" problem in an electronics and computer hardware laboratory. It tracks electronic components (resistors, capacitors, ICs, microcontrollers), computer parts (GPUs, RAM, storage drives), tools, and miscellaneous lab equipment in a unified, responsive dark-mode interface.

The project is built on the **LAMP stack** (Linux, Apache, MySQL/MariaDB, PHP 8+) with **Tailwind CSS v4** and **Chart.js**, and is specifically designed to run with minimal overhead inside a **Proxmox Debian LXC container**.

---

## Core Features

- **Dashboard (`pages/home.php`):**
  - Displays total asset count, an interactive Doughnut chart (Chart.js) breaking down items by category, and a table of the 5 most recently added items.
- **Parts Inventory (`pages/inventory.php`):**
  - Searchable and filterable catalog (searches across item name, sub-category, storage location, and specifications).
  - Switchable **List View** and **Grid View** (persisted in `localStorage`).
  - **Quick `+` / `-` Quantity Controls** directly in both list and grid views via AJAX (`pages/update_quantity.php`).
  - Paginated results (12 items per page).
- **Item Details (`pages/view_item.php`):**
  - Full item overview with zoomable image preview, storage location, date added, specifications/notes, and inline `+` / `-` quantity adjustment buttons.
- **Add & Edit Items (`pages/add_item.php`, `pages/edit_item.php`):**
  - Supports standard file browsing, drag-and-drop, and **`Ctrl+V` clipboard image pasting** with instant live preview.
  - Automatic cleanup of old image files when replacing an item's photo or deleting an item.
- **System Status (`pages/about.php`):**
  - Displays live server metrics (PHP version, live MariaDB ping check, OS/kernel version, and upload directory path).
- **Authentication (`pages/login.php`, `pages/logout.php`):**
  - Session-based authentication using `password_hash()` / `password_verify()` (bcrypt).

---

## Project Structure

```text
Lab-IMS/
├── assets/
│   ├── css/
│   │   └── styles.css           # Compiled & minified Tailwind CSS v4 stylesheet
│   └── uploads/                 # Item images + .htaccess execution protection
├── includes/
│   ├── config.example.php       # Template for local/server DB credentials
│   ├── db_conn.php              # DB connection, CSRF helpers, and image upload validator
│   ├── header.php               # Auth check, responsive navbar, active page state
│   └── footer.php               # Common footer
├── pages/
│   ├── about.php                # System info & live server status
│   ├── add_item.php             # Add new inventory item (with Ctrl+V image paste)
│   ├── delete_item.php          # POST-only item & image deletion handler
│   ├── edit_item.php            # Edit item details/image & delete trigger
│   ├── home.php                 # Dashboard & Chart.js visualization
│   ├── inventory.php            # Search, category filter, List/Grid views, pagination
│   ├── login.php                # User authentication
│   ├── logout.php               # Session destruction
│   ├── update_quantity.php      # JSON AJAX endpoint for + / - quantity buttons
│   └── view_item.php            # Detailed item view
├── src/
│   └── input.css                # Tailwind CSS v4 source & @theme custom tokens
├── index.php                    # Gatekeeper redirector
├── inventory_db.sql             # MariaDB / MySQL schema & seed data
└── .gitignore                   # Excludes local configs, Adminer, and CLI binaries
```

---

## Changelog: Bugs Fixed & Updates Applied

### 1. UI & Theme Fixes
- **Restored Missing Tailwind v4 Theme Tokens (`src/input.css`, `assets/css/styles.css`):**
  - `pages/home.php` used custom theme classes (`bg-app-card`, `border-app-border`, `text-app-text`, `text-app-muted`, `bg-app-bg`, `hover:bg-brand-hover`) that were missing from `src/input.css`. Defined these tokens in `@theme` using the unified Slate + Blue palette and recompiled `assets/css/styles.css`.
  - Fixed button text contrast on the Dashboard's "View All Items" button (`text-white` on `bg-brand`) and added a **Qty** column to the "Recently Added" table.
- **Navigation Active State (`includes/header.php`):**
  - Highlighted the active navigation link (`Dashboard`, `Parts List`, `About`) in both desktop and mobile menus, and linked the `LabInventory` logo back to `home.php`.

### 2. Bug Fixes
- **Zero-Quantity Validation Bug (`pages/add_item.php`, `pages/edit_item.php`):**
  - Previously, `empty($quantity)` rejected items with a quantity of `0` even though `min="0"` was allowed. Updated validation to explicitly allow `0` (out-of-stock items) while rejecting negative or non-numeric values.
- **Missing "Other" Category in Filter (`pages/inventory.php`):**
  - Added the `Other` category option to the inventory filter dropdown so items categorized as `Other` can be filtered.
- **Expanded Search Scope (`pages/inventory.php`):**
  - Included `location` in the search query alongside `item_name`, `sub_category`, and `specifications` so searching for e.g. `Drawer 2` or `Rack 1` returns all items in that location.
- **Edit Page Error Display & `Ctrl+V` Image Paste (`pages/edit_item.php`):**
  - Added the missing `$error` alert banner in `pages/edit_item.php`.
  - Brought the `Ctrl+V` clipboard image paste and live preview functionality from `add_item.php` over to `edit_item.php`.
- **Live Database Check (`pages/about.php`):**
  - Connected `pages/about.php` to `includes/db_conn.php` and used `$conn->ping()` so the "Database: Connected" badge reflects actual database status.
- **Gatekeeper Redirect (`index.php`):**
  - Fixed the `else` branch in `index.php` to redirect unauthenticated visitors directly to `pages/login.php`.

### 3. Security & Code Hardening
- **Eliminated Arbitrary File Deletion / Path Traversal (`pages/edit_item.php`, `pages/delete_item.php`):**
  - Removed reliance on the user-controlled `$_POST['current_image']` hidden field when deleting old images. Old filenames are now fetched directly from the database record and sanitized with `basename()`.
- **Converted Item Deletion to POST + CSRF Protection (`pages/delete_item.php`, `pages/edit_item.php`, `includes/db_conn.php`):**
  - Added session CSRF token generation and verification (`csrf_token()`, `verify_csrf_token()`).
  - Converted item deletion from a `GET` link to a `POST` form submission protected by a CSRF token.
- **Prepared Statements Everywhere (`pages/inventory.php`, `pages/edit_item.php`, `pages/view_item.php`, `pages/delete_item.php`):**
  - Replaced all remaining raw SQL string interpolations with parameterized MySQLi prepared statements.
- **Strict Image Upload Validation (`includes/db_conn.php`):**
  - Created `handle_item_image_upload()` which verifies actual file MIME types via PHP's `finfo_file()` (`image/jpeg`, `image/png`, `image/webp`) and enforces a 5MB file size limit.
  - Added `assets/uploads/.htaccess` to disable directory listing and block PHP script execution inside the uploads folder.
- **Authentication Hardening (`pages/login.php`):**
  - Added `session_regenerate_id(true)` upon login to prevent session fixation, unified login error messages to prevent username enumeration, and stopped trimming passwords.
- **Repository Cleanup & Configurable DB Credentials (`includes/db_conn.php`, `includes/config.example.php`, `.gitignore`):**
  - Removed `adminer.php` from the public web root.
  - Added `.gitignore` to ignore `tailwindcss.exe`, `adminer.php`, and local config files.
  - Updated `includes/db_conn.php` to support environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) or `includes/config.local.php`.

### 4. New Features Added
- **Quick `+` / `-` Quantity Controls (`pages/update_quantity.php`, `pages/inventory.php`, `pages/view_item.php`):**
  - Added inline `+` and `-` buttons on the Inventory List View, Grid View, and Item Detail View so stock counts can be incremented or decremented immediately without opening the Edit page.
- **High-Contrast Component Value / Rating Distinction (`component_rating`):**
  - Added a dedicated **Value / Rating / Key Spec Badge** (rendered as a high-contrast Amber badge with a lightning icon on cards, tables, and item views) so small components with similar names—such as a `4kΩ | 1/2W` resistor vs a `4kΩ | 1/4W` resistor—can be distinguished at a glance.
- **Bulk vs. Serialized Hardware Tracking (`item_type`, `model_number`, `serial_number`, `condition_status`):**
  - Added a **Tracking Type** selector (`Bulk Component` vs `Serialized / Large Hardware Unit`).
  - Added **Model / Part # (`M/N`)**, **Unique Serial Number (`S/N`, highlighted in Cyan)**, and **Condition Status** (`New`, `Working`, `Untested`, `For Parts`) for computer parts and lab equipment.
  - Added a **"Clone / Add Another Variant or Unit"** button (`fa-copy`) on every item row and detail page (`add_item.php?clone_id=...`) so you can rapidly add a second `1/4W` resistor variant or a second identical GPU/HDD with a different Serial Number.
  - Added a **"Related Variants / Serialized Units"** comparison table at the bottom of `pages/view_item.php` that automatically groups and lists all other items sharing the same name or Model Number.
- **Guided Category & Sub-Category Organization (`includes/db_conn.php`, `pages/inventory.php`):**
  - Organized Categories (`Electronic`, `Computer`, `Tool`, `Other`) with dynamic **Sub-Category Presets** (e.g., `Passive - Resistor`, `Passive - Capacitor`, `IC / Logic / Op-Amp`, `Microcontroller / Dev Board`, `CPU / Processor`, `Storage - SSD / NVMe`, `Storage - HDD`, `Memory / RAM`, `Measurement / Multimeter`, etc.) while still allowing custom sub-categories.
  - Added **Sub-Category quick-filter pills** with item counts and a **Tracking Type filter** (`Bulk` vs `Serialized Units`) at the top of `pages/inventory.php`.
  - Added automatic schema migration (`ensure_inventory_schema()` in `includes/db_conn.php`) so existing databases automatically upgrade without manual SQL intervention.
- **Low-Stock & Out-of-Stock Badges & Alerts (`min_quantity`):**
  - Added a configurable **Low-Stock Alert Threshold** (`min_quantity`) per item (defaults to `5` for Bulk components and `0` for unique hardware units).
  - Displays high-visibility **Low Stock** (Orange) and **Out of Stock** (Red) badges across the Inventory List View, Grid View, Item Detail View, and Dashboard, updating live via AJAX when clicking the `+` / `-` quantity buttons.
  - Added a **Stock Level Filter** (`Needs Restock`, `Low Stock Only`, `Out of Stock`) on `pages/inventory.php` and clickable Low-Stock / Out-of-Stock KPI pills on the Dashboard (`pages/home.php`).
- **Location Filter & Sorting Controls (`pages/inventory.php`, `pages/view_item.php`):**
  - Added a dynamic **Storage Location (`📍 All Locations`)** filter dropdown populated from existing lab locations with live item counts (plus an `Unassigned` option).
  - Added an interactive **Sort By** toolbar supporting `Category & Name`, `Name (A–Z)`, `Qty (Low → High)`, `Qty (High → Low)`, `Location`, `Recently Added`, and `Last Updated`.
  - Made storage locations clickable across the List View, Grid View, and Item Detail View (`?loc=...`).
- **Datasheet Links & External Reference Documentation (`datasheet_url`):**
  - Added a dedicated `datasheet_url` field to store direct links to PDF datasheets, pinout diagrams, schematic references, or hardware manuals.
  - Automatically sanitizes and ensures standard `https://` formatting via `sanitize_external_url()`.
  - Displays a high-contrast **Datasheet / Manual** button in the item detail header and an interactive documentation callout card in the specifications section on `pages/view_item.php`.
  - Displays direct 1-click PDF icon links in the Inventory List View actions column and Grid View card footer on `pages/inventory.php`.
- **Account & Password Management UI (`pages/account.php`, `includes/header.php`):**
  - Added a dedicated **Settings / Accounts** page accessible directly from the navbar.
  - Allows the logged-in administrator or operator to securely change their password (with current password verification, 6-character minimum, and confirmation matching).
  - Added a **Lab System Accounts** management table displaying all registered users, roles (`Admin` or `Operator`), and creation dates.
  - Added an **"Add New Lab Account"** form to quickly provision new technician/assistant credentials without needing phpMyAdmin or CLI hash generation.
  - Protected against self-deletion and accidental deletion of the last remaining administrator account.
- **CSV Export & Printable Drawer QR Labels (`pages/export_csv.php`, `pages/print_labels.php`):**
  - Added **1-click CSV export** that respects your currently applied search keyword, category, sub-category, location, tracking type, stock status alert, and sort order. Includes UTF-8 BOM encoding for seamless Microsoft Excel compatibility.
  - Added a **Printable QR Label Generator** with two modes:
    - **Storage Drawers & Bins:** Generates printable sticker labels for component organizers (Akro-Mils, Raaco, Stanley drawers) featuring bold drawer titles, a parts summary list, item counts, and a scannable QR code linking straight to `inventory.php?loc=...`.
    - **Individual Component Tags:** Generates compact part tags with part numbers, serial numbers, rating badges, location, and a QR code linking to `view_item.php?id=...`.
  - Bundled a standalone, offline QR code generator (`assets/js/qrcode.min.js`) so label printing works 100% offline inside air-gapped lab servers.
  - Supports configurable LAN base URLs for lab smartphone scanning, customizable print sizes (Compact Bin, Standard Drawer, Detailed Shelf), and high-contrast `@media print` styling for standard sticker paper.

---

## Deploying to Proxmox Debian LXC

1. **Install LAMP Packages inside your Debian LXC container:**
   ```bash
   sudo apt update && sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-fileinfo
   ```
2. **Create the Database & User in MariaDB:**
   ```bash
   sudo mariadb -e "CREATE DATABASE inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
   sudo mariadb -e "CREATE USER 'lab_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';"
   sudo mariadb -e "GRANT ALL PRIVILEGES ON inventory_db.* TO 'lab_user'@'localhost'; FLUSH PRIVILEGES;"
   sudo mariadb inventory_db < /var/www/html/inventory_db.sql
   ```
3. **Configure Credentials & Permissions:**
   - Copy `includes/config.example.php` to `includes/config.local.php` and set your MariaDB credentials.
   - Ensure Apache owns the uploads directory:
     ```bash
     sudo chown -R www-data:www-data /var/www/html/assets/uploads
     sudo chmod -R 755 /var/www/html/assets/uploads
     ```
4. **Rebuilding Tailwind CSS on Linux (if you edit styles later):**
   - Download the Linux standalone Tailwind v4 binary (`tailwindcss-linux-x64`) and run:
     ```bash
     ./tailwindcss-linux-x64 -i ./src/input.css -o ./assets/css/styles.css --minify
     ```

---

## Recommended Next Improvements

When you are ready to expand the system further, here are the highest-value enhancements for an electronics and PC hardware lab:

1. **Location Filter & Sorting Controls:**
   - Add a dynamic "Location" dropdown filter (populated from `SELECT DISTINCT location FROM inventory`) and a "Sort By" selector (Name, Quantity Low-to-High, Recently Added) on `pages/inventory.php`.
2. **Low-Stock Thresholds & Out-of-Stock Highlighting:**
   - Add a `min_quantity` column to `inventory` (e.g., alert when 10kΩ resistors drop below 10, while a GPU has a threshold of 0). Highlight low/zero-stock items in amber/red on the dashboard and inventory table.
3. **Datasheet Links, Package Type & Condition Status:**
   - Add optional fields for `datasheet_url` (helpful for ICs/MOSFETs/sensors), `package_type` (e.g., `DIP-8`, `TO-220`, `SMD 0805`, `M.2`), and `condition_status` (`Working`, `New`, `Untested`, `For Parts`).
4. **Account / Password Management Page:**
   - Add a simple "Change Password" page in the navbar so you can change the default `admin` password directly from the web UI without manually generating bcrypt hashes.
5. **CSV Export & QR Drawer Labels:**
   - Export inventory to CSV for backups, and generate printable QR code labels for storage drawers (`Drawer 1`, `Rack A`) that link directly to `inventory.php?search=Drawer+1`.

---

## License

This project is open-source and licensed under the [MIT License](LICENSE).
