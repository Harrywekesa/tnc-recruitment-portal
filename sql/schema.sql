-- ============================================================
-- Trans Nzoia County Recruitment Portal — Database Schema
-- Engine: MySQL 5.7+ / MariaDB 10.4+
-- Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS tnc_recruitment
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tnc_recruitment;

-- ──────────────────────────────────────────────────────────────
-- 1. USERS  (admin staff + applicant accounts)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(80)  NOT NULL UNIQUE,
  email           VARCHAR(160) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('superadmin','admin','hr','applicant') NOT NULL DEFAULT 'applicant',
  full_name       VARCHAR(160),
  -- Location (used to enforce ward-specific job eligibility)
  sub_county      VARCHAR(60)  NULL,
  ward            VARCHAR(80)  NULL,
  -- ID verification documents
  id_doc_front    VARCHAR(500) NULL,   -- stored filepath
  id_doc_back     VARCHAR(500) NULL,
  id_verified     TINYINT(1)   NOT NULL DEFAULT 0,
  -- Account state
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  last_login      DATETIME,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role),
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 2. JOBS
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jobs (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_code        VARCHAR(30)  NOT NULL UNIQUE,
  title           VARCHAR(200) NOT NULL,
  department      VARCHAR(200) NOT NULL,
  type            ENUM('Full-Time','Part-Time','Contract','Internship') NOT NULL DEFAULT 'Full-Time',
  deadline        DATE         NOT NULL,
  posted_date     DATE         NOT NULL DEFAULT (CURRENT_DATE),
  status          ENUM('Draft','Open','Closed','Cancelled') NOT NULL DEFAULT 'Draft',
  vacancies       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  min_experience  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  salary_scale    VARCHAR(80),
  scope           ENUM('county_wide','ward_specific') NOT NULL DEFAULT 'county_wide',
  target_sub_county VARCHAR(60) NULL,   -- only for ward_specific
  target_ward     VARCHAR(80)  NULL,   -- only for ward_specific
  description     TEXT         NOT NULL,
  requirements    TEXT,
  responsibilities TEXT,
  source          ENUM('manual','bulk_import') NOT NULL DEFAULT 'manual',
  created_by      INT UNSIGNED,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_deadline (deadline)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 3. APPLICATIONS
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS applications (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_no          VARCHAR(20)  NOT NULL UNIQUE,
  job_id          INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED,                  -- NULL = guest applicant
  first_name      VARCHAR(80)  NOT NULL,
  last_name       VARCHAR(80)  NOT NULL,
  id_no           VARCHAR(20)  NOT NULL,
  dob             DATE,
  gender          ENUM('Male','Female','Prefer not to say'),
  phone           VARCHAR(20)  NOT NULL,
  email           VARCHAR(160) NOT NULL,
  degree          VARCHAR(200),
  institution     VARCHAR(200),
  kism_no         VARCHAR(40),
  experience      VARCHAR(60),
  current_employer VARCHAR(200),
  cover_letter    TEXT,
  sub_county      VARCHAR(60),
  ward            VARCHAR(80),
  postal_address  VARCHAR(200),
  status          ENUM('Received','Under Review','Shortlisted','Not Shortlisted','Interview Scheduled','Hired','Rejected') NOT NULL DEFAULT 'Received',
  reviewer_notes  TEXT,
  reviewed_by     INT UNSIGNED,
  reviewed_at     DATETIME,
  submitted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id)       REFERENCES jobs(id)  ON DELETE CASCADE,
  FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (reviewed_by)  REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_job_id   (job_id),
  INDEX idx_status   (status),
  INDEX idx_sub_county (sub_county),
  INDEX idx_ref_no   (ref_no)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 4. DOCUMENTS  (CVs, certificates, cover letters attached)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id  INT UNSIGNED NOT NULL,
  doc_type        ENUM('cv','certificate','cover_letter','id_copy','testimonial','other') NOT NULL,
  original_name   VARCHAR(255) NOT NULL,
  stored_name     VARCHAR(255) NOT NULL,
  filepath        VARCHAR(500) NOT NULL,
  file_size       INT UNSIGNED,
  mime_type       VARCHAR(100),
  uploaded_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  INDEX idx_application_id (application_id)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 5. INTERVIEWS
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS interviews (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id  INT UNSIGNED NOT NULL UNIQUE,  -- one interview per application
  interview_date  DATE         NOT NULL,
  interview_time  TIME         NOT NULL,
  venue           VARCHAR(300) NOT NULL,
  mode            ENUM('In-Person','Virtual','Telephone') NOT NULL DEFAULT 'In-Person',
  panel_members   TEXT,
  status          ENUM('Scheduled','Completed','Cancelled','No-Show','Rescheduled') NOT NULL DEFAULT 'Scheduled',
  outcome         ENUM('Pass','Fail','Pending') DEFAULT 'Pending',
  outcome_notes   TEXT,
  notification_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_by      INT UNSIGNED,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by)     REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_date (interview_date)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 6. TESTIMONIALS  (bulk-uploaded reference letters etc.)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS testimonials (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id  INT UNSIGNED,
  applicant_name  VARCHAR(160),
  description     VARCHAR(300),
  original_name   VARCHAR(255) NOT NULL,
  stored_name     VARCHAR(255) NOT NULL,
  filepath        VARCHAR(500) NOT NULL,
  file_size       INT UNSIGNED,
  mime_type       VARCHAR(100),
  uploaded_by     INT UNSIGNED,
  uploaded_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
  FOREIGN KEY (uploaded_by)    REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 7. UPLOAD LOGS  (bulk job imports)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS upload_logs (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED,
  original_name   VARCHAR(255) NOT NULL,
  upload_type     ENUM('jobs','testimonials') NOT NULL,
  records_found   SMALLINT UNSIGNED DEFAULT 0,
  records_imported SMALLINT UNSIGNED DEFAULT 0,
  records_skipped SMALLINT UNSIGNED DEFAULT 0,
  status          ENUM('success','partial','failed') NOT NULL DEFAULT 'success',
  error_msg       TEXT,
  uploaded_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
