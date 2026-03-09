# NBSC Anonymous Student Feedback System

## Overview

The **NBSC Anonymous Student Feedback System** is a web-based platform designed for Northbay College of Science and Technology (NBSC) that allows students to submit feedback, concerns, and suggestions anonymously. The system ensures student privacy while enabling administrators and staff to review, respond to, and resolve submitted feedback in an organized and transparent manner.

---

## Objectives

- Provide a **safe and anonymous** channel for students to voice concerns, suggestions, and feedback without fear of identification or retaliation.
- Enable **administrators and staff** to efficiently manage, categorize, review, and resolve feedback submissions.
- Promote **transparency** by showing resolved feedback and admin responses publicly in the student feed.
- Maintain **accountability** through activity logs, user warnings, and comment moderation.
- Foster a **responsive academic environment** by ensuring student concerns are heard and acted upon.

---

## Purpose

Many students hesitate to report issues or share honest feedback due to fear of consequences or lack of a proper channel. This system addresses that by:

- Stripping personal identity from submissions — no name, student ID, or profile is linked to any feedback.
- Allowing anonymous comments on feedback posts, encouraging peer discussion.
- Giving admins and staff a structured dashboard to track and act on submissions.
- Notifying relevant staff automatically when new feedback is submitted.

---

## Folder Structure

```
nbsc-feedback/
├── index.php                    ← Root redirect
├── app/
│   ├── auth/
│   │   ├── login.php            ← Login page
│   │   └── logout.php           ← Logout + session destroy
│   ├── admin/
│   │   ├── dashboard.php        ← System overview + charts
│   │   ├── feedback.php         ← View + review all feedback
│   │   ├── users.php            ← Manage all users
│   │   ├── comments.php         ← Moderate anonymous comments
│   │   ├── warnings.php         ← Issue + track user warnings
│   │   ├── activity-logs.php    ← Full system activity history
│   │   └── notifications.php    ← Admin notifications
│   ├── manager/                 ← Staff role
│   │   ├── dashboard.php        ← Feedback overview
│   │   ├── feedback.php         ← Review + update feedback status
│   │   ├── comments.php         ← View anonymous comments
│   │   └── notifications.php    ← Staff notifications
│   └── user/                    ← Student role
│       └── index.php            ← NGL-style feed + submit form
├── assets/
│   └── css/
│       └── style.css            ← Global stylesheet
├── config/
│   ├── config.php               ← DB credentials + BASE_URL
│   └── function.php             ← Helper functions
├── includes/
│   ├── header.php               ← HTML head + Bootstrap
│   ├── footer.php               ← Closing tags + Bootstrap JS
│   ├── sidebar.php              ← Role-based navigation + SVG icons
│   └── activity-logger.php      ← Activity logging helper
├── db/
│   └── schema.sql               ← Full database schema + sample data
└── README.md
```

---

## Setup

1. Place this folder inside `C:\xampp\htdocs\` and rename it to your preferred folder name.
2. Import `db/schema.sql` into MySQL via phpMyAdmin or terminal.
3. Open `config/config.php` and update:
   ```php
   define('DB_NAME', 'working_schema');
   define('BASE_URL', 'http://localhost/YOUR-FOLDER-NAME');
   ```
4. Visit `http://localhost/YOUR-FOLDER-NAME/` in your browser.

---

## Default Login Credentials

| Role    | Email                      | Password   |
|---------|----------------------------|------------|
| Admin   | admin@nbsc.edu.ph          | password   |
| Staff   | r.villanueva@nbsc.edu.ph   | password   |
| Student | r.geonzon@nbsc.edu.ph      | password   |
| Student | t.rojo@nbsc.edu.ph         | password   |

> **Note:** Default password hash in schema:
> `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi` = `password`

---

## How It Works

### 👨‍🎓 Student (User)

Students access a single-page **NGL-style anonymous feed** after logging in.

1. **Submit Feedback** — Select a category (Academic, Facilities, Services, Faculty, Administration, Suggestion, Complaint, General, Other), set a priority level (Low, Medium, High, Urgent), and type a message (max 200 characters). The submission is stored with no link to the student's identity.
2. **Browse the Feed** — All submitted feedback is visible to all students in a social-media-style feed showing category, priority, status, and any admin responses.
3. **My Submissions** — A tab shows feedback submitted in the current session so students can track their own posts anonymously.
4. **Anonymous Comments** — Students can post anonymous comments on any feedback post. Each comment is assigned a randomly generated anonymous ID (e.g. `anon_c1d2e3f4`) with no link to their real account.
5. **Notifications** — Students receive notifications when their feedback is resolved or reviewed.

> Privacy note: Once the session ends, the "My Submissions" tab clears. Submissions remain in the database but are permanently anonymous.

---

### 👩‍💼 Staff (Manager)

Staff members have a **sidebar dashboard** focused on reviewing and responding to feedback.

1. **Dashboard** — Overview of total, pending, reviewed, resolved, and urgent feedback counts, plus a recent feedback table.
2. **All Feedback** — Full list of feedback with filter options by status and priority. Staff can click "Review" on any item to open a modal, update its status (Pending → Reviewed → Resolved), and add review notes that are shown publicly to students.
3. **Comments** — View all anonymous comments associated with feedback submissions.
4. **Notifications** — Receive automatic notifications when new feedback is submitted.
5. **Activity Logging** — All staff actions (reviews, status changes) are automatically recorded in the activity log.

---

### 🛡️ Admin

Admins have **full access** to all system features plus user and content management.

1. **Dashboard** — Complete system overview with stat cards (users, feedback counts, warnings, comments) and 5 live charts:
   - Users by Role (donut)
   - Feedback by Category (bar)
   - Feedback by Status (donut)
   - Feedback by Priority (bar)
   - Submissions over the last 7 days (line)
2. **Feedback Management** — View, filter, and review all feedback with full modal review system.
3. **User Management** — Create, activate/deactivate, and delete user accounts for students, staff, and admins.
4. **Comments Moderation** — View all anonymous comments and update their status (Active, Flagged, Deleted).
5. **User Warnings** — Issue formal warnings to students for violations (offensive language, spam, harassment, etc.) and track warning history.
6. **Activity Logs** — Full audit trail of every action performed in the system including logins, logouts, feedback submissions, status changes, and user management actions.
7. **Notifications** — Receive and manage system notifications.

---

## Database Tables

| Table              | Description                                      |
|--------------------|--------------------------------------------------|
| `users`            | All system users (students, staff, admins)       |
| `feedback`         | Anonymous feedback submissions                   |
| `comments`         | Anonymous comments on feedback posts             |
| `feedback_reviews` | Admin/staff review notes and status changes      |
| `user_warnings`    | Warnings issued to users for violations          |
| `notifications`    | In-app notifications per user                    |
| `activity_logs`    | Full audit log of all system actions             |

---

## Roles Summary

| Feature                  | Student | Staff | Admin |
|--------------------------|:-------:|:-----:|:-----:|
| Submit anonymous feedback|    ✅   |       |       |
| Browse feedback feed     |    ✅   |       |       |
| Post anonymous comments  |    ✅   |       |       |
| Review feedback          |         |  ✅   |  ✅   |
| Update feedback status   |         |  ✅   |  ✅   |
| Manage users             |         |       |  ✅   |
| Issue warnings           |         |       |  ✅   |
| Moderate comments        |         |       |  ✅   |
| View activity logs       |         |       |  ✅   |
| Receive notifications    |    ✅   |  ✅   |  ✅   |

---

## Technologies Used

- **Backend:** PHP 8.2 (PDO)
- **Database:** MariaDB / MySQL
- **Frontend:** HTML5, CSS3, Bootstrap 5.3, Chart.js 4.4
- **Server:** Apache (XAMPP)
- **Security:** BCrypt password hashing, session-based authentication, HTML sanitization via `htmlspecialchars()`
