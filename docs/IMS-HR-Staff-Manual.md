# TDT Powersteel — Intern Management System
## HR Staff User Manual

**Version:** 1.0  
**Date:** June 2026  
**Prepared for:** HR Staff  
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
10. [Troubleshooting](#10-troubleshooting)
11. [Glossary](#11-glossary)

---

## 1. Introduction

### 1.1 System Overview

The TDT Powersteel Intern Management System (IMS) is a web-based platform that centralizes the management of the company's OJT and internship program. It handles intern profiles, daily time records (DTR), requirements tracking, MOA records, and company policies in one place.

### 1.2 About This Manual

This manual is for **HR Staff** — users with the `hr_staff` role. It covers all features available to your role. Some system areas (Settings and Audit Trail) are restricted to Administrators only.

### 1.3 What You Can Do

As HR Staff, you have access to:
- View the Dashboard
- View Departments and their intern lists
- Manage all intern profiles, DTR entries, and requirements
- Manage MOA records
- View the Policy Hub
- Generate and export reports

---

## 2. Getting Started

### 2.1 System Requirements

The IMS runs in a web browser — no software installation needed. You need:
- Google Chrome (recommended) or any modern browser
- A stable internet or local network connection
- A valid IMS user account

### 2.2 Logging In

1. Open your browser and go to the IMS URL given to you by your administrator.
2. Enter your **Email Address** and **Password**.
3. Click **Log In**.

If your credentials are correct, you will be taken to the Dashboard.

**Important:**
- After **5 failed login attempts**, your account will be locked. Ask your Admin to unlock it.
- You will be automatically logged out after **30 minutes of inactivity**.
- Never share your password with anyone.

### 2.3 Navigating the Interface

| Area | What It Does |
|---|---|
| Left Sidebar | Links to all modules: Dashboard, Departments, Intern Management, MOA Management, Policy Hub, Reports & Export |
| Top Bar | Shows your name and breadcrumb trail so you always know where you are |
| Main Content Area | Where all the information, tables, and forms appear |

### 2.4 Logging Out

Click **Logout** at the bottom of the left sidebar.

---

## 3. Dashboard

The Dashboard gives you an at-a-glance overview of the internship program as soon as you log in.

### 3.1 Summary Cards

| Card | What It Shows |
|---|---|
| Total Active Interns | How many interns are currently active |
| Total Hours Rendered | Combined hours worked by all active interns |
| Avg. Completion | Average percentage of required OJT hours completed |
| Departments | Number of active departments |

### 3.2 Departments Grid

A grid of department cards showing the department name and active intern count. Click any card to see the interns in that department.

### 3.3 Recent Interns

A table of the 5 most recently added active interns. Click any row to open that intern's workspace.

### 3.4 Intern Policy Hub Widget

A quick-access panel for browsing intern policies by category. Click any category to jump to that section in the Policy Hub.

---

## 4. Departments

### 4.1 Viewing Departments

Click **Departments** in the sidebar to see all departments and their intern counts.

### 4.2 Department View

Click any department card to open the Department View, which lists all interns assigned to that department along with their progress and hours.

> **Note:** HR Staff can view departments but cannot add or edit them. Contact your Admin if a new department needs to be created.

---

## 5. Intern Management

### 5.1 Opening the Intern List

Click **Intern Management** in the sidebar. The page opens on **Active** interns by default.

### 5.2 Switching Between Statuses

Use the tabs at the top to switch between:
- **Active** — currently on OJT
- **Inactive** — temporarily not reporting
- **Archived** — interns who have completed or been terminated

Each tab shows the count in parentheses.

### 5.3 Searching for an Intern

- Type a name in the search box and click **Search**.
- Use the department dropdown to filter by department.
- Click **Reset** to clear the search.

### 5.4 Opening an Intern's Workspace

Click anywhere on an intern's row, or click the **arrow button** at the end of the row, to open their full workspace.

---

## 6. Intern Workspace

The Intern Workspace is the central page for managing an individual intern. It has three tabs: **201 Profile**, **DTR**, and **Requirements**.

---

### 6.1 201 Profile Tab

This tab contains the intern's complete information.

#### Viewing the Profile

All personal, academic, and internship details are visible here.

#### Editing the Profile

1. Update the relevant fields directly on the form.
2. Click **Save Changes**.

**Fields you can edit:**

*Personal*
- First Name, Middle Name, Last Name
- Email, Phone, Address
- Birthdate, Gender, Nationality, Civil Status
- Guardian Name, Guardian Contact

*Academic*
- School, Course, Year Level, School Address

*Internship Details*
- Department
- Required Hours
- Start Date, End Date
- Supervisor
- Profile Photo (JPEG/PNG only, max 5MB)

#### Archiving an Intern

When an intern's OJT period ends or they are terminated, archive their record:

1. Scroll to the bottom of the 201 Profile tab.
2. Click **Archive Intern**.
3. The intern's status is set to `Archived`.

> Archived interns are hidden from the Active list but all their data (DTR, requirements) is kept. You can find them under the **Archived** tab in Intern Management.

---

### 6.2 DTR Tab

The DTR tab is where you log and manage the intern's daily attendance.

#### Adding a DTR Entry

1. Click **Add Entry**.
2. Select the **Date**.
3. Enter the **Time In** and **Time Out**.
4. Select a **Remark** if applicable.
5. Click **Save**.

**When are Time In / Time Out required?**

| Remark | Time Required? |
|---|---|
| *(none)* — regular day | Yes |
| Half Day | Yes |
| Excused | No — times are cleared |
| Absent | No — times are cleared |
| Holiday | No — times are cleared |
| No Office | No — times are cleared |

> You cannot add two entries for the same date. The system will show an error if a duplicate is detected.

#### Editing a DTR Entry

- Click the **pencil icon** on any row to edit the Time In and Time Out.
- Make your changes and save.

> Time Out must always be later than Time In.

#### Updating a Remark

- Click the remark badge (or the remark cell) on any DTR row.
- Select the new remark from the dropdown and save.
- If you change to a non-working remark (Absent, Holiday, No Office, Excused), the system will automatically clear the times so no hours are counted.

#### Deleting a DTR Entry

- Click the **trash icon** on any DTR row.
- Confirm the deletion in the prompt.

> Deleted DTR entries cannot be recovered. Double-check before deleting.

#### Rendered Hours

The total rendered hours shown in the intern's header card updates automatically after every change you make to the DTR.

---

### 6.3 Requirements Tab

This tab tracks the internship documents that the intern needs to submit.

#### Viewing Requirements

All requirements are listed with their current status (Pending, Submitted, Approved) and any attached files or remarks.

#### Adding a Requirement

1. Click **Add Requirement**.
2. Enter the requirement name (e.g., "School Endorsement Letter", "Waiver Form").
3. Click **Save**.

#### Updating a Requirement's Status

Click the status badge on any requirement to change it:
- **Pending** — not yet submitted by the intern
- **Submitted** — the intern has submitted the document
- **Approved** — the document has been reviewed and accepted

#### Uploading a File

1. Click the upload icon on any requirement.
2. Select the file (PDF, JPEG, PNG, or DOCX; max 10MB).
3. The file is saved and linked to the requirement.

#### Adding Remarks

Click the remarks area on any requirement to type and save a note (max 500 characters). This is useful for noting issues or instructions related to a document.

#### Archiving and Restoring a Requirement

- Click the **archive icon** to remove a requirement from the active list without deleting it.
- To view archived requirements, look for the **Show Archived** toggle in the tab.
- Click the **restore icon** to bring it back to the active list.

---

## 7. MOA Management

The MOA (Memorandum of Agreement) module stores and tracks TDT Powersteel's academic partnership agreements with schools and universities.

### 7.1 Viewing MOA Records

Click **MOA Management** in the sidebar. Summary cards at the top show the count per status:
- **Active** — agreement is current and in force
- **Expired** — agreement has lapsed
- **For Verification** — pending confirmation
- **On Process** — being processed or drafted
- **For Renewal** — active but due for renewal

### 7.2 Searching and Filtering

- Use the search bar to find a school by name.
- Use the status dropdown to filter by agreement status.
- Click **Reset** to clear filters.
- Click **Archived** to view archived/deleted MOA records, and click it again to return to active records.

### 7.3 Adding an MOA Record

1. Click **Add MOA**.
2. Fill in the form:
   - **SEQ** — optional ordering number
   - **School / University** *(required)*
   - **Validity** — duration of the agreement (e.g., "3 years")
   - **Status** — select from the dropdown
   - **Period Start / Period End** — the agreement's active date range
   - **Remarks** — any notes (e.g., "Pending to Receive")
   - **MOA File** — optional file upload (PDF, JPEG, PNG, DOCX; max 20MB)
3. Click **Add**.

### 7.4 Editing an MOA Record

1. Click the **pencil icon** on the MOA row.
2. Update the relevant fields.
3. If you want to replace the uploaded file, select a new one. Leave the file field empty to keep the existing file.
4. Click **Save Changes**.

### 7.5 Archiving an MOA Record

Click the **archive icon** on any row and confirm. The record moves to the archived list and no longer appears in the active table.

### 7.6 Restoring an Archived MOA

1. Click the **Archived** button in the toolbar.
2. Find the record and click the **restore icon**.
3. Click **Active** to return to the active MOA list.

---

## 8. Policy Hub

The Policy Hub displays TDT Powersteel's official OJT and internship policies grouped by category.

Click **Policy Hub** in the sidebar to view all policies. They are organized into:
- Traineeship Terms
- Attendance & Schedule
- Dress Code
- Conduct & Performance
- Trainer & Supervision
- Compensation

This page is for **reference only**. All interns are expected to be familiar with these policies. You can share the link to this page with interns or print the relevant sections as needed.

---

## 9. Reports & Export

### 9.1 Quick Export

Three export cards are available at the top of the Reports page:

**All Interns**
- Click **PDF** or **CSV** to download the complete intern list with hours.

**DTR by Intern**
1. Select an intern from the dropdown.
2. Click the **PDF** icon or **CSV** icon to export their full DTR record.

**Requirements by Intern**
1. Select an intern from the dropdown.
2. Click **PDF** to download their requirements checklist.

> Always select an intern before clicking the export button. If no intern is selected, the system will show a warning.

### 9.2 Department Summary Table

The table on the Reports page shows department-level statistics:
- Number of active interns
- Total rendered and required hours
- Average completion percentage

### 9.3 All Interns Overview

A full table listing every intern across all departments and statuses, with progress information. Click any intern's name to open their workspace.

---

## 10. Troubleshooting

| Problem | What to Do |
|---|---|
| Cannot log in | Check your email and password. Make sure CAPS LOCK is off. If you see "Account locked", contact your Admin. |
| "Session expired" message | Your session was idle for 30 minutes and was ended for security. Log in again. |
| Intern not appearing in the list | Check the status tab — they may be under Inactive or Archived. |
| Cannot save a DTR entry | Make sure Time Out is after Time In. Check that no entry already exists for that date. |
| File upload fails | Check that the file is in the accepted format (PDF, JPEG, PNG, DOCX) and is under the size limit. |
| Exported report is blank or wrong | Make sure you selected the correct intern from the dropdown before exporting. |
| Cannot access Settings or Audit Trail | These pages are for Admins only. Contact your Admin for changes to user accounts or system settings. |
| Face registration link says "Link Expired" | The token expires 24 hours after being generated. Contact your Admin to generate a new registration link. |

---

## 11. Glossary

| Term | Definition |
|---|---|
| IMS | Intern Management System — the web-based platform described in this manual |
| HR Staff | A user role with access to intern management, MOA, and reporting features |
| Admin | A user role with full system access, including Settings and Audit Trail |
| DTR | Daily Time Record — the attendance log of time-in and time-out entries for each intern |
| Rendered Hours | Total hours worked by an intern, calculated from their DTR entries |
| Required Hours | The total OJT hours the intern must complete (default: 486 hours) |
| 201 Profile | The complete profile record of an intern, covering personal, academic, and internship details |
| MOA | Memorandum of Agreement — a formal partnership document between TDT Powersteel and a school |
| QR Code | A unique scannable code given to each intern after face registration, used at the attendance kiosk |
| Archived | A status for records that are hidden from active lists but preserved in the database |
| Status: Active | Intern is currently on OJT |
| Status: Inactive | Intern is temporarily not reporting |
| Status: Archived | Internship is completed or terminated |

---

*TDT Powersteel Corporation · Intern Management System · v1.0 · June 2026*
