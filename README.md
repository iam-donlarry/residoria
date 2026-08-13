# Residoria — Standalone Residential Estate Management System

**Residoria** is a modern, clean, highly configurable PHP & MySQL residential estate management system. Designed specifically for standalone single-estate deployments, **Residoria** provides a complete operational platform for managing properties, residents, owners, recurring billing, security gate access, facility maintenance, and granular Role-Based Access Control (RBAC).

---

## 🌟 Key Highlights & Features

- **Standalone Single-Estate Deployment**: Built for single estate installations without unnecessary SaaS multi-tenancy overhead.
- **Configurable Estate Business Rules**: Estate branding (Name, Logo, Motto, Address), Primary Theme Color (`#12B1B0`), Tariffs, and Roles are managed directly via settings.
- **Granular RBAC System**: 24 distinct permissions mapped across 5 default system roles with dedicated permission management.
- **Half-Screen Offcanvas Drawers**: All Add, Edit, and View forms operate as 50% screen width (`50vw`) slide-over drawers with smooth micro-animations.
- **Decoupled Payment Gateway Integration**: Built-in `PaymentGatewayInterface` with native Paystack inline driver and digital receipt generation.
- **Dynamic DB Dashboard**: Real-time database metrics for total active residents, property counts, unit occupancy, revenue collection, arrears, and maintenance tickets.

---

## 🔐 Security Standards

- **PDO Prepared Statements**: 100% of database queries utilize PDO prepared statements with bound parameters to prevent SQL Injection.
- **CSRF Protection**: Form submissions require cryptographic CSRF token validation (`csrfField()`, `verifyCsrfToken()`).
- **Route Access Control**: Centralized session guard (`includes/auth_check.php`) enforces timeout rules and RBAC permissions (`requirePermission()`).
- **File Upload Security**: Profile photos and logos undergo strict MIME validation, extension whitelisting, and file renaming (`secureUpload()`).

---

## 🎨 Design System & UI Components

- **Primary Brand Color**: `#12B1B0`
- **Typography**: Google Font **Outfit** (`'Outfit', sans-serif`)
- **Icons**: Lucide Icons suite (`<i data-lucide="..."></i>`)
- **Navigation Sidebar**: Ghostwhite background (`ghostwhite`) with 2-way minimization (`sidebar-mini`) & `localStorage`/`cookie` state persistence.
- **Toast Notifications**: Reusable JS toast engine (`showToast('message', 'success')`).
- **Data Tables**: Clean data tables with status badges and action toolbars.

---

## 🛠️ Installation & Setup Guide

### 1. Requirements
- PHP 8.1 or higher with `pdo_mysql` extension
- MySQL / MariaDB Database Server
- Apache Web Server with `mod_rewrite` enabled

### 2. Deployment Steps
1. Clone or extract **Residoria** into your web server directory (e.g. `c:/xampp/htdocs/Estate`).
2. Ensure MySQL server is running.
3. Open terminal in project root and run database setup:
   ```bash
   php setup_database.php
   ```
4. Access **Residoria** in browser:
   ```text
   http://localhost/Estate/login.php
   ```

### 3. Default Login Credentials
- **Email**: `admin@admin.com`
- **Password**: `admin123`

---

*Residoria — Residential Estate Management System v2.0*
