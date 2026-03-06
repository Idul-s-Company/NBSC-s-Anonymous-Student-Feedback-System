# NBSC Anonymous Student Feedback System

## Folder Structure

```
nbsc-feedback/
├── index.php                    ← Root redirect
├── app/
│   ├── auth/
│   │   ├── login.php
│   │   └── logout.php
│   ├── admin/
│   │   ├── dashboard.php
│   │   ├── feedback.php
│   │   ├── users.php
│   │   ├── categories.php
│   │   ├── activity-logs.php
│   │   └── notifications.php
│   ├── manager/                 ← Staff role
│   │   ├── dashboard.php
│   │   ├── feedback.php
│   │   └── notifications.php
│   └── user/                    ← Student role
│       ├── dashboard.php
│       ├── submit.php
│       ├── my-feedback.php
│       └── notifications.php
├── assets/
│   └── css/
│       └── style.css
├── config/
│   ├── config.php               ← DB + BASE_URL
│   └── functions.php            ← Helpers
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php              ← Navigation (all roles)
│   └── activity-logger.php
├── db/
│   └── schema.sql               ← Your working_schema.sql
└── README.md
```

## Setup

1. Place this folder in `htdocs/` (XAMPP) or `www/` (WAMP).
2. Import `db/schema.sql` into MySQL.
3. Edit `config/config.php` → set `DB_USER`, `DB_PASS`, `BASE_URL`.
4. Visit `http://localhost/nbsc-feedback/`

## Default Login

| Role    | Email                    | Password   |
|---------|--------------------------|------------|
| Admin   | admin@nbsc.edu.ph        | password   |
| Staff   | r.villanueva@nbsc.edu.ph | password   |
| Student | r.geonzon@nbsc.edu.ph    | password   |

> Default password hash in schema: `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi` = `password`

## Roles

- **Admin** — Full access: users, categories, feedback, logs, notifications
- **Staff** — Review and update feedback status
- **Student** — Submit anonymous feedback, view resolved items
