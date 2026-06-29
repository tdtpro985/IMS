# TDT Powersteel — Intern Management System
## Full System Documentation

**Version:** 1.0
**Date:** June 2026
**System:** Intern Management System (IMS)
**Prepared by:** Development Team

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Architecture & Tech Stack](#2-system-architecture--tech-stack)
3. [User Roles & Access Matrix](#3-user-roles--access-matrix)
4. [System Interface Layout](#4-system-interface-layout)
5. [Diagrams](#5-diagrams)
6. [Module 1 — Login & Authentication](#6-module-1--login--authentication)
7. [Module 2 — Dashboard](#7-module-2--dashboard)
8. [Module 3 — Departments](#8-module-3--departments)
9. [Module 4 — Intern Management](#9-module-4--intern-management)
10. [Module 5 — Intern Workspace](#10-module-5--intern-workspace)
11. [Module 6 — MOA Management](#11-module-6--moa-management)
12. [Module 7 — Policy Hub](#12-module-7--policy-hub)
13. [Module 8 — Reports & Export](#13-module-8--reports--export)
14. [Module 9 — Audit Trail](#14-module-9--audit-trail)
15. [Module 10 — Settings](#15-module-10--settings)
16. [Intern Self-Registration](#16-intern-self-registration)
17. [Hours Calculation Logic](#17-hours-calculation-logic)
18. [Database Tables Reference](#18-database-tables-reference)
19. [Troubleshooting & FAQ](#19-troubleshooting--faq)
20. [Glossary](#20-glossary)

---

## 1. Introduction

### 1.1 System Overview

The **TDT Powersteel Intern Management System (IMS)** is a web-based platform built to centralize and streamline all OJT and internship program operations at TDT Powersteel Corporation. It replaces manual, paper-based tracking with a unified digital system accessible to authorized personnel from any browser.

The IMS covers the full lifecycle of an intern's engagement with the company — from onboarding and profile management, to daily attendance tracking, document requirements, MOA monitoring, and final certificate generation.

### 1.2 Scope

This document is the full technical and functional reference for the IMS. It describes every module, every form field, every dropdown option, all business rules, and all role-based access controls. It is intended for:

- System administrators managing the platform
- HR staff using the system day-to-day
- Developers maintaining or extending the codebase
- Evaluators reviewing the system for documentation or compliance purposes

### 1.3 Key Features

- Intern 201 profile management with photo upload
- Daily Time Record (DTR) logging with inline editing and automatic hour calculation
- Overtime and undertime tracking
- Certificate of Completion (COC) generation
- Internship document requirements tracking with file upload and preview
- Memorandum of Agreement (MOA) repository
- Company policy publishing
- Reports and exports in PDF and CSV format
- Face registration and QR code generation for biometric kiosk attendance
- Full audit trail of all system actions
- Role-based access control (Admin vs HR Staff)
- Account lockout after 5 failed login attempts
- Session timeout after 30 minutes of inactivity

---

## 2. System Architecture & Tech Stack

### 2.1 Technology Stack

| Layer | Technology |
|---|---|
| Server-side language | PHP 8+ |
| Database | MySQL — database: `tdt_ims` |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| UI Icons | Font Awesome 6.5 |
| Typography | Google Fonts — Inter |
| Face Detection | MediaPipe Face Landmarker (WASM/GPU, loaded via CDN) |
| Face Embedding | Python ONNX service running on port 5001 |
| QR Code Generation | api.qrserver.com (external API) |
| PDF/Print Export | HTML print stylesheet + browser print dialog |
| Timezone | Asia/Manila (GMT+8) |

### 2.2 File Structure

| Path | Purpose |
|---|---|
| `/index.php` | Entry point — redirects to dashboard or login |
| `/login.php` | Login page |
| `/logout.php` | Session destruction and redirect |
| `/dashboard.php` | Main dashboard |
| `/departments.php` | Department list |
| `/department_view.php` | Single department intern list |
| `/interns.php` | Intern management list |
| `/intern_workspace.php` | Intern 201, DTR, and requirements workspace |
| `/register_intern.php` | Intern self-registration (public, token-gated) |
| `/moa.php` | MOA management |
| `/policies.php` | Policy hub |
| `/reports.php` | Reports and export |
| `/audit.php` | Audit trail (Admin only) |
| `/settings.php` | System settings (Admin only) |
| `/modules/profile_tab.php` | 201 profile tab (included by workspace) |
| `/modules/dtr_tab.php` | DTR tab (included by workspace) |
| `/modules/requirements_tab.php` | Requirements tab (included by workspace) |
| `/api/export_interns.php` | Intern list export (PDF/CSV) |
| `/api/export_dtr.php` | DTR export (PDF/CSV) |
| `/api/export_requirements.php` | Requirements export (PDF) |
| `/api/export_coc.php` | Certificate of Completion export (PDF) |
| `/config/db.php` | Database connection |
| `/config/session.php` | Session management and role helpers |
| `/config/audit.php` | Audit logging function |
| `/assets/css/main.css` | Global stylesheet |
| `/assets/css/login.css` | Login page stylesheet |
| `/assets/js/main.js` | Global JavaScript |
| `/uploads/photos/` | Intern profile photos |
| `/uploads/requirements/` | Requirement document uploads |
| `/uploads/moa/` | MOA file uploads |

---

## 3. User Roles & Access Matrix

The IMS supports two user roles. Access is enforced server-side on every page load via `checkSession()` and `requireRole()` in `config/session.php`.

| Module / Feature | Admin | HR Staff |
|---|:---:|:---:|
| Login / Logout | ✅ | ✅ |
| Dashboard | ✅ | ✅ |
| View Departments | ✅ | ✅ |
| Add Department | ✅ | ❌ |
| Edit Department | ✅ | ❌ |
| View Intern List | ✅ | ✅ |
| Search & Filter Interns | ✅ | ✅ |
| Open Intern Workspace | ✅ | ✅ |
| View / Edit 201 Profile | ✅ | ✅ |
| Archive / Restore Intern | ✅ | ✅ |
| View / Add / Edit / Delete DTR | ✅ | ✅ |
| Export DTR (PDF / CSV) | ✅ | ✅ |
| Export COC | ✅ | ✅ |
| View / Add Requirements | ✅ | ✅ |
| Update Requirement Status | ✅ | ✅ |
| Upload Requirement File | ✅ | ✅ |
| Archive / Restore Requirement | ✅ | ✅ |
| MOA — View / Add / Edit | ✅ | ✅ |
| MOA — Archive / Restore | ✅ | ✅ |
| Policy Hub (view) | ✅ | ✅ |
| Reports & Export | ✅ | ✅ |
| Audit Trail | ✅ | ❌ |
| Settings — User Management | ✅ | ❌ |
| Settings — Shift & Hours Config | ✅ | ❌ |
| Settings — Change Password | ✅ | ❌ |

> HR Staff who navigate to `/audit.php` or `/settings.php` are automatically redirected to `/dashboard.php`.

### 3.1 Role Definitions

**Admin (`admin`)**
Full access to all modules including Settings and Audit Trail. Responsible for managing system users, configuring shift settings, and monitoring system activity.

**HR Staff (`hr_staff`)**
Operational access to all intern management, DTR, requirements, MOA, and reporting features. Cannot access Settings or Audit Trail.

---

## 4. System Interface Layout

Every authenticated page shares the same three-area layout.

### 4.1 Left Sidebar

The main navigation menu. Always visible on desktop. Collapses to an off-canvas drawer on mobile, toggled by a hamburger button in the top bar.

**Links shown to both roles:**
- Dashboard
- Departments
- Intern Management
- MOA Management
- Policy Hub
- Reports & Export

**Links shown to Admin only:**
- Audit Trail
- Settings

**Sidebar footer shows:**
- User avatar icon
- Logged-in user's full name
- User's role (formatted: "Admin" or "Hr Staff")
- Logout link

### 4.2 Top Bar

- Left: hamburger toggle (mobile only) + breadcrumb navigation
- Right: logged-in user's name with a user icon

Breadcrumbs update per page. Example for intern workspace:
`Dashboard > Departments > [Department Name] > [Intern Full Name]`

### 4.3 Main Content Area

The working area where all page content, tables, forms, cards, and modals are rendered.

---

## 5. Diagrams

This section contains the system diagrams. Insert the finalized diagram images below each placeholder.

---

### 5.1 System Flowchart

> **[ INSERT FLOWCHART HERE ]**
>
> The flowchart should illustrate the full system flow starting from Login/Access through all major modules: Dashboard, Intern Management (201 Profile, DTR, Requirements), MOA Management, Reports & Export, Audit Trail, and Settings. Include decision points for role-based access, DTR validation logic, and export conditions (COC only when hours are complete).

---

### 5.2 Use Case Diagram

> **[ INSERT USE CASE DIAGRAM HERE ]**
>
> Two actors: **Admin** and **HR Staff**. Admin inherits all HR Staff use cases and additionally has access to: Add/Edit Departments, Audit Trail, User Management, Shift & Hours Settings, and Change Password. Include «include» and «extend» relationships for key interactions such as validate DTR entry, confirm archive, and conditional COC export.

---

### 5.3 Entity Relationship Diagram (ERD)

> **[ INSERT ERD HERE ]**
>
> Core entities and their relationships:
> - **users** — system accounts (Admin, HR Staff)
> - **departments** — organizational units
> - **interns** — belongs to one department; has many dtr_entries, requirement_items
> - **dtr_entries** — belongs to one intern; has generated columns for rendered_hours, overtime, undertime
> - **requirement_items** — belongs to one intern; stores status, file uploads, remarks
> - **moa_agreements** — independent entity for school partnership records
> - **intern_policies** — independent entity for published OJT policies
> - **system_settings** — key-value store for system configuration
> - **audit_trail** — belongs to one user (nullable); logs all system actions

---

## 6. Module 1 — Login & Authentication

**URL:** `/login.php`
**Access:** Public — no session required

### 6.1 Login Page

The login page is the entry point of the system. It uses a centered card layout with a logo, system title ("Admin Access — Intern Management System"), and the login form.

### 6.2 Login Form

| Field | Type | Required | Notes |
|---|---|:---:|---|
| Email Address | Email input | Yes | Labeled "Username" in the UI |
| Password | Password input | Yes | Show/hide toggle button available |
| Log In | Submit button | — | Submits the form via POST |

### 6.3 Login Logic & Behavior

**On successful login:**
- `fail_count` is reset to 0 in the `users` table
- Session is regenerated (`session_regenerate_id(true)`)
- `$_SESSION` is set: `user_id`, `user_name`, `user_role`, `last_activity`
- `LOGIN` event is written to the `audit_trail` table
- User is redirected to `/dashboard.php`

**On wrong password:**
- `fail_count` increments by 1
- Error shown: *"Invalid email or password. X attempt(s) remaining."*
- If `fail_count` reaches 5: account is locked (`is_locked = 1`)
- Error shown: *"Too many failed attempts. Account locked. Contact an Admin."*
- `LOCK` event is written to the `audit_trail` table

**On locked account login attempt:**
- Error shown: *"This account is locked. Please contact an Administrator."*
- No further login attempts are processed until unlocked by an Admin

**On non-existent email:**
- Error shown: *"Invalid email or password."*

**On session timeout (idle > 30 minutes):**
- User is redirected to `/login.php?timeout=1`
- Warning shown: *"Your session expired due to inactivity. Please sign in again."*

### 6.4 Session Management

Sessions are managed via `config/session.php`. Key behaviors:

| Setting | Value |
|---|---|
| Session timeout | 1800 seconds (30 minutes) |
| Cookie: httponly | true |
| Cookie: samesite | Strict |
| Session check | Every page load via `checkSession()` |

### 6.5 Logout

**URL:** `/logout.php`
**Trigger:** Clicking "Logout" in the sidebar footer

- `LOGOUT` event is written to the audit trail
- `session_unset()` and `session_destroy()` are called
- User is redirected to `/login.php`

---

## 7. Module 2 — Dashboard

**URL:** `/dashboard.php`
**Access:** Admin, HR Staff

The Dashboard is the home screen after login. It gives a real-time overview of the internship program status.

### 7.1 Summary Stat Cards

Four KPI cards are displayed at the top, all scoped to interns with `status = 'Active'`:

| Card | Metric | Sub-text |
|---|---|---|
| Total Active Interns | COUNT of active interns | — |
| Total Hours Rendered | SUM of `rendered_hours` | "of [total required] required" |
| Avg. Completion | (total rendered ÷ total required) × 100% | "On track" if ≥ 50%, "Needs attention" if < 50% |
| Departments | COUNT of all rows in `departments` table | "Active departments" |

### 7.2 Departments Grid

- Lists all departments as clickable cards
- Each card shows: building icon, department name, active intern count, label ("Active Intern/s")
- Clicking any card navigates to `/department_view.php?id={id}`
- If no departments exist: empty state shown
- "View All" button in the card header links to `/departments.php`

### 7.3 Recent Interns Table

- Shows the 5 most recently created interns where `status = 'Active'`
- Ordered by `created_at DESC`
- Clicking any row navigates to `/intern_workspace.php?id={id}`
- "View All" button links to `/interns.php`

**Columns shown:**

| Column | Content |
|---|---|
| Intern | Circular photo/initials avatar + full name |
| Department | Assigned department name |
| Status | Colored badge |
| Progress | Progress bar + percentage |
| Hours | Rendered (1 decimal) / Required |

### 7.4 Policy Hub Widget

- Displays all distinct active policy categories from `intern_policies`
- Each category shown as a card with icon, name, and policy count
- Clicking a category card navigates to `/policies.php#{category}`
- "View All Policies" button links to `/policies.php`

**Policy categories:**

| Category | Icon |
|---|---|
| Traineeship Terms | fa-handshake |
| Attendance & Schedule | fa-calendar-check |
| Dress Code | fa-tshirt |
| Conduct & Performance | fa-shield-alt |
| Trainer & Supervision | fa-chalkboard-teacher |
| Compensation | fa-ban |

---

## 8. Module 3 — Departments

**URL:** `/departments.php`
**Department detail URL:** `/department_view.php?id={id}`
**Access:** View — Admin & HR Staff | Add/Edit — Admin only

### 8.1 Departments List

All departments are shown in a responsive grid of cards. Each card displays:
- Building icon
- Department name
- Active intern count (large number)
- "Active Intern/s" label + total count across all statuses
- Pencil/edit icon — visible to **Admin only**, top-right of each card

Clicking the card body navigates to the Department View page.
If no departments exist, an empty state is shown.

### 8.2 Add Department *(Admin only)*

**Trigger:** "Add Department" button in the page header (top-right)

**Modal — Add Department:**

| Field | Type | Required | Validation |
|---|---|:---:|---|
| Department Name | Text input | Yes | Max 100 characters |

**Buttons:** Cancel · Add

On save: record inserted into `departments`, `CREATE` logged to audit trail, page redirects to `/departments.php`.

> Department names must be unique (enforced by `UNIQUE` constraint on the `departments` table).

### 8.3 Edit Department *(Admin only)*

**Trigger:** Pencil icon on a department card (click stops card navigation via `event.stopPropagation()`)

**Modal — Edit Department:**

| Field | Type | Required | Validation |
|---|---|:---:|---|
| Department Name | Text input (pre-filled) | Yes | Max 100 characters |

**Buttons:** Cancel · Save

On save: `departments.name` is updated, `UPDATE` logged to audit trail, page redirects to `/departments.php`.

> Departments cannot be deleted to preserve historical data integrity. Rename unused departments if needed.

### 8.4 Department View

**URL:** `/department_view.php?id={id}`

Lists all interns assigned to the selected department. Clicking any intern row opens their workspace at `/intern_workspace.php?id={id}`.

---

## 9. Module 4 — Intern Management

**URL:** `/interns.php`
**Access:** Admin, HR Staff

### 9.1 Status Filter Tabs

Three tabs at the top filter the intern list by status. The active tab is highlighted with the corresponding status color. Each tab shows a count badge.

| Tab | Status Filter | Badge Color |
|---|---|---|
| Active | `status = 'Active'` | Green |
| Inactive | `status = 'Inactive'` | Amber/Orange |
| Archived | `status = 'Archived'` | Gray |

Counts respect any active department filter.

### 9.2 Search & Filter Bar

| Control | Type | Behavior |
|---|---|---|
| Search box | Text input | Searches `CONCAT(first_name,' ',last_name) LIKE '%value%'` |
| Department dropdown | Select | Filters by `department_id` |
| Search button | Submit | Applies all active filters |
| Reset button | Link | Clears search and department filter, keeps current status tab |

**Department dropdown options:**
- All Departments *(default, value = "")*
- [All department names from `departments` table, ordered A–Z]

### 9.3 Intern Table

Results are ordered by `last_name, first_name` ASC.

**Columns:**

| Column | Content |
|---|---|
| Intern | Circular avatar (photo or initials) + Full Name (bold) + Email (muted) |
| Department | Assigned department name |
| School | School/university name, or "—" if blank |
| Status | Colored badge: Active / Inactive / Archived |
| Progress | Progress bar (rendered ÷ required × 100%) + percentage label |
| Hours | Rendered hours (1 decimal) / Required hours |
| Action | Arrow button → opens intern workspace |

Clicking any row navigates to `/intern_workspace.php?id={id}`.
The action arrow button also navigates to the same URL (stops row-click propagation).

If no results match: empty state shown with the current status and search term.

### 9.4 Intern Onboarding Flow

New intern records are not created through a UI form in this module. The intended flow is:

1. Admin/HR Staff creates the intern record directly in the database, generating a `registration_token` (64-character random string) and setting `token_expires_at` to 24 hours from now.
2. The registration link is shared with the intern: `/register_intern.php?token={token}`
3. The intern opens the link on their device, confirms their email, and completes face registration (5 captures).
4. On successful registration: `face_embedding`, `qr_code`, `face_registered_at` are saved; `registration_token` and `token_expires_at` are cleared.
5. The intern receives their QR code via email and can now use the biometric kiosk.

> If the token expires (after 24 hours), the registration page shows: *"This registration link is invalid or has expired."* A new token must be generated.

---

## 10. Module 5 — Intern Workspace

**URL:** `/intern_workspace.php?id={id}&tab={tab}`
**Access:** Admin, HR Staff

The Intern Workspace is the central page for managing an individual intern. It contains all three management areas in a tabbed layout.

**Workspace header** (always visible above the tabs):
- Circular profile photo or initials avatar (56×56px, orange border)
- Full name (bold, 18px)
- Department name with building icon
- School name with graduation cap icon
- Status badge (Active / Inactive / Archived)
- Rendered hours (large, orange) / Required hours

**Tab navigation:**

| Tab | URL Param | Content |
|---|---|---|
| 201 Profile | `tab=201` | Full intern profile form |
| DTR | `tab=dtr` | Daily Time Record log |
| Requirements | `tab=reqs` | Document requirements tracker |

Tab state is preserved in the URL (`?id={id}&tab={tab}`) and updated via `history.replaceState()` when switching tabs without a page reload.

---

### 10.1 Tab 1 — 201 Profile

**URL param:** `tab=201` (default)

#### Profile Photo Panel

| Element | Detail |
|---|---|
| Photo display | 110×110px circular frame with orange border. Shows uploaded photo or colored initials avatar |
| Change Photo button | Triggers hidden file input |
| Accepted formats | JPEG, PNG only |
| Maximum size | 5MB |
| Live preview | JavaScript `FileReader` updates the preview immediately before form submission |

#### Section A — Personal Information

| Field | Input Type | Required | Options / Constraints |
|---|---|:---:|---|
| First Name | Text | Yes | Max 80 characters |
| Last Name | Text | Yes | Max 80 characters |
| Middle Name | Text | No | Max 80 characters |
| Gender | Dropdown | No | Male · Female · Other |
| Email | Email | No | Max 150 characters |
| Phone | Text | No | Max 30 characters |
| Birthdate | Date picker | No | — |
| Civil Status | Dropdown | No | Single · Married · Widowed · Separated |
| Nationality | Text | No | Max 60 characters |
| Address | Text | No | Max 255 characters |

#### Section B — Academic Information

| Field | Input Type | Required | Options / Constraints |
|---|---|:---:|---|
| School / University | Text | No | Max 150 characters |
| Course / Program | Text | No | Max 150 characters |
| Year Level | Dropdown | No | 1st Year · 2nd Year · 3rd Year · 4th Year · 5th Year |
| School Address | Text | No | Max 255 characters |

#### Section C — Emergency Contact

| Field | Input Type | Required | Constraints |
|---|---|:---:|---|
| Guardian / Parent Name | Text | No | Max 100 characters |
| Guardian Contact Number | Text | No | Max 30 characters |

#### Section D — Internship Details

| Field | Input Type | Required | Options / Constraints |
|---|---|:---:|---|
| Department | Dropdown | Yes | All departments from `departments` table, A–Z order |
| Supervisor | Text | No | Max 100 characters |
| Start Date | Date picker | No | — |
| End Date | Date picker | No | — |
| Required Hours | Number | No | Min 1, step 0.5, default 486 |

> Changing the department moves the intern's record. DTR entries and requirements are not affected.

#### Profile Tab Actions

| Button | Access | Condition | Action |
|---|---|---|---|
| Save Changes | Admin, HR Staff | Always visible | POST `action=update_profile` → updates all fields → `UPDATE` audit log → redirect to `tab=201` |
| Export PDF | Admin, HR Staff | Always visible | Opens `/api/export_requirements.php?intern_id={id}` in a new browser tab |
| Archive Intern | Admin, HR Staff | Only if `status = 'Active'` | Opens Archive Intern confirmation modal |

**Archive Intern Modal:**
- Content: *"Archive [First Last]? The record will be preserved and can be restored later."*
- Buttons: Cancel · Archive (red)
- On confirm: POST `action=archive_from_profile` → `status = 'Archived'` → `ARCHIVE` audit log → redirect to `tab=201`

---

### 10.2 Tab 2 — DTR (Daily Time Record)

**URL param:** `tab=dtr`

#### DTR Toolbar — Left (Date Filter)

| Control | Type | Behavior |
|---|---|---|
| From | Date picker | Filters entries where `entry_date >= value` |
| To | Date picker | Filters entries where `entry_date <= value` |
| Filter button | Submit | Applies filter, reloads tab |
| Reset link | Anchor | Clears filter → `/intern_workspace.php?id={id}&tab=dtr` |

#### DTR Toolbar — Right (Action Buttons)

| Button | Access | Condition | Action |
|---|---|---|---|
| Add Entry | Admin, HR Staff | Always visible | Opens Add DTR Entry modal |
| PDF | Admin, HR Staff | Always visible | Opens `/api/export_dtr.php?intern_id={id}&format=pdf` (includes date range params if filter active) |
| CSV | Admin, HR Staff | Always visible | Downloads `/api/export_dtr.php?intern_id={id}&format=csv` |
| COC | Admin, HR Staff | Only when `rendered_hours >= required_hours` | Opens `/api/export_coc.php?intern_id={id}` in new tab |

#### DTR Summary Cards

Three stat cards below the toolbar:

| Card | Value |
|---|---|
| Rendered Hours | `rendered_hours` from `interns` table, 2 decimal places |
| Remaining Hours | `MAX(0, required_hours − rendered_hours)`, 2 decimal places |
| Entries | Count of `dtr_entries` currently shown. Shows "(filtered)" label if date range is active |

#### DTR Table

Entries ordered by `entry_date ASC, id ASC`.

| Column | Content | Editable |
|---|---|---|
| # | Row number | No |
| Date | `entry_date` (YYYY-MM-DD) | No |
| Time In | Inline time input | Yes — saves on `change` event |
| Time Out | Inline time input | Yes — saves on `change` event |
| Rendered Hrs | Auto-calculated, 2 decimal places. Shows `0.00` for non-working remarks | No |
| Overtime | Auto-calculated, 2 decimal places. Orange text if > 0. Shows `0.00` for non-working remarks | No |
| Remarks | Inline dropdown | Yes — saves immediately on `change` |
| Actions | Delete (trash) icon | — |

**Inline Time In / Time Out behavior:**
- Disabled and grayed out (opacity 0.35) when remark is Absent, Holiday, No Office, or Excused
- On `change`: fires `saveDtrEdit(id, input)` → POST `action=edit_dtr` → updates `time_in` and `time_out`
- Validation: Time Out must be greater than Time In

**Inline Remarks dropdown — all options:**

| Value | Label | Hours Counted? | Clears Time In/Out? |
|---|---|:---:|:---:|
| *(empty)* | — None — | Yes | No |
| Half Day | Half Day | Yes | No |
| Excused | Excused | No | Yes |
| Absent | Absent | No | Yes |
| Holiday | Holiday | No | Yes |
| No Office | No Office | No | Yes |

On `change`: fires `saveDtrRemark(id, value)` → POST `action=edit_dtr_remark`. If a non-working remark is selected, `time_in` and `time_out` are set to NULL in the database and the Time In/Time Out inputs are cleared and disabled in the UI immediately without a page reload.

**Delete button:**
Fires `deleteDtrEntry(id)` → browser `confirm()` dialog → POST `action=delete_dtr` → row removed from DOM → rendered hours recalculated → page reloads after 600ms.

#### Add DTR Entry Modal

**Trigger:** "Add Entry" button

| Field | Input Type | Required | Notes |
|---|---|:---:|---|
| Date | Date picker | Yes | Duplicate dates for the same intern are rejected |
| Remarks | Dropdown | No | Same 6 options as the inline table dropdown |
| Time In | Time picker | Conditional | Visible only when remark is None or Half Day |
| Time Out | Time picker | Conditional | Visible only when remark is None or Half Day |

When a non-working remark is selected, the Time In/Time Out fields are hidden and an info banner appears: *"No time entry needed for this remark type."*

**Client-side validation:**
- Date field must not be empty
- Time In and Time Out required when remark is None or Half Day
- Time Out must be later than Time In

**Server-side validation:**
- Duplicate `entry_date` check for the same `intern_id`
- Time Out > Time In check
- Remark whitelist validation

**Buttons:** Cancel · Add Entry

On success: modal closes → success toast → page reloads after 600ms → `CREATE` logged to audit trail.
On failure: inline error message shown inside the modal.

---

### 10.3 Tab 3 — Requirements

**URL param:** `tab=reqs`

#### Requirements Toolbar

**Left — Status summary badges:**
- X Approved (green badge)
- X Submitted (blue badge)
- X Pending (yellow badge)

**Right — Action buttons:**

| Button | Condition | Action |
|---|---|---|
| Add Requirement | Always visible | Opens Add Requirement modal |
| Export PDF | Always visible | Opens `/api/export_requirements.php?intern_id={id}` in a new tab |
| Archived (X) | Only shown if archived requirements exist | Toggles the archived section below the main table |

#### Requirements Table

Active requirements ordered by `created_at ASC`.

| Column | Content | Editable |
|---|---|---|
| Requirement | Requirement name (bold) | No |
| Status | Inline dropdown | Yes — saves on `change` |
| Date Submitted | `submission_date` from DB, or "—" | No |
| Remarks | Inline text input | Yes — saves on `blur` (auto-save) |
| File | Image thumbnail or PDF icon, click to preview | No |
| Actions | Upload icon · View icon · Archive icon | — |

**Status inline dropdown options:**

| Value | Meaning |
|---|---|
| Pending | Document not yet submitted by the intern |
| Submitted | Intern has submitted the document |
| Approved | Document reviewed and accepted by HR |

On `change`: fires `updateReqStatus(id, value)` → POST `action=update_status` → updates `status` and `status_changed_at` in DB.

**Remarks inline text input:**
- Max 500 characters
- On `blur` (when user clicks away): fires `updateReqRemarks(id, value)` → POST `action=update_remarks`
- Saves without page reload

**File column behavior:**
- Image file (JPG/PNG): 36×36px thumbnail. Click opens image preview in file viewer modal
- PDF file: orange PDF icon. Click opens PDF in iframe inside file viewer modal
- No file: shows "No file" text label

**Actions per row:**

| Icon | Trigger | Action |
|---|---|---|
| Upload (arrow-up) | Hidden file input on click | POST `action=upload_file`. Accepts PDF, JPEG, PNG, DOCX. Max 10MB. Page reloads on success |
| View (eye) | Button click | Opens file viewer modal. Grayed/disabled if no file |
| Archive (box) | Button click | Opens Archive Requirement confirmation modal |

#### Add Requirement Modal

| Field | Input Type | Required | Notes |
|---|---|:---:|---|
| Requirement | Dropdown | Yes | Preset options — see list below |
| Custom Name | Text input | Conditional | Shown only when "Other" is selected from dropdown |

**Requirement dropdown preset options:**
- — Select Requirement — *(placeholder, value = "")*
- Endorsement Letter
- Letter of Intent
- MOA
- School ID
- Proof of Registration
- School Schedule
- Parent Consent
- Barangay Clearance
- Other *(shows custom text input below when selected)*

**Buttons:** Cancel · Add

On success: modal closes → toast → page reloads after 600ms → `CREATE` logged to audit trail.

#### File Viewer Modal

Opens when clicking an image thumbnail or eye icon in the file column.

- Header shows the requirement name
- Download button always available in the header (opens file URL in new tab)
- **Image (JPG/PNG):** rendered as `<img>` with `max-height: 65vh`
- **PDF:** embedded in an `<iframe>` with height 65vh
- **DOCX:** shows Word icon, file name, and a Download button (cannot be previewed inline)

#### Archive Requirement Modal

- Content: *"Archive [Requirement Name]? It will be hidden from the active list but preserved in storage."*
- Buttons: Cancel · Archive (red)
- On confirm: POST `action=archive_requirement` → `is_archived = 1` → row removed from table → `ARCHIVE` logged to audit trail

#### Archived Requirements Section

Toggled by the "Archived (X)" button. Shown as a separate table below the active table.

**Archived table columns:** Requirement name · Status badge (grayed) · Date Submitted · File view button · Restore button

**Restore button:** POST `action=restore_requirement` → `is_archived = 0` → page reloads → `RESTORE` logged to audit trail

---

## 11. Module 6 — MOA Management

**URL:** `/moa.php`
**Access:** Admin, HR Staff

### 11.1 Overview

The MOA module stores and tracks Memorandum of Agreement records between TDT Powersteel and partner schools or universities.

### 11.2 Status Summary Cards

Five clickable stat cards at the top, each linking to a pre-filtered view:

| Status | Color | Description |
|---|---|---|
| Active | Green | Agreement is current and in force |
| Expired | Red | Agreement has lapsed |
| For Verification | Blue | Pending confirmation or review |
| On Process | Amber | Being drafted or processed |
| For Renewal | Orange | Needs to be renewed |

### 11.3 Toolbar — Search & Filter

| Control | Type | Behavior |
|---|---|---|
| Search box | Text input | Searches `school_name LIKE '%value%'` |
| Status dropdown | Select | Filters by `status` field |
| Search button | Submit | Applies filters |
| Reset button | Link | Clears all filters |
| Archived toggle | Link button | Switches between active and archived view |

**Status dropdown options:**
- All Statuses *(default)*
- Active
- Expired
- For Verification
- On Process
- For Renewal

### 11.4 MOA Table

Active records ordered by `seq ASC, school_name ASC`. Expired and For Renewal rows are highlighted with a light red background.

| Column | Content |
|---|---|
| SEQ | Optional sort order number |
| School / University | School name (red text if expired/for renewal) |
| Validity | Duration text (e.g., "3 years") |
| Start | `period_start` formatted as "Mon DD, YYYY" |
| End | `period_end` formatted as "Mon DD, YYYY" (red text if expired/for renewal) |
| Status | Colored badge |
| Remarks | Free-text remarks |
| File | Eye icon to view file, or "No file" |
| Actions | Edit (pencil) · Archive or Restore icon |

### 11.5 Add MOA Modal

**Trigger:** "Add MOA" button in the page header

| Field | Input Type | Required | Options / Constraints |
|---|---|:---:|---|
| SEQ | Number input | No | Optional sort order |
| School / University | Text | Yes | Max 200 characters |
| Validity | Text | No | Max 50 chars (e.g., "3 years", "For Verification") |
| Status | Dropdown | No | On Process · Active · For Verification · Expired · For Renewal |
| Period Start | Date picker | No | Start of agreement |
| Period End | Date picker | No | End of agreement |
| Remarks | Text | No | Max 255 characters (e.g., "Pending to Receive") |
| MOA File | File upload | No | PDF, JPEG, PNG, DOCX · Max 20MB |

**Buttons:** Cancel · Add

On save: record inserted, `CREATE` logged, redirect to `/moa.php`.

### 11.6 Edit MOA Modal

**Trigger:** Pencil icon on any MOA row

Same fields as Add MOA, all pre-filled. File upload field has note: *(leave blank to keep existing)*. Current file name is shown below the upload field.

**Buttons:** Cancel · Save Changes

On save: record updated, `UPDATE` logged, redirect to `/moa.php`.

### 11.7 Archive & Restore

**Archive:** Archive icon on any active row → `confirm()` prompt → POST `action=delete_moa` → `is_archived = 1` → `ARCHIVE` logged

**Restore:** Shown only in archived view. Restore icon → POST `action=restore_moa` → `is_archived = 0` → `RESTORE` logged

**Archived view:** Toggled by the "Archived" button in the toolbar. Shows all records where `is_archived = 1`.

---

## 12. Module 7 — Policy Hub

**URL:** `/policies.php`
**Access:** Admin, HR Staff (view only)

### 12.1 Overview

The Policy Hub displays TDT Powersteel's official OJT/intern policies sourced from the On-The-Job Training Agreement. All content is read-only for system users. Policy records are managed directly in the `intern_policies` database table.

### 12.2 Policy Banner

A notice banner at the top of the page:
*"TDT Powersteel Corporation — On-The-Job Training Agreement. All interns are expected to be familiar with and abide by the following policies."*

### 12.3 Policy Categories

Policies are grouped by category, each rendered as a card section with a colored header. Categories and their colors:

| Category | Color | Icon |
|---|---|---|
| Traineeship Terms | Blue (info) | fa-handshake |
| Attendance & Schedule | Orange | fa-calendar-check |
| Dress Code | Purple (#8B5CF6) | fa-tshirt |
| Conduct & Performance | Red (danger) | fa-shield-alt |
| Trainer & Supervision | Green (success) | fa-chalkboard-teacher |
| Compensation | Gray | fa-ban |

Each policy item within a category shows: icon, title (bold), and content text.

### 12.4 Footer Note

*"These policies are sourced from the official TDT Powersteel On-The-Job Training Agreement. For questions, contact the HR & Admin department."*

---

## 13. Module 8 — Reports & Export

**URL:** `/reports.php`
**Access:** Admin, HR Staff

### 13.1 Quick Export Cards

Three export shortcut cards at the top of the page:

**Card 1 — All Interns**
- Description: Full intern list with hours
- Buttons: PDF · CSV
- PDF action: opens `/api/export_interns.php?format=pdf` in a new tab
- CSV action: downloads `/api/export_interns.php?format=csv`

**Card 2 — DTR by Intern**
- Description: Select an intern to export their DTR
- Controls: Intern select dropdown + PDF button + CSV button
- PDF action: opens `/api/export_dtr.php?intern_id={id}&format=pdf`
- CSV action: downloads `/api/export_dtr.php?intern_id={id}&format=csv`
- If no intern selected: toast warning shown

**Card 3 — Requirements by Intern**
- Description: Select an intern to export their requirements checklist
- Controls: Intern select dropdown + PDF button
- PDF action: opens `/api/export_requirements.php?intern_id={id}&format=pdf`
- If no intern selected: toast warning shown

**Intern dropdown (Cards 2 & 3):**
- — Select Intern — *(placeholder)*
- [All interns listed as "First Last", ordered by department then last name then first name]

### 13.2 Department Summary Table

Shows aggregated statistics per department, ordered A–Z.

| Column | Content |
|---|---|
| Department | Department name |
| Active Interns | COUNT of active interns in that department |
| Total Rendered Hrs | SUM of rendered_hours for active interns |
| Total Required Hrs | SUM of required_hours for active interns |
| Avg Completion | Progress bar + percentage |

### 13.3 All Interns Overview Table

A full table of all interns across all departments and statuses.

| Column | Content |
|---|---|
| Name | Clickable orange link → opens intern workspace |
| Department | Department name |
| Status | Colored badge |
| Start | `start_date` or "—" |
| End | `end_date` or "—" |
| Rendered | `rendered_hours` (1 decimal) |
| Required | `required_hours` |
| Progress | Progress bar + percentage |

### 13.4 Export Output Details

**All Interns — PDF**
- Title: "TDT Powersteel Corp. — Intern List"
- Columns: #, Name, Department, School, Status, Required, Rendered, Remaining
- Print/Save as PDF button (browser print dialog)

**All Interns — CSV**
- Filename: `Interns_TDTPowersteel_{YYYYMMDD}.csv`
- Columns: Last Name, First Name, Email, Phone, Department, School, Course, Year Level, Status, Required Hrs, Rendered Hrs, Remaining Hrs, Start Date, End Date, Supervisor

**DTR — PDF**
- Full company letterhead (logo, address, contact)
- Intern info header: Name, Department, School, Course, Required, Rendered, Remaining, Generated timestamp, Period (if filtered)
- DTR table with colored remark badges
- Total row: sums Rendered Hrs and Overtime columns
- Print/Save as PDF button

**DTR — CSV**
- Filename: `DTR_{First_Last}_{YYYYMMDD}.csv`
- Header rows with intern info, then column headers, then entries, then TOTAL row

**Requirements — PDF**
- Per-intern requirements checklist with status, submission date, and remarks
- Print/Save as PDF button

**Certificate of Completion (COC)**
- Only accessible when `rendered_hours >= required_hours`
- Full formal letterhead and COC layout
- Intern name in ALL CAPS with middle initial (e.g., JUAN D. DELA CRUZ)
- Correct gender pronouns (he/she) and salutation (Mr./Ms.) based on `gender` field
- Date range: `start_date` to last DTR `entry_date` (or `end_date` if no DTR entries)
- Signatory: Monaliza R. Acuña, CPA, MIR — AVP for Finance and Accounting / HR & Admin Officer-in-charge
- Watermark: *"NOT VALID WITHOUT THE SIGN OF IMMEDIATE HEAD"*
- Print/Save as PDF button

---

## 14. Module 9 — Audit Trail

**URL:** `/audit.php`
**Access:** Admin only

HR Staff who navigate to this URL are redirected to `/dashboard.php`.

### 14.1 Overview

The Audit Trail provides a complete, read-only chronological log of all significant actions performed in the system. It is used for accountability, traceability, and compliance purposes.

### 14.2 Filter Bar

| Control | Type | Behavior |
|---|---|---|
| Date From | Date picker | Filters `DATE(created_at) >= value` |
| Date To | Date picker | Filters `DATE(created_at) <= value` |
| User text input | Text | Filters `user_name LIKE '%value%'` |
| Action dropdown | Select | Filters by exact `action` value |
| Filter button | Submit | Applies all active filters |
| Reset button | Link | Clears all filters, reloads page |

**Action dropdown options:**
- All Actions *(default)*
- CREATE
- UPDATE
- ARCHIVE
- RESTORE
- DELETE
- LOGIN
- LOGOUT
- LOCK
- REGISTER_FACE
- [Any other distinct values found in the `audit_trail.action` column]

### 14.3 Audit Log Table

Displays up to 500 records, ordered by `created_at DESC` (newest first).

| Column | Content |
|---|---|
| Timestamp (UTC) | `created_at` datetime value |
| User | `user_name` from audit record |
| Action | Colored badge per action type |
| Module | System area (e.g., Interns, DTR, MOA, Users, Auth) |
| Record ID | Database ID of the affected record, or "—" |
| Description | Human-readable summary of what occurred |

**Action badge colors:**

| Action | Badge Style |
|---|---|
| CREATE | Green (approved) |
| UPDATE | Blue (submitted) |
| ARCHIVE | Gray (archived) |
| RESTORE | Green (approved) |
| DELETE | Red (pending/danger) |
| LOGIN | Blue (submitted) |
| LOGOUT | Gray (archived) |
| LOCK | Red (pending/danger) |
| REGISTER_FACE | Blue (submitted) |

### 14.4 What Gets Logged

Every call to `logAudit($action, $module, $recordId, $description)` creates one row in `audit_trail`. The following actions are logged throughout the system:

| Action | Module | Trigger |
|---|---|---|
| LOGIN | Auth | Successful login |
| LOGOUT | Auth | User clicks Logout |
| LOCK | Users | Account locked after 5 failed attempts |
| CREATE | Departments | New department added |
| UPDATE | Departments | Department renamed |
| CREATE | Interns | New intern profile saved |
| UPDATE | Interns | Intern profile updated |
| ARCHIVE | Interns | Intern archived |
| CREATE | DTR | New DTR entry added |
| UPDATE | DTR | DTR time or remark updated |
| DELETE | DTR | DTR entry deleted |
| CREATE | Requirements | New requirement added |
| UPDATE | Requirements | Requirement status, remarks, or file updated |
| ARCHIVE | Requirements | Requirement archived |
| RESTORE | Requirements | Requirement restored |
| CREATE | MOA | New MOA record added |
| UPDATE | MOA | MOA record edited |
| ARCHIVE | MOA | MOA record archived |
| RESTORE | MOA | MOA record restored |
| CREATE | Users | New user account created |
| UPDATE | Users | User unlocked or password changed |
| UPDATE | Settings | Shift/hours settings saved |
| REGISTER_FACE | Interns | Intern completed face registration |

---

## 15. Module 10 — Settings

**URL:** `/settings.php`
**Access:** Admin only

HR Staff who navigate to this URL are redirected to `/dashboard.php` via `requireRole('admin')`.

### 15.1 System Users Table

Lists all user accounts in the system, ordered by name A–Z.

| Column | Content |
|---|---|
| Name | Full name (bold) |
| Email | Email address |
| Role | Badge: Admin (green) or Hr Staff (blue) |
| Status | Badge: Active (green) or Locked (red with lock icon) |
| Created | Account creation timestamp |
| Actions | Unlock icon — shown only on locked accounts |

### 15.2 Add User

**Trigger:** "Add User" button in the System Users card header

**Modal — Add User:**

| Field | Input Type | Required | Validation |
|---|---|:---:|---|
| Full Name | Text | Yes | Max 100 characters |
| Email | Email | Yes | Must be a valid email, must be unique in the `users` table |
| Password | Password | Yes | Minimum 8 characters |
| Role | Dropdown | No | HR Staff (default) · Admin |

**Role dropdown options:**
- HR Staff *(default, value = `hr_staff`)*
- Admin *(value = `admin`)*

**Buttons:** Cancel · Create User

On save: password is hashed with `bcrypt`, record inserted into `users`, `CREATE` logged to audit trail. Success message shown. If email already exists: error shown.

### 15.3 Unlock Account

Shown as an unlock icon in the Actions column for any locked user row.

- POST `action=unlock_user` with `user_id`
- Sets `is_locked = 0`, `fail_count = 0`
- `UPDATE` logged to audit trail
- Success message: *"Account unlocked."*

### 15.4 Shift & Hours Customization

A form for configuring global DTR calculation settings. Settings are stored as key-value pairs in the `system_settings` table.

| Setting | Input Type | Default | Constraints | Key in DB |
|---|---|---|---|---|
| Deduct lunch break | Checkbox (toggle) | Off | On / Off | `lunch_break_enabled` |
| Lunch Break Duration | Number (minutes) | 60 | 0–120, step 5 | `lunch_break_minutes` |
| Standard Daily Hours | Number (hours) | 8 | 1–12, step 0.5 | `standard_hours` |

**Lunch break deduction:** When enabled, the configured minutes are deducted from the rendered hours of every new DTR entry. Affects `lunch_break_mins` column on `dtr_entries`.

**Standard daily hours:** Used as the threshold for computing overtime. Any hours worked above this value in a single DTR entry are counted as overtime.

**Button:** Save Settings

On save: each key-value pair is upserted (`INSERT ... ON DUPLICATE KEY UPDATE`), `UPDATE` logged to audit trail. Success message shown.

### 15.5 Change Password

Allows the currently logged-in Admin to change their own password.

| Field | Input Type | Required | Validation |
|---|---|:---:|---|
| Current Password | Password | Yes | Must match the stored bcrypt hash |
| New Password | Password | Yes | Minimum 8 characters |
| Confirm New Password | Password | Yes | Must match New Password |

**Button:** Update Password

On save: password updated with new bcrypt hash, `UPDATE` logged to audit trail. Success or error message shown inline.

> This only changes the logged-in user's own password. To reset another user's password, that user must do it themselves, or a developer must update the hash directly in the database.

---

## 16. Intern Self-Registration

**URL:** `/register_intern.php?token={token}`
**Access:** Public — no login required, token-gated

This page is used by interns (not system users) to complete their own onboarding. It is accessed via a unique registration link sent by HR.

### 16.1 Token Validation

On page load, the `token` query parameter is checked against the `interns` table:
- Token must exist in `registration_token`
- `token_expires_at` must be greater than `NOW()` (24-hour TTL)

If invalid or expired: a full-page error state is shown with the message *"This registration link is invalid or has expired. Registration links expire 24 hours after generation. Please contact HR to get a new link."*

### 16.2 Registration Flow

**Step 1 — Model Loading**

On valid token, a loading overlay is shown while MediaPipe Face Landmarker loads in the background (WASM/GPU via CDN). The overlay fades out when the model is ready or if it fails to load.

**Step 2 — Onboarding Tutorial Modal**

Before the camera section, a 4-slide tutorial modal is shown automatically:

| Slide | Title | Instruction |
|---|---|---|
| 1 of 4 | Look Straight | Face centered, look directly at camera. Remove glasses/masks/hats. |
| 2 of 4 | Slightly Right | Rotate head slightly to the right |
| 3 of 4 | Slightly Left | Rotate head slightly to the left |
| 4 of 4 | Tilt Up | Tilt chin and head slightly upward |

Guide images are gender-specific based on the intern's `gender` field (suffix `_f` for Female, `_m` for Male/Other).

Navigation: Next button + dot indicators + "Skip Tutorial" text link.

**Step 3 — Email Confirmation**

| Field | Input Type | Required | Notes |
|---|---|:---:|---|
| Email Address | Email | Yes | Pre-filled if already in DB. Must be active and accessible. QR code will be sent here. |

Email is validated for format and uniqueness (checked against other active interns).

Proceed button opens the camera section.

**Step 4 — Face Capture**

The camera section shows a live video feed with:
- Circular guide overlay (SVG)
- Scanning ring animation
- Step dots (4 steps)
- Step title and instruction text
- Camera hint: *"Remove glasses, masks, or hats to ensure accuracy."*
- Capture Angle button

The intern captures 4 facial angles guided by MediaPipe:
1. Look Straight
2. Slightly Right
3. Slightly Left
4. Tilt Up

Each capture takes a 224×224px JPEG frame from the video feed. 5 total embeddings are generated (the straight angle is captured once extra to meet the required count of 5).

If MediaPipe fails to load, the system falls back to manual capture mode.

**Step 5 — Submission**

A "Processing Biometrics" state is shown while:
1. The 5 base64-encoded JPEG images are POSTed to the Python face embedding service at `http://localhost:5001/embed`
2. The service returns 5 arrays of 512 floats (face embeddings)
3. A unique QR code is generated: `TDTINTRN{id}-{4-digit-random}`
4. The embeddings JSON and QR code are saved to the `interns` table
5. `registration_token` and `token_expires_at` are cleared

Timeout for the embedding service: 60 seconds (to allow for queuing during peak times).

**Step 6 — Success Screen**

Shows an animated envelope icon with the message:
*"Check Your Email! We have sent your unique attendance QR code to your email. You can also download it directly below."*

- QR code image displayed (from `api.qrserver.com`)
- Download QR Code button
- "How to Clock In/Out at the Kiosk" instructions:
  1. Present QR Code to kiosk camera
  2. Face Verification — look at kiosk screen
  3. Confirmation — time log saved automatically

### 16.3 Error Handling

| Error Condition | Message Shown |
|---|---|
| Face service offline | *"Face service offline. Please try again later."* |
| Less than 5 clear face captures | *"Failed to process all face angles. Please retry."* |
| Invalid embedding shape | *"Invalid embedding shape returned from face service."* |
| Camera permission denied | Camera Error modal with instructions for in-app browsers (Messenger/Viber) and permission grant steps |
| Database save failure | *"Database save failure. Please contact HR."* |
| Duplicate email | *"This email is already registered."* |

---

## 17. Hours Calculation Logic

### 17.1 Rendered Hours Formula

Rendered hours per DTR entry are calculated as a **generated stored column** in the `dtr_entries` table:

```sql
rendered_hours = ROUND(TIME_TO_SEC(TIMEDIFF(time_out, time_in)) / 3600, 2)
```

This gives a decimal result to 2 places. Examples:

| Time In | Time Out | Rendered Hours |
|---|---|---|
| 08:00 | 17:00 | 9.00 |
| 08:00 | 12:30 | 4.50 |
| 08:00 | 16:30 | 8.50 |

If `time_in` or `time_out` is NULL, or if `time_out <= time_in`, rendered hours = 0.00.

### 17.2 Overtime Formula

Overtime is also a generated stored column:

```sql
overtime = rendered_hours - 8.00   (if rendered_hours > 8, else 0)
```

The threshold of 8 is the default `standard_hours` system setting. Note: the column is generated using the hardcoded value 8 in the schema. The `standard_hours` setting in `system_settings` is used for display and reporting purposes.

### 17.3 Undertime Formula

```sql
undertime = 8.00 - rendered_hours   (if rendered_hours < 8, else 0)
```

### 17.4 Non-Working Remarks

The following remarks result in **0 rendered hours** regardless of time values:

| Remark | Hours Counted |
|---|---|
| Absent | 0 |
| Holiday | 0 |
| No Office | 0 |
| Excused | 0 |

When any of these remarks are applied (inline or via Add Entry), `time_in` and `time_out` are set to NULL in the database, ensuring the generated column also returns 0.

### 17.5 Decimal Hours Format

Hours are stored and displayed in decimal format, not HH:MM. This is intentional for mathematical summation.

**Conversion reference:**
- Minutes ÷ 60 = decimal part
- 30 minutes = 0.50 hours
- 45 minutes = 0.75 hours
- 8 hours 30 minutes = 8.50 hours
- 481 hours 54 minutes = 481.90 hours

### 17.6 Total Rendered Hours

The `interns.rendered_hours` column is a regular (non-generated) column updated via a recalculation query whenever a DTR entry is added, edited, or deleted:

```sql
UPDATE interns
SET rendered_hours = (
    SELECT COALESCE(SUM(rendered_hours), 0)
    FROM dtr_entries
    WHERE intern_id = {id} AND is_archived = 0
)
WHERE id = {id}
```

### 17.7 Lunch Break Deduction

When `lunch_break_enabled = '1'` in `system_settings`, the configured `lunch_break_minutes` value is stored in the `lunch_break_mins` column of each new `dtr_entries` row. The actual deduction from displayed/exported hours is applied at the application layer when this setting is active.

---

## 18. Database Tables Reference

Database name: `tdt_ims` · Character set: `utf8mb4` · Collation: `utf8mb4_unicode_ci`

---

### 18.1 `users`

Stores all system user accounts (Admin and HR Staff).

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| name | VARCHAR(100) | Full name |
| email | VARCHAR(150) UNIQUE | Login email |
| password | VARCHAR(255) | bcrypt hash |
| role | ENUM('admin','hr_staff') | Default: hr_staff |
| is_locked | TINYINT(1) | 0 = active, 1 = locked |
| fail_count | TINYINT(3) | Consecutive failed logins, resets on success |
| created_at | DATETIME | Auto-set on insert |
| updated_at | DATETIME | Auto-updated on change |

Default seed: `admin@tdtpowersteel.com` / `Admin@1234` (Admin role)

---

### 18.2 `departments`

Stores organizational departments.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| name | VARCHAR(100) UNIQUE | Department name |
| created_at | DATETIME | Auto-set on insert |

Default seed: HR & Admin · Sales and Marketing · Business Development · Operations Management · Accounting

---

### 18.3 `interns`

Core table. Stores all intern records.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| department_id | INT UNSIGNED FK | References `departments.id` |
| first_name | VARCHAR(80) | Required |
| last_name | VARCHAR(80) | Required |
| middle_name | VARCHAR(80) | Nullable |
| email | VARCHAR(150) | Nullable |
| registration_token | VARCHAR(64) UNIQUE | Nullable. Cleared after face registration |
| token_expires_at | DATETIME | Nullable. 24-hour TTL |
| phone | VARCHAR(30) | Nullable |
| address | TEXT | Nullable |
| birthdate | DATE | Nullable |
| gender | ENUM('Male','Female','Other') | Nullable |
| nationality | VARCHAR(60) | Nullable |
| civil_status | VARCHAR(20) | Nullable |
| guardian_name | VARCHAR(100) | Nullable |
| guardian_contact | VARCHAR(30) | Nullable |
| school | VARCHAR(150) | Nullable |
| course | VARCHAR(150) | Nullable |
| year_level | VARCHAR(30) | Nullable |
| school_address | VARCHAR(255) | Nullable |
| required_hours | DECIMAL(6,2) | Default: 486.00 |
| rendered_hours | DECIMAL(6,2) | Default: 0.00. Updated by application after each DTR change |
| start_date | DATE | Nullable |
| end_date | DATE | Nullable |
| supervisor | VARCHAR(100) | Nullable |
| status | ENUM('Active','Archived') | Default: Active |
| profile_photo | VARCHAR(255) | Filename in `/uploads/photos/` |
| face_embedding | LONGTEXT | JSON array of 5 × 512-float arrays |
| qr_code | VARCHAR(100) UNIQUE | Format: `TDTINTRN{id}-{4-digit}` |
| face_registered_at | DATETIME | Nullable |
| created_at | DATETIME | Auto-set on insert |
| updated_at | DATETIME | Auto-updated on change |

---

### 18.4 `dtr_entries`

Stores daily attendance records for each intern.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| intern_id | INT UNSIGNED FK | References `interns.id` |
| entry_date | DATE | One entry per date per intern |
| time_in | TIME | Nullable |
| time_out | TIME | Nullable |
| rendered_hours | DECIMAL(5,2) GENERATED STORED | Calculated from time_in/time_out |
| overtime | DECIMAL(5,2) GENERATED STORED | rendered_hours − 8 if > 8, else 0 |
| undertime | DECIMAL(5,2) GENERATED STORED | 8 − rendered_hours if < 8, else 0 |
| remarks | VARCHAR(50) | One of: '' / Half Day / Excused / Absent / Holiday / No Office |
| lunch_break_mins | INT | Lunch deduction in minutes (0 if not applicable) |
| is_archived | TINYINT(1) | Default: 0 |
| entry_source | ENUM('manual','kiosk') | Default: manual |
| created_at | DATETIME | Auto-set on insert |
| updated_at | DATETIME | Auto-updated on change |

---

### 18.5 `requirement_items`

Tracks per-intern document requirements.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| intern_id | INT UNSIGNED FK | References `interns.id` |
| name | VARCHAR(200) | Requirement name |
| status | ENUM('Pending','Submitted','Approved') | Default: Pending |
| status_changed_at | DATETIME | Updated when status changes |
| submission_date | DATE | Set when file is uploaded |
| file_path | VARCHAR(255) | Filename in `/uploads/requirements/` |
| file_name | VARCHAR(255) | Original filename shown in UI |
| remarks | TEXT | Free-text notes, max 500 chars enforced at app layer |
| is_archived | TINYINT(1) | Default: 0 |
| created_at | DATETIME | Auto-set on insert |
| updated_at | DATETIME | Auto-updated on change |

---

### 18.6 `moa_agreements`

Stores MOA records for partner schools.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| seq | INT | Optional sort order |
| school_name | VARCHAR(200) | Required |
| validity | VARCHAR(50) | e.g., "3 years" |
| period_start | DATE | Nullable |
| period_end | DATE | Nullable |
| status | ENUM | Active / Expired / For Verification / On Process / For Renewal |
| remarks | VARCHAR(255) | Nullable |
| file_path | VARCHAR(255) | Filename in `/uploads/moa/` |
| file_name | VARCHAR(255) | Original filename |
| is_archived | TINYINT(1) | Default: 0 |
| created_at | DATETIME | Auto-set on insert |

---

### 18.7 `intern_policies`

Stores OJT policy content displayed in the Policy Hub.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| category | VARCHAR(100) | e.g., "Attendance & Schedule" |
| title | VARCHAR(200) | Policy title |
| content | TEXT | Policy body text |
| icon | VARCHAR(50) | Font Awesome class (e.g., "fa-clock") |
| sort_order | INT | Display order within category |
| is_active | TINYINT(1) | 1 = shown in Policy Hub |

---

### 18.8 `system_settings`

Key-value store for global system configuration.

| Column | Type | Notes |
|---|---|---|
| setting_key | VARCHAR(100) PK | Unique key name |
| setting_val | VARCHAR(255) | String value |

**Current keys:**

| Key | Default Value | Description |
|---|---|---|
| lunch_break_enabled | 0 | 1 = deduct lunch break from DTR entries |
| lunch_break_minutes | 60 | Duration in minutes to deduct |
| standard_hours | 8 | Daily hours threshold for overtime calculation |

---

### 18.9 `audit_trail`

Logs all significant system actions.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | Auto-increment |
| user_id | INT UNSIGNED FK | References `users.id`, SET NULL on delete |
| user_name | VARCHAR(100) | Snapshot of name at time of action |
| action | VARCHAR(50) | e.g., CREATE, UPDATE, DELETE, LOGIN |
| module | VARCHAR(50) | e.g., Interns, DTR, MOA, Auth |
| record_id | INT UNSIGNED | Affected record's ID, nullable |
| description | TEXT | Human-readable action summary |
| created_at | DATETIME | Auto-set on insert |

---

## 19. Troubleshooting & FAQ

### 19.1 Login Issues

| Problem | Cause | Solution |
|---|---|---|
| Cannot log in — wrong credentials | Incorrect email or password | Double-check email. Make sure CAPS LOCK is off. |
| "Account locked" message | 5 consecutive failed login attempts | Ask an Admin to unlock the account from Settings |
| "Session expired" message | Session was idle for more than 30 minutes | Log in again — this is normal security behavior |
| Already logged in but redirected to login | Session cookie was cleared (browser restart or private mode) | Log in again |

### 19.2 Dashboard

| Problem | Solution |
|---|---|
| Stat cards show 0 | No active interns exist yet. Check Intern Management and verify interns have `Active` status |
| Departments grid is empty | No departments have been created. Ask an Admin to add departments in the Departments module |
| Policy Hub widget is empty | No active policies in the database. Contact the developer to seed the `intern_policies` table |

### 19.3 Intern Management & Workspace

| Problem | Solution |
|---|---|
| Intern not found in the list | Check the status tab — they may be under Inactive or Archived |
| Profile photo not showing | Verify the file was uploaded as JPEG or PNG and is under 5MB |
| Cannot save profile — first/last name error | First name and last name are required fields |
| Archive button not visible | The intern's status is already Archived or Inactive — Archive is only shown for Active interns |

### 19.4 DTR

| Problem | Solution |
|---|---|
| "An entry for this date already exists" | Only one DTR entry is allowed per date per intern. Edit the existing entry instead |
| "Time Out must be later than Time In" | Correct the time values — time_out must be strictly greater than time_in |
| Rendered hours show 0 for a normal entry | Check the Remarks column — a non-working remark (Absent, Holiday, etc.) may have been applied |
| COC button not visible | The intern's rendered hours have not yet reached the required hours |
| Total hours not updating | The total is recalculated automatically after every add/edit/delete. Try refreshing the page |

### 19.5 Requirements

| Problem | Solution |
|---|---|
| File upload fails | Ensure the file is PDF, JPEG, PNG, or DOCX and does not exceed 10MB |
| File viewer shows blank | The file may have been deleted from the server. Re-upload the file |
| Remarks not saving | Remarks save on blur — click outside the input field to trigger the save |

### 19.6 MOA Management

| Problem | Solution |
|---|---|
| MOA file upload fails | Ensure the file is PDF, JPEG, PNG, or DOCX and under 20MB |
| Record not appearing after add | Check the status filter — newly added records with "On Process" status may be filtered out |

### 19.7 Reports & Export

| Problem | Solution |
|---|---|
| Export file is blank or empty | Ensure an intern is selected in the dropdown before clicking export |
| COC shows wrong name format | The COC uses the first_name, middle_name, and last_name fields from the 201 Profile. Update the profile if the name is incorrect |
| COC shows wrong dates | End date is derived from the last DTR entry date. If no DTR entries exist, the intern's `end_date` field is used |

### 19.8 Settings (Admin only)

| Problem | Solution |
|---|---|
| "Email already exists" on Add User | The email address is already registered. Use a different email |
| Cannot change another user's password | Each user must change their own password. Developers can reset hashes directly in the database |
| Lunch break setting not affecting DTR | The lunch break deduction only applies to new entries created after the setting is saved. Existing entries are not retroactively updated |

### 19.9 Intern Self-Registration

| Problem | Solution |
|---|---|
| "Link Expired" page | The token has a 24-hour TTL. A new registration_token must be generated in the database |
| Face service offline error | The Python ONNX embedding service at port 5001 is not running. Start the service on the server |
| Camera access denied | The intern must allow camera permissions in their browser. In-app browsers (Messenger, Viber) block camera access — instruct them to open the link in Chrome or Safari |
| "Failed to process all face angles" | Not all 5 face angles produced usable embeddings. The intern should retry in better lighting without glasses or obstructions |

---

## 20. Glossary

| Term | Definition |
|---|---|
| IMS | Intern Management System — the web-based platform described in this document |
| Admin | User role with full system access including Settings and Audit Trail |
| HR Staff | User role with operational access to intern, DTR, requirements, MOA, and reporting modules |
| 201 File / Profile | The complete personal, academic, and internship profile record of an intern |
| DTR | Daily Time Record — the log of time-in and time-out attendance entries for each intern |
| Rendered Hours | Total hours an intern has worked, calculated from their DTR entries |
| Required Hours | Total OJT hours an intern must complete. Default value: 486 hours |
| Overtime | Hours worked beyond the standard daily threshold (default: 8 hours) in a single DTR entry |
| Undertime | Hours short of the standard daily threshold in a single DTR entry |
| Lunch Break Deduction | A configurable deduction (in minutes) subtracted from rendered hours per DTR entry |
| Standard Hours | Configurable daily hour threshold used to determine overtime. Default: 8 hours |
| COC | Certificate of Completion — the formal document issued when an intern completes their required hours |
| MOA | Memorandum of Agreement — a formal partnership document between TDT Powersteel and a school or university |
| QR Code | A unique scannable code generated per intern after face registration, used at the biometric attendance kiosk |
| Face Embedding | A 512-float numerical vector representing an intern's facial features, stored for kiosk verification |
| Registration Token | A 64-character random string used to gate the intern self-registration page. Expires after 24 hours |
| Archived | A soft-delete status for records hidden from active views but preserved in the database |
| Active Status | Intern is currently on OJT |
| Inactive Status | Intern is temporarily not reporting |
| Archived Status | Intern's OJT is completed or terminated |
| Audit Trail | A chronological, tamper-evident log of all significant system actions for accountability |
| Session Timeout | Automatic logout after 30 minutes of inactivity |
| Account Lock | Security measure that disables a login after 5 consecutive failed password attempts |
| bcrypt | The password hashing algorithm used to store user passwords securely |
| ONNX | Open Neural Network Exchange — the format used by the face embedding model |
| MediaPipe | Google's ML framework used for real-time face landmark detection during self-registration |
| Entry Source | Indicates whether a DTR entry was created manually (by staff) or via the biometric kiosk |
| system_settings | A key-value database table storing global configuration values for the IMS |
| is_archived | A tinyint column (0 or 1) used for soft-deletion across MOA, requirements, and DTR tables |

---

*TDT Powersteel Corporation · Intern Management System · v1.0 · June 2026*
*Document prepared by the IMS Development Team*
