# 🩸 Blood Bank Management System (PHP 8.2 + Render + Aiven Cloud MySQL)

Complete Blood Bank Management System upgraded for **PHP 8.2**, containerized with **Docker & Apache** for **Render** deployment, and configured with SSL & Primary Key support for **Aiven Cloud MySQL**.

---

## 🔑 Administrator Login Credentials (ID & Password)

| Field | Value |
| :--- | :--- |
| **Username / Admin ID** | `admin` |
| **Password** | `admin123` |
| **User Role** | Administrator (Type 1) |
| **Login URL** | `https://<YOUR-RENDER-APP-NAME>.onrender.com/login.php` |

> [!NOTE]
> Default credentials are automatically configured and verified during deployment and database auto-seeding.

---

## 🚀 Live Demo & Deployment Quick Links
- **GitHub Repository**: [https://github.com/pankajdixit-dev/Blood-Bank-System.git](https://github.com/pankajdixit-dev/Blood-Bank-System.git)
- **1-Click DB Installer Tool**: `https://<YOUR-RENDER-APP-NAME>.onrender.com/db_setup.php`
- **Application Login Page**: `https://<YOUR-RENDER-APP-NAME>.onrender.com/login.php`

---

## ⚙️ Aiven Cloud MySQL Environment Variables
Configure these variables in your **Render Web Service -> Environment**:

| Variable Name | Required | Example Value | Description |
| :--- | :---: | :--- | :--- |
| `DB_SERVER` | Yes | `mysql-xxxx.aivencloud.com` | Aiven Cloud MySQL Hostname |
| `DB_PORT` | Yes | `26284` | Aiven 5-digit custom port |
| `DB_USERNAME` | Yes | `avnadmin` | MySQL Username |
| `DB_PASSWORD` | Yes | `AVNS_xxxxxxxx` | MySQL Password |
| `DB_NAME` | Yes | `defaultdb` / `bloodbank_db` | Target Database Name |
| `DB_SSL` | Yes | `true` | Enforces SSL encryption (required by Aiven) |
| `BASE_URL` | Optional | `https://your-app.onrender.com` | Base URL of deployed application |

---

## 🛠️ Step-by-Step Render Deployment Guide

### Option A: Using Blueprint (`render.yaml`) - Recommended
1. Log in to [Render Dashboard](https://dashboard.render.com/).
2. Click **Blueprints** -> **New Blueprint Instance**.
3. Connect your repository: `https://github.com/pankajdixit-dev/Blood-Bank-System.git`.
4. Render will auto-detect `render.yaml`.
5. Enter your Aiven MySQL credentials (`DB_SERVER`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`).
6. Click **Apply**. Render will build the Docker container and deploy the app.

### Option B: Manual Web Service Setup
1. Log in to [Render Dashboard](https://dashboard.render.com/).
2. Click **New +** -> **Web Service**.
3. Select **Build and deploy from a Git repository** and connect: `pankajdixit-dev/Blood-Bank-System`.
4. Fill in the details:
   - **Name**: `blood-bank-system` (or any preferred name)
   - **Region**: Choose closest to your Aiven database (e.g. Frankfurt, Oregon, Singapore)
   - **Branch**: `main`
   - **Runtime**: `Docker`
   - **Instance Type**: `Free`
5. Under **Environment Variables**, add the 6 variables listed in the table above.
6. Click **Create Web Service**.

---

## ⚡ 1-Click Database Setup & Auto-Seeding
The application includes an **Automatic Database Seeding Engine**:
1. When you first open the login page (`/login.php`), the application automatically detects if tables exist.
2. If tables are missing, it seeds all 6 tables and the default administrator user (`admin` / `admin123`) automatically.
3. You can also manually inspect database health, tables status, and SSL connectivity at `/db_setup.php`.

---

## 🛡️ Key Features & Compatibility Fixes
- **PHP 8.2 Ready**: Fully compatible with PHP 8.x (constructor-based property initialization, safe null-checks, error reporting configured).
- **Aiven Cloud SSL Enforced**: Supports `require_secure_transport=ON` and custom 5-digit ports via `mysqli_init()` and `MYSQLI_CLIENT_SSL`.
- **Aiven `sql_require_primary_key=ON` Compliant**: All database tables define explicit primary keys and auto-increment IDs in their creation schemas.
- **Render Dynamic Port & Reverse Proxy Safe**: Dynamic `$PORT` binding in Apache with `UseCanonicalName Off` and `UseCanonicalPhysicalPort Off` to prevent port 10000 leakages during redirects.
