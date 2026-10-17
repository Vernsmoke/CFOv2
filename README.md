# CFOv2

CFOv2 is a Laravel-based recollection registration, attendance, scheduling, and certificate management system.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- MySQL or MariaDB

## Installation

Clone the repository and install dependencies:

```powershell
git clone https://github.com/Vernsmoke/CFOv2.git
cd CFOv2
composer install
npm install
Copy-Item .env.example .env
```

Create the database using phpMyAdmin or MySQL:

```sql
CREATE DATABASE cfov2
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Configure `.env`:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cfov2
DB_USERNAME=root
DB_PASSWORD=

CFO_DEMO_ACCOUNTS_ENABLED=true
CFO_DEMO_ACCOUNTS_PASSWORD=ChangeMe123!
```

Generate a unique application key and initialize the application:

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan optimize:clear
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Demo accounts

When `CFO_DEMO_ACCOUNTS_ENABLED=true`, `php artisan migrate --seed` creates these local accounts:

### Super Admin

- Email: `interface.admin@cfo.office.com`
- Password: `ChangeMe123!`

### CFO Staff

- Email: `interface.staff@cfo.office.com`
- Password: `ChangeMe123!`

### Student

- Email: `interface.student@cfo.office.com`
- Password: `ChangeMe123!`

Demo accounts are restricted to `local` and `testing` environments. For a production installation, set `CFO_DEMO_ACCOUNTS_ENABLED=false` and configure a unique administrator with `CFO_DEFAULT_ADMIN_EMAIL` and `CFO_DEFAULT_ADMIN_PASSWORD`.

## Account roles

- **Student** accounts are created through public registration and can access only their own registration, schedules, receipts, notifications, and certificates.
- **CFO Staff** accounts are created by a Super Admin under **Settings → Staff accounts**. They can perform daily office workflows but cannot manage staff, system settings, academic reference data, certificate templates, or activity logs.
- **Super Admin** accounts have full office access. Existing `Admin` accounts are converted to `SuperAdmin` by the role migration.

To provision another Super Admin from the server console:

```powershell
php artisan cfo:create-super-admin person@example.com --name="Full Name"
```

The public account form intentionally creates students only. CFO Staff receive a one-time password from a Super Admin and must change it within 24 hours.

## Important security notes

- Never commit the `.env` file.
- Every computer must generate its own `APP_KEY`.
- Never enable the shared demo accounts in production.
- The repository contains database migrations, not real user data.
- Do not publish production database exports.

## Resetting the database

```powershell
php artisan migrate:fresh --seed
```

Warning: this command permanently deletes existing data in the configured database.
