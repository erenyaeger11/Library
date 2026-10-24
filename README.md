# SSU QR Code Library Entry and Visitor Logging System

This is a PHP 8 and MySQL web application. The administrative interface and API are served by PHP; each administrative page template lives in its own file under `pages/`, while `index.php` handles routing and shared layout. Small browser-side scripts are used only for QR rendering and camera access.

## Requirements
- PHP 8.1 or later with PDO MySQL and sessions enabled
- MySQL or MariaDB
- Composer
- HTTPS in production (required by browsers for camera access)

## Run locally
1. Install PHP dependencies from the project folder with `composer install`.
2. Copy `.env.example` to `.env` and set the database connection values.
3. Create/import the database using `sql/ssu_library_qr.sql` in MySQL/phpMyAdmin.
4. Start the app from the project folder with `php -S 127.0.0.1:8000`.
5. Open `http://127.0.0.1:8000` and sign in.

Alternatively, put the project under XAMPP's `htdocs`, run `composer install` from the project folder to create `vendor/autoload.php`, then start Apache and MySQL, import the SQL file, and open the project folder through `http://localhost/CDC101`.

## Deploy
Vercel does not execute this PHP application. Deploy the project to a PHP-capable host that provides MySQL/MariaDB, or use a PHP server with a separately managed database.

1. Create a production database and import `sql/ssu_library_qr.sql`.
2. Upload the PHP project, including `index.php`, `pages/`, `api/`, and `assets/`, to the host's web root. Keep `vendor/` available by running `composer install --no-dev` in the project folder (or upload the generated `vendor/` directory).
3. Set `APP_ENV=production`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the host's environment settings or private `.env` file. Never publish database credentials.
4. Point the domain's document root at this project and enable HTTPS.
5. Visit the domain and sign in. Do not expect Vercel to run PHP files.

## Features
- Admin session-based login and logout
- Dashboard metrics, seven-day visitor activity, recent visits, and registrations
- Student creation, search, profile/history view, edit, activate/deactivate, and permanent deletion
- QR code generation and download; a replaced code immediately revokes the previous one
- Live QR camera scanner with selectable camera and visit purpose
- Visitor logs

The QR-generation and camera scripts load from public CDNs, so the browser needs internet access for those features. QR identifiers are generated and validated by PHP; only their hashes are stored in MySQL. Camera access requires HTTPS or localhost.

## Add a student
1. Sign in and open **Student Management**.
2. Select **Add New Student**, fill in all student details, and create the record.
3. Download the QR code shown after creation.

## Manage student records
- **Read:** Browse the student directory and open a profile to see visit history.
- **Update:** Select **Edit**, change the information, and save.
- **Deactivate:** This disables the account and QR but preserves history. Reactivation requires a replacement QR before check-in.
- **Delete permanently:** This deletes the student account, QR, and all associated visit history. This cannot be undone.

## Default admin user
- Email: `admin@ssu.edu.ph`
- Password: `password123`

Change the default administrator password before exposing a deployment publicly.

## API endpoints
- POST `/api/auth/login.php`
- POST `/api/auth/logout.php`
- GET `/api/auth/me.php`
- GET `/api/students/list.php`
- GET `/api/students/show.php?id={id}`
- POST `/api/students/create.php`
- POST `/api/students/update.php`
- POST `/api/students/delete.php`
- POST `/api/students/regenerate-qr.php`
- POST `/api/scanner/scan.php`
- GET `/api/reports/dashboard.php`
- GET `/api/reports/visitor-logs.php`

## Database notes
- QR identifiers are stored as hashes in `student_qr_codes`.
- The database enforces at most one visit per student per day.
- The setup SQL creates an administrator and tables without demo students or visitor logs.
