# Local Server Deployment Guide using Webmin

This document guides you through deploying the **TDT Powersteel Intern Management System (IMS)** and its background **Python ONNX Face Embedding Service** on a local Linux server managed by the **Webmin** control panel.

---

## Prerequisites
* **Webmin** installed and accessible.
* **Apache** or **Nginx** web server installed.
* **MySQL** or **MariaDB** database server installed.
* **PHP 8.2+** with `mysqli`, `curl`, and `json` extensions.
* **Python 3.9+** with `pip` and `virtualenv`.

---

## Step 1: Host the PHP Application in Apache

1. **Log in to Webmin** on your server.
2. Navigate to **Servers** > **Apache Webserver**.
3. Under the **Create Virtual Host** tab, configure:
   * **Handle connections on port:** `80` (and `443` for SSL).
   * **Document Root:** Path to your `INTERN-MANAGEMENT-SYSTEM` folder (e.g., `/var/www/html/INTERN-MANAGEMENT-SYSTEM`).
   * **Server Name:** Your local domain (e.g., `ims.local` or the server's local IP address).
4. Click **Create Now**.
5. Click **Apply Changes** (top-right of the Apache Webserver module) to restart Apache.
6. **Enable URL Rewriting (`mod_rewrite`)**:
   * Navigate to **Apache Webserver** > **Global Configuration** > **Configure Apache Modules**.
   * Locate **rewrite** in the list, check it, and click **Enable Selected Modules**.
   * Ensure that the Directory block for your document root allows overrides by setting `AllowOverride All` in your virtual host configuration (this allows the `.htaccess` file to rewrite `/register` to `/register_face.php` successfully).

---

## Step 2: Set Up the MySQL Database

1. Navigate to **Servers** > **MySQL Database Server**.
2. Click **Create a new database**:
   * **Database name:** `tdt_ims` (or your preferred database name).
   * **Character set:** `utf8mb4`.
3. Click **Create**.
4. Import the SQL Schema:
   * Inside your new database view, click **Execute SQL**.
   * Under **From uploaded file**, upload and execute the schema file: [ims_schema.sql](file:///C:/Users/Keith/HRIS/INTERN-MANAGEMENT-SYSTEM/db/ims_schema.sql).
5. Configure database credentials inside the application:
   * Edit [config/db.php](file:///C:/Users/Keith/HRIS/INTERN-MANAGEMENT-SYSTEM/config/db.php) and update the server, username, password, and database variables to match your MySQL server config.

---

## Step 3: Set Up the Python ONNX Face Service

The face embedding extraction runs as a background Python Flask service (port `5001`).

### A. Install dependencies on the server terminal:
```bash
cd /opt
sudo git clone <your-repository-url> hris-kiosk-backend
cd hris-kiosk-backend/face_server
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### B. Configure as a systemd Daemon Service in Webmin:
1. Navigate to **System** > **Bootup and Shutdown** in Webmin.
2. Click **Create a new systemd service**.
3. Configure the service parameters:
   * **Service Name:** `ims-face-service`
   * **Description:** TDT IMS Python ONNX Face Embedding Service
   * **ExecStart:** Path to the Python virtual environment and the Flask app. E.g.:
     ```bash
     /opt/hris-kiosk-backend/face_server/venv/bin/python /opt/hris-kiosk-backend/face_server/app.py
     ```
   * **WorkingDirectory:** `/opt/hris-kiosk-backend/face_server`
   * **Restart:** `always`
4. Click **Create**.
5. Locate the newly created `ims-face-service` service, check it, and click **Start Now** and **Enable on Boot**.

---

## Step 4: Configure SSL Certificate (HTTPS)

Modern mobile web browsers strictly block camera access (`navigator.mediaDevices.getUserMedia`) on unencrypted HTTP connections. Setting up SSL is required for face registration.

1. Navigate to **Webmin** > **Webmin Configuration** > **SSL Encryption**.
2. Go to the **Let's Encrypt** tab.
3. Configure the following:
   * **Hostnames for certificate:** Enter your server's public domain name.
   * **Website root directory for validation:** Point to your Apache document root (e.g. `/var/www/html/INTERN-MANAGEMENT-SYSTEM`).
4. Click **Request Certificate**.
5. Once obtained, configure Apache to use this SSL certificate for your Port `443` Virtual Host.

---

## Step 5: Deploy the HRIS Kiosk PHP Backend

1. **Virtual Host Configuration**:
   - Create a virtual host in Apache for the Kiosk backend (Webmin -> **Servers** > **Apache Webserver**).
   - Set the **Document Root** to the public subdirectory of your Kiosk backend folder (e.g. `/var/www/html/HRIS-KIOSK/backend-php/public`).
2. **Environment Variable Configuration**:
   - Create or edit the `.env` configuration file inside `/var/www/html/HRIS-KIOSK/backend-php/.env`.
   - Set the directory mode and database connection variables:
     ```ini
     # Switch Kiosk database target: 'employee' (Supabase) or 'intern' (Local MySQL)
     KIOSK_MODE=intern
     
     # Local IMS MySQL Credentials
     IMS_DB_HOST=localhost
     IMS_DB_USER=root
     IMS_DB_PASS=
     IMS_DB_NAME=tdt_ims
     
     # Optional: Explicit override for IMS backend URL (highly recommended if virtual hosts run on separate domains)
     # E.g., if IMS is hosted at ims.local and Kiosk is at kiosk.local
     IMS_URL=http://ims.local
     ```
   - *Note: If `IMS_URL` is omitted, the backend dynamically resolves custom development ports (redirecting internally to port `8002` during local testing) and falls back to the `/ims` subdirectory in standard Apache deployments.*
