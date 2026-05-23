# dpk_pvt_csbk

A lightweight, industry-standard PHP **MVC** accounting helper webapp.
Built from scratch with PHP 8.2+, MySQL (PDO), Bootstrap 5 & jQuery (local, no CDN),
Laravel-style routing, sessions + middleware auth, CSRF protection, and env config.

---

## 1. Tech Stack

| Layer    | Choice                                  |
| -------- | --------------------------------------- |
| Backend  | PHP 8.2+ (no Composer required)         |
| Database | MySQL 5.7+/MariaDB via PDO              |
| Frontend | Bootstrap 5.3.3 + jQuery 3.7.1 (local)  |
| Auth     | Sessions + bcrypt + CSRF tokens         |
| Routing  | Custom Laravel-style router             |

---

## 2. Folder Structure

dpk_pvt_csbk/
├── app/
│   ├── Core/            Framework classes (Router, View, DB, etc.)
│   ├── Controllers/     HTTP request handlers
│   ├── Models/          DB-backed entities (e.g. User)
│   ├── Middleware/      Auth & Guest gatekeepers
│   └── Views/           PHP templates (partials, pages, errors)
├── config/              Config bootstrap (loads .env)
├── routes/
│   └── web.php          Route definitions (GET/POST + middleware)
├── public/              ★ Web root (only this should be web-accessible)
│   ├── index.php        Front controller
│   ├── .htaccess        Pretty URLs
│   └── assets/
│       ├── css/         App stylesheets
│       ├── js/          App scripts
│       └── vendor/      bootstrap/ & jquery/ (downloaded, no CDN)
├── database/
│   └── db_dpk_pvt_csbk.sql   Schema + seed
├── storage/
│   └── logs/            Error logs
├── .env                 Local secrets (gitignored)
├── .env.example         Template
├── .htaccess            Root rewrite → public/
└── README.md

---

## 3. Setup (Windows + XAMPP)

1. **Start XAMPP**: Apache + MySQL.
2. **Create database & seed user**:
   - Open phpMyAdmin → `http://localhost/phpmyadmin`
   - Click **Import** → choose `database/db_dpk_pvt_csbk.sql` → **Go**.
   - This creates `db_dpk_pvt_csbk`, the `users` table, and the seed admin.
3. **Verify `.env`** matches your MySQL credentials (defaults to `root` / empty password — standard XAMPP).
4. **Open the app**: `http://localhost/dpk_pvt_csbk/`
   - You'll be redirected to **/login**.

> **Note:** Apache must allow `.htaccess` (XAMPP allows this by default — `AllowOverride All`).
> The root `.htaccess` rewrites every request into `public/index.php`, keeping the rest of the app outside the web root.

---

## 4. Routing (Laravel-style)

Defined in `routes/web.php`:

URL parameters use Laravel-style placeholders: `/users/{id}`.

---

## 5. Security Highlights

- **PDO prepared statements** for every query (no string concatenation, no `mysqli`).
- **CSRF tokens** required on every POST form (`@csrf` helper in views).
- **Bcrypt** password hashing (`password_hash` / `password_verify`).
- **HTTP-only, SameSite=Lax** session cookies; session ID rotated on login.
- **`htmlspecialchars`** auto-escape helper (`e($value)`) in views.
- **Strict middleware** — every route is protected by `auth` or `guest`.
- **Errors hidden** in production (`APP_DEBUG=false`); logged to `storage/logs/`.

---
