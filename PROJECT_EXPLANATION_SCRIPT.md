# SSU Library QR System: Presentation Script and Q&A

## Presentation script

Hello. I will explain how the SSU Library QR Entry and Visitor Logging System works, focusing on the database connection, QR generation, camera scanning, and APIs.

### 1. Database configuration, connection, and queries

The application loads its settings from a private `.env` file using the Dotenv library. The database host, port, database name, username, and password are read from environment variables. This keeps connection settings out of the PHP source code.

The `db()` function in `api/config.php` builds a PDO connection to MySQL. It reuses one connection during each PHP request, returns query results as associative arrays, and throws exceptions when a database operation fails. Queries use PDO prepared statements with named parameters, such as `:email`, so user input is bound as data instead of being inserted into the SQL text.

For multi-step operations such as creating a student and the student's QR record, the application uses a database transaction. If a step fails, it rolls the transaction back so the database does not keep only part of the operation.

### 2. QR code generation

When a student is created or an administrator generates another QR code, PHP creates a secret token using 32 cryptographically random bytes and converts it to hexadecimal text. The browser's `qrcodejs` library turns that token into the QR image.

The raw token is returned to the browser to render and download the QR code. The database does not store the raw token: it stores an HMAC-SHA-256 hash created with the configured `QR_HASH_SECRET`. When a code is scanned, the server hashes the scanned token the same way and looks up the matching hash.

Multiple QR records can belong to the same student, so generating an additional code does not invalidate previously issued codes. The scanner still checks that both the student account and the matched QR record are active. Deactivating a student disables their QR codes.

### 3. Laptop camera scanning

On the scanner page, `html5-qrcode` asks the browser for permission to use a camera and lists available devices. The administrator can select a camera; the selected video frames are decoded in the browser, and the decoded QR text is passed to the application.

Browsers require a secure context for camera access: use HTTPS in production or `localhost` during local development. The camera image is used for decoding in the browser; the scan request sends the decoded token to the server for validation.

### 4. API request flow

The APIs are PHP endpoints under `api/`. An endpoint loads the shared configuration, checks the HTTP method and request fields, and requires an administrator session where needed. JSON requests are decoded by `readJsonBody()`, and `jsonResponse()` sends a JSON body with the appropriate HTTP status code.

For a scan, the browser sends a same-origin POST request containing the decoded QR token and selected visit purpose. The PHP scanner endpoint verifies the session, hashes the token, checks the matching QR and student status, and checks whether that student already has a visit logged for the day. If valid, it inserts a visitor log and returns the student's information and visit time as JSON.

In summary, the browser handles the interface, QR rendering, and camera decoding; PHP handles authentication and business rules; and MySQL stores the student records, QR hashes, audit entries, and visit history.

## Possible questions and answers

### Why does the database store a hash instead of the QR token?

The token works like a credential. Storing only its keyed hash means someone who can read the QR table cannot directly copy a token and reproduce a student's QR code.

### How does the server know a scanned QR is valid?

It computes the HMAC hash of the decoded text and searches `student_qr_codes` for that hash. It then checks that the QR record and student account are active.

### Why use prepared statements?

Prepared statements keep user-provided values separate from SQL commands. This reduces SQL injection risk and makes query parameters explicit.

### Why use both a token and a hash?

The QR needs a value the camera can decode and the scanner can send. The database only needs a value it can compare. The raw token is used in the QR; its hash is used for database lookup.

### What happens when another QR code is generated?

A new token and QR record are added for the same student. Previously issued codes remain valid as long as their QR record and the student's account remain active.

### What happens when a student is deactivated?

The student's account becomes inactive and the active QR records are revoked. Scans are rejected until the account is active and a usable QR code is available.

### Why does the scanner require HTTPS or localhost?

Modern browsers restrict camera APIs to secure contexts to protect users' camera access. HTTPS is required on a deployed site; localhost is allowed for local development.

### What data does the scan API receive?

It receives the decoded QR token and the selected visit purpose as JSON. The browser sends the administrator's same-origin session cookie as well, so the PHP endpoint can authenticate the request.

### How does the app prevent duplicate daily check-ins?

The scanner checks for an existing visitor log for that student and date. The database also has a unique key on `(student_id, visit_date)`, providing a second guard against duplicate entries.

### What happens if the database operation fails halfway through?

For operations wrapped in a transaction, the application rolls back changes if an exception occurs before commit. The API returns an error response instead of reporting success.
