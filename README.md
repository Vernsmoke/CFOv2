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

CFO_DEFAULT_ADMIN_EMAIL=admin@example.com
CFO_DEFAULT_ADMIN_PASSWORD=Choose-A-Secure-Password
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

## Important security notes

- Never commit the `.env` file.
- Every computer must generate its own `APP_KEY`.
- Replace the example administrator credentials before seeding.
- The repository contains database migrations, not real user data.
- Do not publish production database exports.

## Resetting the database

```powershell
php artisan migrate:fresh --seed
```

Warning: this command permanently deletes existing data in the configured database.
