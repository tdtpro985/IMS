# Safe Database Migration Guide (Pulling Updates)

When a teammate has made changes to the database schema (e.g., adding the new Face ID and Kiosk columns) and you need to pull those updates, **DO NOT re-import the full `ims_schema.sql` file.** Doing so will overwrite and destroy your local data (interns, DTR entries, requirements).

Instead, follow this safe migration process to update your local database structure while keeping your data perfectly intact.

## 🚨 STEP 1: Backup Your Database (Safety First!)
Before running any updates or migrations, always create a backup of your current local data.
1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Select your `tdt_ims` (or equivalent) database on the left sidebar.
3. Click the **Export** tab at the top.
4. Leave the method as "Quick" and format as "SQL".
5. Click **Export** to download your database backup file. 

## STEP 2: Fetch Latest Remote Changes
Update your local git history with the remote repository:
```bash
git fetch
```

## STEP 3: Pull the Latest Code
*(Assuming the `connecting-kiosk` branch has been merged into `main`)*

Pull the latest updates into your active branch:
```bash
git pull origin connecting-kiosk
```
*(Note: If you are testing the branch directly before it is merged to main, use `git pull origin connecting-kiosk` instead).*

## STEP 4: Run the Migration Script
To automatically apply the new Face ID and Kiosk columns to your local database without touching your existing data, run the provided PHP migration script. You can do this in two ways:

**Option A (Browser):** 
Open your web browser and navigate to:
`http://localhost/INTERN-MANAGEMENT-SYSTEM/db/add_face_columns.php`
*(Adjust the URL if your local folder name or setup is slightly different).*

**Option B (Terminal):** 
Open your terminal inside the `INTERN-MANAGEMENT-SYSTEM` folder and run:
```bash
php db/add_face_columns.php
```

*(Note: If the script says "Already exists" for the columns, it means they were already added previously. This script is perfectly safe to run multiple times.)*

## STEP 5: Generate Registration Links for Interns
Now that the database is updated with Face ID capabilities, you need to register the existing interns.
1. Log into the Intern Management System as an administrator.
2. Go to the intern management view.
3. Generate the specific face registration link for each intern.
4. Send the respective links to the interns so they can complete their responsive Face Registration process on their own devices.
