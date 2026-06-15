# TDT Powersteel IMS — Setup Guide

## Requirements
- XAMPP (PHP 8.0+, MySQL 5.7+ / MariaDB 10.4+)
- Apache with mod_rewrite enabled

## Steps

### 1. Place files
Copy the entire project folder into your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\ims\
```

### 2. Import the database
1. Start XAMPP — start **Apache** and **MySQL**
2. Open **phpMyAdmin**: http://localhost/phpmyadmin
3. Click **Import** → choose `db/ims_schema.sql`
4. Click **Go**

### 3. Configure database (if needed)
Edit `config/db.php` if your MySQL credentials differ:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');        // your MySQL password
define('DB_NAME', 'tdt_ims');
```

### 4. Add logo images
Place these two files in `assets/img/`:
- `logo-dark.png`  — dark background version (for sidebar)
- `logo-light.png` — white background version (for login page)

### 5. Enable mod_rewrite (for .htaccess)
In `C:\xampp\apache\conf\httpd.conf`, ensure:
```
LoadModule rewrite_module modules/mod_rewrite.so
```
And in the `<Directory "C:/xampp/htdocs">` block:
```
AllowOverride All
```

### 6. Access the system
Open: http://localhost/ims/

**Default Admin Login:**
- Email:    `admin@tdtpowersteel.com`
- Password: `Admin@1234`

> Change the password immediately after first login via Settings.

## Folder Structure
```
ims/
├── api/                  Export endpoints (DTR, Requirements, Interns)
├── assets/
│   ├── css/              main.css, login.css
│   ├── img/              logo-dark.png, logo-light.png
│   └── js/               main.js
├── config/               db.php, session.php, audit.php
├── db/                   ims_schema.sql
├── includes/             header.php, footer.php, sidebar.php
├── modules/              profile_tab.php, dtr_tab.php, requirements_tab.php
├── uploads/
│   ├── photos/           Intern profile photos
│   └── requirements/     Uploaded requirement files
├── dashboard.php
├── departments.php
├── department_view.php
├── interns.php
├── intern_workspace.php
├── audit.php
├── reports.php
├── settings.php
├── login.php
├── logout.php
├── index.php
└── .htaccess
```
