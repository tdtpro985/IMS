# Intern Management System (IMS)

## 1. System Requirements & Basic Setup

**Requirements:**
- XAMPP (PHP 8.0+, MySQL 5.7+ / MariaDB 10.4+)
- Node.js (for running the dev scripts)
- Apache with `mod_rewrite` enabled

### 1.1 Initial Installation
1. **Place files**: Copy the entire project folder into your XAMPP `htdocs` directory (`C:\xampp\htdocs\ims\`).
2. **Import Database**: 
   - Start Apache and MySQL in XAMPP.
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Import `db/ims_schema.sql`.
3. **Configure Database**: Edit `config/db.php` if your credentials differ.
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'tdt_ims');
   ```
4. **Enable mod_rewrite**: In `C:\xampp\apache\conf\httpd.conf`, ensure `LoadModule rewrite_module modules/mod_rewrite.so` is enabled, and `AllowOverride All` is set for the htdocs directory.
5. **Access**: Open `http://localhost/ims/` (Default Login: `admin@tdtpowersteel.com` / `Admin@1234`).

---

## 2. Running the Development Server (`npm run dev`)

We have created an automated Node.js script to run the PHP built-in server and handle secure tunneling.

1. Open a terminal in the `INTERN-MANAGEMENT-SYSTEM` directory.
2. Run `npm install` (first time only) to install the dev dependencies.
3. Run:
   ```bash
   npm run dev
   ```
4. You will be prompted with an interactive menu:
   * **Option 1 (Local Only [Port 8001])**: Spawns the local PHP development server on `http://localhost:8001`. Use this for standard local testing.
   * **Option 2 (Local + ngrok Tunnel)**: Spawns the local PHP server and creates a secure, public **Ngrok** tunnel (`ngrok http 8001`) so the app is accessible over the internet.
   * **Option 3**: Exit.

---

## 3. Remote Access & Ngrok Setup

If you want interns to register their faces from their own homes, or you want to connect the tablet Kiosk from a different Wi-Fi network, you must use **Option 2 (Ngrok Tunnel)**.

### 3.1 Teammate Setup Guide
If you pulled this code from GitHub, you **must sign up for Ngrok** before you can use the tunneling feature.
1. **Sign Up**: Create a free account at [ngrok.com](https://ngrok.com/).
2. **Download**: Download the Ngrok executable and add it to your PATH (or use `npm install -g ngrok`).
3. **Authenticate**: Find your **Authtoken** on the Ngrok dashboard and run:
   ```bash
   ngrok config add-authtoken <your-personal-authtoken>
   ```

### 3.2 Connecting the HRIS Kiosk
1. Start the IMS dev server with Option 2. Ngrok will output a public URL (e.g., `https://1a2b-3c4d.ngrok-free.app`).
2. Open your `HRIS-KIOSK` folder.
3. Edit `HRIS-KIOSK/backend-php/.env` and update `IMS_URL=https://1a2b-3c4d.ngrok-free.app`.
4. Now the Kiosk tablet can connect to your local machine from anywhere in the world!

### 3.3 Troubleshooting Ngrok
If the tunnel fails to create, your Ngrok free account has likely hit its simultaneous session limit (max 3). This happens if you exited the script uncleanly.
**Fix:** Run the following to kill stuck processes:
```powershell
taskkill /f /im ngrok.exe
```

---

## 4. Safe Database Migration (Pulling Updates)

When a teammate makes changes to the database schema (e.g., adding face recognition columns) and you pull from Git, **DO NOT re-import the full `ims_schema.sql` file**, as this will destroy your local intern data.

**Safe Migration Process:**
1. Backup your local database via phpMyAdmin.
2. `git pull origin main` (or the active branch) to get the latest code.
3. Run the safe PHP migration script from the terminal:
   ```bash
   php db/add_face_columns.php
   ```
   *(This script safely adds the new `face_embedding`, `face_embedding_large`, and `qr_code` columns to the `interns` table without touching existing data. It is safe to run multiple times).*
