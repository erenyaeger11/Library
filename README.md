# SSU QR Code Library Entry and Visitor Logging System

This project contains a Vite + React frontend and PHP/MySQL REST API skeleton for the Samar State University library check-in system.

## Stack
- Frontend: React, Vite, Tailwind CSS, Framer Motion, Lucide, html5-qrcode
- Backend: PHP 8 + PDO + MySQL

## Local setup
1. Install Node dependencies:
   npm install
2. Install PHP dependencies:
   composer install
3. Copy `.env.example` to `.env` and set your local database values.
4. From the project root, start PHP's local development server in a terminal:
   php -S 127.0.0.1:8000 -t .
5. In a second terminal, start the frontend:
   npm run dev
6. Import the database schema from `sql/ssu_library_qr.sql` in phpMyAdmin or MySQL.
7. The Vite dev server proxies `/api` requests to the PHP server at `http://127.0.0.1:8000` by default. Verify `http://127.0.0.1:8000/api/auth/me.php` returns JSON (an unauthenticated response is expected).

Admin pages first check `GET /api/auth/me.php`; without a valid PHP session, the app only shows the login page. API endpoints must also enforce authentication server-side.

For local development, PHP's built-in server is used so the API PHP files in this project are executed directly; a copy under XAMPP's `htdocs` is not required. Configure `VITE_PHP_API_ORIGIN` in `.env` only if you run the PHP server at a different address, then restart Vite.

## Add a student
1. Sign in as an administrator and open **Student Management**.
2. Select **Add New Student** and enter the student's ID, full name, email, program, and year level.
3. Submit **Create student and QR code**. The account and secure QR identifier are saved in MySQL.
4. Download the generated QR PNG from the confirmation card.

## Manage student records
- **Create:** Add New Student creates a database record and QR identifier.
- **Read:** The directory, profile details, and visit history are loaded from MySQL.
- **Update:** Select Edit, change the student information, and save.
- **Deactivate (soft delete):** Select Deactivate and confirm. The account and QR code are disabled, but visit history remains. Activate is available later; generate a replacement QR before the student checks in again.
- **Delete permanently:** Select Delete and confirm to permanently remove the student account, QR code, and all associated visit history. This cannot be undone.

## Laptop camera scanner
- Open the app at `http://localhost:5173` (or `http://127.0.0.1:5173`). Browsers only allow webcam access on HTTPS or localhost; a LAN IP such as `http://192.168.x.x:5173` requires HTTPS.
- To test from another device on the network, install `mkcert` on the development computer (`winget install FiloSottile.mkcert`), run `mkcert -install`, create a `certs` folder, then generate a certificate for the computer's LAN IP (for example `mkcert -key-file certs/dev-key.pem -cert-file certs/dev-cert.pem 192.168.1.25 localhost 127.0.0.1`). Add `VITE_HTTPS_CERT_FILE=certs/dev-cert.pem` and `VITE_HTTPS_KEY_FILE=certs/dev-key.pem` to `.env`, then restart Vite. Open `https://192.168.1.25:5173` on the test device and trust the local CA on that device as well; get its public `rootCA.pem` path with `mkcert -CAROOT` and install that certificate on the test device. Never copy or share `rootCA-key.pem`. Replace the example IP with the development computer's actual LAN IP. Keep the generated certificate files private and do not commit them.
- Open **Library Entry Scanner** and allow camera permission when prompted. Select the laptop's built-in webcam from the Camera menu if more than one device is listed.
- Generate a student's QR code from **QR Code Management**, download the PNG, and present it to the camera. The scanner validates the identifier against the database and records the visit through PHP.
- If access is denied, allow camera access in the browser's site settings and click **Restart Camera**. Close other applications using the webcam if the device is reported busy.

## Default admin user
- Email: admin@ssu.edu.ph
- Password: password123

## API endpoints
- POST /api/auth/login.php
- POST /api/auth/logout.php
- GET /api/auth/me.php
- GET /api/students/list.php
- GET /api/students/show.php?id={id}
- POST /api/students/create.php
- POST /api/students/update.php
- POST /api/students/delete.php
- POST /api/students/regenerate-qr.php
- POST /api/scanner/scan.php
- GET /api/reports/dashboard.php
- GET /api/reports/visitor-logs.php

## Notes
- The QR identifier is stored as a hashed value in `student_qr_codes`.
- The duplicate check-in rule is enforced as one log per student per day in `visitor_logs`.
- Dashboard metrics and seven-day visitor activity are queried from MySQL and refresh every 15 seconds. Student and visitor log tables also load database records.
- QR management loads active students from MySQL. Generating or regenerating a QR identifier stores only its hash and returns the code once for PNG download.
- The SQL setup creates the administrator and database tables without inserting demo students or visitor logs. Add real student records through Student Management.
- The project includes a front-end prototype and backend skeleton suitable as an academic project foundation.
