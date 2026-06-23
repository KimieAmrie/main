USE student_registration_db;

-- ── Tambah kolum baru dalam table users ──────────────────────────────
ALTER TABLE users
    ADD COLUMN date_of_birth DATE          DEFAULT NULL AFTER phone,
    ADD COLUMN education_level ENUM('asasi','diploma') DEFAULT NULL AFTER year_of_study,
    ADD COLUMN current_semester INT        DEFAULT NULL AFTER education_level,
    ADD COLUMN intake_year      YEAR       DEFAULT NULL AFTER current_semester;

-- ── Tambah kolum education_level dalam courses ───────────────────────
ALTER TABLE courses
    DROP COLUMN credit_hours,
    ADD COLUMN education_level ENUM('asasi','diploma','both') DEFAULT 'both' AFTER faculty;

-- ── Tambah kolum education_level dalam classes ───────────────────────
ALTER TABLE classes
    ADD COLUMN education_level ENUM('asasi','diploma') DEFAULT NULL AFTER semester,
    ADD COLUMN semester_no     INT DEFAULT NULL AFTER education_level;
-- semester_no: Asasi = 1 or 2 | Diploma = 1,2,3, or 4

-- ── Table: semester definitions ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS semester_definitions (
    sem_def_id      INT AUTO_INCREMENT PRIMARY KEY,
    education_level ENUM('asasi','diploma') NOT NULL,
    semester_no     INT NOT NULL,
    semester_label  VARCHAR(50) NOT NULL,
    academic_year   VARCHAR(20) DEFAULT '2024/2025',
    start_date      DATE DEFAULT NULL,
    end_date        DATE DEFAULT NULL,
    is_current      TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_sem (education_level, semester_no, academic_year)
);

-- Asasi: 2 semester
INSERT INTO semester_definitions (education_level, semester_no, semester_label, academic_year, is_current) VALUES
('asasi', 1, 'Semester 1 (Asasi)', '2024/2025', 1),
('asasi', 2, 'Semester 2 (Asasi)', '2024/2025', 0);

-- Diploma: 4 semester
INSERT INTO semester_definitions (education_level, semester_no, semester_label, academic_year, is_current) VALUES
('diploma', 1, 'Semester 1 (Diploma)', '2024/2025', 1),
('diploma', 2, 'Semester 2 (Diploma)', '2024/2025', 0),
('diploma', 3, 'Semester 3 (Diploma)', '2025/2026', 0),
('diploma', 4, 'Semester 4 (Diploma)', '2025/2026', 0);
