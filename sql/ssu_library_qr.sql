CREATE DATABASE IF NOT EXISTS ssu_library_qr CHARACTER SET utf8mb4;

USE ssu_library_qr;

CREATE TABLE admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'super_admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE students (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_number VARCHAR(50) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    program VARCHAR(100) NOT NULL,
    year_level VARCHAR(30) NOT NULL,
    account_status ENUM(
        'active',
        'inactive',
        'pending'
    ) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_number (student_number),
    UNIQUE KEY uq_students_email (email),
    KEY idx_students_program (program),
    KEY idx_students_status (account_status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE student_qr_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    qr_identifier_hash VARCHAR(255) NOT NULL,
    status ENUM(
        'active',
        'revoked',
        'reissued'
    ) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_qr_hash (qr_identifier_hash),
    KEY idx_student_qr_student (student_id),
    KEY idx_student_qr_status (status),
    CONSTRAINT fk_qr_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE visitor_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    student_number_snapshot VARCHAR(50) NOT NULL,
    visit_date DATE NOT NULL,
    time_in TIME NOT NULL,
    purpose ENUM('STUDY', 'BORROW_BOOK') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_visitor_logs_date (visit_date),
    KEY idx_visitor_logs_student (student_id),
    CONSTRAINT fk_visitor_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NULL,
    student_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_student (student_id),
    KEY idx_audit_logs_admin (admin_id),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT INTO
    admins (
        full_name,
        email,
        password_hash,
        role
    )
VALUES (
        'Library Administrator',
        'admin@ssu.edu.ph',
        '$2y$10$wqmhAr9zRNrYmZ2YretKBe/KZRRV0u4AEk5S1Dr3JMkjQ2LoKNCbu',
        'super_admin'
    );
-- End: database setup 