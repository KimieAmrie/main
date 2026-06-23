USE student_registration_db;

-- ── Table: classes (kelas fizikal untuk setiap kursus) ──────────────
CREATE TABLE IF NOT EXISTS classes (
    class_id     INT AUTO_INCREMENT PRIMARY KEY,
    class_code   VARCHAR(30)  UNIQUE NOT NULL,
    class_name   VARCHAR(100) NOT NULL,
    course_id    INT          NOT NULL,
    semester     VARCHAR(20)  DEFAULT '2024/2025-1',
    day          VARCHAR(20)  DEFAULT NULL,   -- e.g. 'Monday'
    time_start   TIME         DEFAULT NULL,
    time_end     TIME         DEFAULT NULL,
    venue        VARCHAR(100) DEFAULT NULL,
    max_students INT          DEFAULT 40,
    status       ENUM('active','inactive') DEFAULT 'active',
    created_by   INT          DEFAULT NULL,   -- staff user_id
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)  REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)     ON DELETE SET NULL
);

-- ── Table: subjects (subjek/topic dalam sesebuah kursus) ────────────
CREATE TABLE IF NOT EXISTS subjects (
    subject_id   INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(30)  UNIQUE NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    course_id    INT          NOT NULL,
    credit_hours INT          DEFAULT 3,
    description  TEXT         DEFAULT NULL,
    status       ENUM('active','inactive') DEFAULT 'active',
    created_by   INT          DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)  REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)     ON DELETE SET NULL
);

-- ── Table: class_students (assign student ke kelas) ─────────────────
CREATE TABLE IF NOT EXISTS class_students (
    cs_id      INT AUTO_INCREMENT PRIMARY KEY,
    class_id   INT NOT NULL,
    user_id    INT NOT NULL,
    assigned_by INT DEFAULT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cs (class_id, user_id),
    FOREIGN KEY (class_id)    REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(user_id)    ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)    ON DELETE SET NULL
);

-- ── Table: class_lecturers (assign lecturer ke kelas + subjek) ──────
CREATE TABLE IF NOT EXISTS class_lecturers (
    cl_id        INT AUTO_INCREMENT PRIMARY KEY,
    class_id     INT NOT NULL,
    lecturer_id  INT NOT NULL,
    subject_id   INT DEFAULT NULL,
    assigned_by  INT DEFAULT NULL,
    assigned_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cl (class_id, lecturer_id, subject_id),
    FOREIGN KEY (class_id)    REFERENCES classes(class_id)  ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(user_id)     ON DELETE CASCADE,
    FOREIGN KEY (subject_id)  REFERENCES subjects(subject_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)     ON DELETE SET NULL
);

-- ── Table: student_subjects (assign subjek tambahan untuk student) ───
CREATE TABLE IF NOT EXISTS student_subjects (
    ss_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    subject_id  INT NOT NULL,
    class_id    INT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    status      ENUM('active','dropped') DEFAULT 'active',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ss (user_id, subject_id),
    FOREIGN KEY (user_id)     REFERENCES users(user_id)       ON DELETE CASCADE,
    FOREIGN KEY (subject_id)  REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)    REFERENCES classes(class_id)    ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)       ON DELETE SET NULL
);
