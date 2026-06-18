# TDT Powersteel — Intern Management System
## Administrator Manual

**Version:** 1.0  
**Date:** June 2026  
**Prepared for:** System Administrators  
**System:** Intern Management System (IMS)

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Dashboard](#3-dashboard)
4. [Departments](#4-departments)
5. [Intern Management](#5-intern-management)
6. [Intern Workspace](#6-intern-workspace)
7. [MOA Management](#7-moa-management)
8. [Policy Hub](#8-policy-hub)
9. [Reports & Export](#9-reports--export)
10. [Audit Trail](#10-audit-trail)
11. [Settings](#11-settings)
12. [Troubleshooting](#12-troubleshooting)
13. [Glossary](#13-glossary)

---

## 1. Introduction

### 1.1 System Overview

The TDT Powersteel Intern Management System (IMS) is a web-based platform designed to manage all aspects of the company's OJT and internship program. It centralizes intern records, daily time records (DTR), requirements tracking, MOA management, and policy publishing under one system.

### 1.2 About This Manual

This manual is intended for **Administrators** — users with the `admin` role — who have full access to every feature in the system, including settings and the audit trail.

### 1.3 User Roles

The IMS supports two roles:

| Feature | Admin | HR Staff |
|---|---|---|
| Dashboard | Full Access | Full Access |
| Departments | Add, Edit | View Only |
| Intern Management | Full Access | Full Access |
| Intern Workspace (Profile, DTR, Requirements) | Full Access | Full Access |
| MOA Management | Full Access | Full Access |
| Policy Hub | View | View |
| Reports & Export | Full Access | Full Access |
| Audit Trail | Full Access | No Access |
| Settings (Users, System) | Full Access | No Access |

> **Note:** Only Admins can access Settings and the Audit Trail. HR Staff are redirected to the Dashboard if they attempt to access those pages.

---

## 2. Getting Started

### 2.1 System Requirements

The IMS is a web-based application. No local installation is required. Ensure you have:

- A modern web browser (Google Chrome recommended)
- Stable internet or local network connection
- A valid IMS user account

### 2.2 Logging In

1. Open your browser and navigate to the IMS URL provided by your administrator.
2. Enter your registered **Email Address** and **Password**.
3. Click **Log In**.
4. On success, you will be redirected to the Dashboard.

**Security notes:**
- After **5 failed login attempts**, your account will be locked. Contact another Admin to unlock it.
- Sessions expire after **30 minutes of inactivity**. You will be redirected to the login page with a timeout notice.
- Do not share your login credentials.

### 2.3 Navigating the Interface

The IMS interface has three main areas:

| Area | Description |
|---|---|
| Left Sidebar | Main navigation: Dashboard, Departments, Intern Management, MOA Management, Policy Hub, Reports & Export, Audit Trail, Settings |
| Top Bar | Shows your username and breadcrumb navigation |
| Main Content Area | The workspace where all module content is displayed |

### 2.4 Logging Out

Click **Logout** at the bottom of the left sidebar to end your session.

---

## 3. Dashboard

The Dashboard is the home screen and gives a real-time overview of the internship program.

### 3.1 Summary Cards

Four stat cards are shown at the top:

| Card | Description |
|---|---|
| Total Active Interns | Count of all interns with `Active` status |
| Total Hours Rendered | Sum of rendered hours across all active interns |
| Avg. Completion | Average percentage of required hours completed |
| Departments | Total number of configured departments |

### 3.2 Departments Grid

A grid of clickable department cards. Each card shows the department name and the number of active interns. Click any card to go to that department's view.

### 3.3 Recent Interns

A table of the 5 most recently added active interns, showing name, department, status, progress bar, and hours. Click any row to open that intern's workspace.

### 3.4 Intern Policy Hub Widget

A quick-access panel showing all active policy categories. Click any category card to jump directly to that section in the Policy Hub.

---

## 4. Departments

### 4.1 Viewing Departments

Navigate to **Departments** in the sidebar. Each department card displays:
- Department name
- Number of active interns
- Total intern count (all statuses)

Click a card to open the **Department View**, which lists all interns assigned to that department.

### 4.2 Adding a Department *(Admin only)*

1. Click the **Add Department** button (top right).
2. Enter the department name.
3. Click **Add**.

### 4.3 Editing a Department *(Admin only)*

1. On any department card, click the **pencil icon** (top-right corner of the card).
2. Update the department name.
3. Click **Save**.

> **Note:** Deleting departments is not supported to preserve data integrity. Rename inactive departments if needed.

---

## 5. Intern Management

### 5.1 Viewing Interns

Navigate to **Intern Management** in the sidebar. The list defaults to `Active` interns.

**Status tabs** at the top allow filtering by:
- **Active** — currently on OJT
- **Inactive** — temporarily not reporting
- **Archived** — completed or terminated interns

Each tab shows the count for that status.

### 5.2 Searching and Filtering

Use the search bar to search by name. Use the department dropdown to filter by department. Click **Search** to apply, **Reset** to clear.

### 5.3 Intern Table Columns

| Column | Description |
|---|---|
| Intern | Photo/initials, full name, and email |
| Department | Assigned department |
| School | University or school |
| Status | Active / Inactive / Archived badge |
| Progress | Visual progress bar (% of required hours completed) |
| Hours | Rendered hours / Required hours |
| Action | Arrow button to open the intern's workspace |

Click any row to open the intern's workspace directly.

### 5.4 Adding a New Intern

New interns are onboarded through a **registration link** sent by HR. The link directs the intern to the self-registration page (`/register_intern.php?token=...`) where they:
1. Confirm or enter their email address.
2. Complete the face registration process (5 facial angle captures).
3. Receive their unique QR code via email for kiosk clock-in/out.

> **Prerequisite:** The intern record must first be created in the database (by an Admin or HR Staff) with a valid `registration_token` before the link can be sent.

---

## 6. Intern Workspace

Each intern has a dedicated workspace accessible from the Intern Management list or department view. The workspace has three tabs.

### 6.1 201 Profile Tab

Displays and allows editing of the intern's complete profile information.

**Editable fields:**

*Personal Information*
- First name, Middle name, Last name
- Email, Phone, Address
- Birthdate, Gender, Nationality, Civil Status
- Guardian Name, Guardian Contact

*Academic Information*
- School, Course, Year Level, School Address

*Internship Details*
- Department
- Required Hours (default: 486)
- Start Date, End Date
- Supervisor
- Profile Photo (JPEG/PNG, max 5MB)

**Actions available on the Profile tab:**
- **Save Changes** — updates all profile fields
- **Archive Intern** — sets the intern's status to `Archived` (use when the internship is completed or terminated)

> **Note:** You must re-open the intern's workspace after archiving to verify the change. Archived interns retain all their DTR and requirements data.

### 6.2 DTR Tab

The Daily Time Record tab manages the intern's attendance log.

**Adding a DTR Entry**
1. Click **Add Entry**.
2. Select the date.
3. Enter Time In and Time Out (required unless the remark is Absent, Holiday, No Office, or Excused).
4. Select a remark if applicable.
5. Click **Save**.

**Remark options:**

| Remark | Behavior |
|---|---|
| *(none)* | Normal working day |
| Half Day | Records hours as entered |
| Excused | Clears time in/out, no hours counted |
| Absent | Clears time in/out, no hours counted |
| Holiday | Clears time in/out, no hours counted |
| No Office | Clears time in/out, no hours counted |

**Editing a DTR Entry**
- Click the **pencil icon** on any row to edit the Time In and Time Out.
- Click the **remark badge** on any row to update the remark. Non-working remarks will clear the times automatically.

**Deleting a DTR Entry**
- Click the **trash icon** on any row and confirm deletion.

> **Note:** Duplicate dates are prevented. Each intern can have only one DTR entry per calendar day.

**Lunch Break Deduction**

If lunch break deduction is enabled in Settings, the configured lunch break duration (in minutes) is automatically subtracted from each DTR entry's rendered hours. This affects all new entries going forward.

**Total Rendered Hours** updates automatically after every add, edit, or delete operation.

### 6.3 Requirements Tab

Tracks the submission status of all internship documents required from the intern.

**Adding a Requirement**
1. Click **Add Requirement**.
2. Enter the requirement name.
3. Click **Save**.

**Updating Requirement Status**
Click the status badge on any requirement to cycle through:
- `Pending` → `Submitted` → `Approved`

**Uploading a File**
- Click the upload icon on any requirement.
- Accepted formats: PDF, JPEG, PNG, DOCX (max 10MB).
- The file is saved and linked to the requirement record.

**Remarks**
Click the remarks field on any requirement to add or update notes.

**Archiving a Requirement**
Click the archive icon to hide a requirement. Archived requirements can be restored from the archived view.

---

## 7. MOA Management

The MOA (Memorandum of Agreement) module tracks academic partnership agreements with schools and universities.

### 7.1 Viewing MOA Records

Navigate to **MOA Management**. Status summary cards at the top show counts by status:
- Active, Expired, For Verification, On Process, For Renewal

Use the search bar and status filter to narrow results.

### 7.2 Adding an MOA Record

1. Click **Add MOA**.
2. Fill in the required fields:
   - **SEQ** — optional sort order number
   - **School / University** *(required)*
   - **Validity** — e.g., "3 years" or "For Verification"
   - **Status** — On Process / Active / For Verification / Expired / For Renewal
   - **Period Start / Period End** — the agreement's effective dates
   - **Remarks** — any notes (e.g., "Pending to Receive")
   - **MOA File** — optional upload (PDF, image, or DOCX, max 20MB)
3. Click **Add**.

### 7.3 Editing an MOA Record

1. Click the **pencil icon** on any MOA row.
2. Update the desired fields. Leave the file upload blank to keep the existing file.
3. Click **Save Changes**.

### 7.4 Archiving and Restoring MOA Records

- Click the **archive icon** on any active record to move it to the archived list.
- Click **Archived** toggle button in the toolbar to view archived records.
- Click the **restore icon** on any archived record to bring it back.

> **Note:** Expired and For Renewal records are highlighted in the table to draw attention.

---

## 8. Policy Hub

The Policy Hub displays TDT Powersteel's official OJT/intern policies grouped by category, based on the On-The-Job Training Agreement.

**Categories:**
- Traineeship Terms
- Attendance & Schedule
- Dress Code
- Conduct & Performance
- Trainer & Supervision
- Compensation

This page is **view-only** for all users. Policy content is managed directly in the database. Contact a developer to add or update policy entries.

---

## 9. Reports & Export

### 9.1 Quick Export Cards

Three export shortcuts are available at the top:

**All Interns**
- Export the full intern list with hours as **PDF** or **CSV**.

**DTR by Intern**
1. Select an intern from the dropdown.
2. Click the **PDF** or **CSV** button to download that intern's DTR records.

**Requirements by Intern**
1. Select an intern from the dropdown.
2. Click **PDF** to download that intern's requirements checklist.

### 9.2 Department Summary Table

Shows aggregated statistics per department:
- Active intern count
- Total rendered and required hours
- Average completion percentage with a progress bar

### 9.3 All Interns Overview Table

A complete table of all interns across all statuses, showing:
- Name (clickable link to intern workspace), Department, Status, Start/End Dates, Rendered/Required hours, and Progress.

---

## 10. Audit Trail

*(Admin only — HR Staff cannot access this page.)*

The Audit Trail logs every significant action performed in the system for accountability and traceability.

### 10.1 Viewing Logs

Navigate to **Audit Trail**. The table shows the 500 most recent log entries (newest first).

**Columns:**

| Column | Description |
|---|---|
| Timestamp | Date and time the action occurred (UTC) |
| User | Name of the user who performed the action |
| Action | Type of action (see table below) |
| Module | System area affected (e.g., Interns, DTR, Users) |
| Record ID | Database ID of the affected record |
| Description | Human-readable summary of what happened |

**Action types:**

| Action | Meaning |
|---|---|
| CREATE | A new record was added |
| UPDATE | An existing record was modified |
| ARCHIVE | A record was archived |
| RESTORE | An archived record was restored |
| DELETE | A record was permanently deleted |
| LOGIN | A user logged in |
| LOGOUT | A user logged out |
| LOCK | An account was locked due to failed login attempts |
| REGISTER_FACE | An intern completed face registration |

### 10.2 Filtering Logs

Use the filter bar to narrow results by:
- **Date From / Date To** — limit to a date range
- **User** — filter by user name (partial match)
- **Action** — select a specific action type from the dropdown

Click **Filter** to apply, **Reset** to clear all filters.

---

## 11. Settings

*(Admin only)*

### 11.1 System Users

The user management table lists all accounts with their name, email, role, lock status, and creation date.

**Adding a User**
1. Click **Add User**.
2. Fill in:
   - Full Name *(required)*
   - Email *(required, must be a valid email address)*
   - Password *(required, minimum 8 characters)*
   - Role — `HR Staff` or `Admin`
3. Click **Create User**.

> **Note:** Email addresses must be unique. Duplicate emails will return an error.

**Unlocking a Locked Account**

If a user's account shows a **Locked** badge (due to 5 failed login attempts), click the **unlock icon** in their row to restore access and reset their failure count.

### 11.2 Shift & Hours Customization

**Lunch Break Deduction**
- Toggle **Deduct lunch break / rest period from rendered hours** on or off.
- Set the **Lunch Break Duration** in minutes (default: 60).
- When enabled, this deduction is applied to all newly created DTR entries.

**Standard Daily Hours Threshold**
- Set the number of hours considered a standard workday (default: 8).
- Used to compute whether an intern has overtime on a given day.

Click **Save Settings** to apply changes.

### 11.3 Change Password

1. Enter your **Current Password**.
2. Enter and confirm your **New Password** (minimum 8 characters).
3. Click **Update Password**.

> This updates only your own account's password. To change another user's password, that user must do it themselves via their own Settings page, or contact the developer for a manual reset.

---

## 12. Troubleshooting

| Problem | Solution |
|---|---|
| Cannot log in | Check your email and password. Make sure CAPS LOCK is off. If the account shows Locked, ask another Admin to unlock it from Settings. |
| Session expired message | Your session was idle for over 30 minutes. Log in again. |
| Dashboard shows no interns | No active interns exist yet, or all interns have been archived. Check the Intern Management page. |
| DTR entry won't save | Check that Time Out is later than Time In. Ensure the date does not already have an entry. |
| File upload fails | Verify the file format (PDF, JPEG, PNG, DOCX) and that it does not exceed the size limit (5MB for photos, 10MB for requirements, 20MB for MOA files). |
| Export file is blank | Ensure you have selected an intern before clicking the export button. |
| Face registration link expired | Registration tokens expire after 24 hours. The intern record needs a new token generated by a developer or via the database. |
| Audit Trail shows no data | Verify the date range and user filters. Click Reset to clear all filters. |

---

## 13. Glossary

| Term | Definition |
|---|---|
| IMS | Intern Management System — this web-based platform |
| Admin | User role with full system access including Settings and Audit Trail |
| HR Staff | User role with access to intern management features; no access to Settings or Audit Trail |
| DTR | Daily Time Record — the log of time-in and time-out entries for each intern |
| Rendered Hours | Total hours an intern has worked, calculated from DTR entries |
| Required Hours | The total number of OJT hours an intern must complete (default: 486) |
| 201 Profile | The complete personal, academic, and internship profile of an intern |
| MOA | Memorandum of Agreement — a formal partnership document between TDT Powersteel and a school or university |
| QR Code | A unique scannable code assigned to each intern after face registration, used at the attendance kiosk |
| Face Embedding | A numerical representation of an intern's facial features, stored for biometric verification at the kiosk |
| Archived | A status indicating a record is hidden from active views but preserved in the database |
| Audit Trail | A chronological log of all significant system actions for accountability and traceability |
| Session Timeout | Automatic logout after 30 minutes of inactivity |
| Account Lock | A security measure that disables a login account after 5 consecutive failed password attempts |

---

*TDT Powersteel Corporation · Intern Management System · v1.0 · June 2026*
