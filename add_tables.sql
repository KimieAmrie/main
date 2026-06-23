USE student_registration_db;

-- Table: courses
CREATE TABLE IF NOT EXISTS courses (
    course_id     INT AUTO_INCREMENT PRIMARY KEY,
    course_code   VARCHAR(20)  UNIQUE NOT NULL,
    course_name   VARCHAR(150) NOT NULL,
    credit_hours  INT          DEFAULT 3,
    faculty       VARCHAR(100) DEFAULT NULL,
    lecturer_id   INT          DEFAULT NULL,
    semester      VARCHAR(20)  DEFAULT '2024/2025-1',
    max_students  INT          DEFAULT 40,
    description   TEXT         DEFAULT NULL,
    status        ENUM('open','closed','full') DEFAULT 'open',
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Table: course_registrations
CREATE TABLE IF NOT EXISTS course_registrations (
    reg_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    course_id     INT NOT NULL,
    semester      VARCHAR(20)  DEFAULT '2024/2025-1',
    status        ENUM('pending','approved','rejected','dropped') DEFAULT 'pending',
    applied_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reg (user_id, course_id, semester),
    FOREIGN KEY (user_id)   REFERENCES users(user_id)   ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

-- Sample courses
INSERT INTO courses (course_code, course_name, credit_hours, faculty, semester, max_students, description, status) VALUES
('SWC3533', 'System Design',                    3, 'Faculty of Computing',  '2024/2025-1', 40, 'Covers system design methodologies, UML diagrams, and software architecture.', 'open'),
('ITC3123', 'Software Project Management',      3, 'Faculty of Computing',  '2024/2025-1', 40, 'Software project planning, scheduling, risk management and quality assurance.', 'open'),
('SWC2623', 'Software Development Paradigms',   3, 'Faculty of Computing',  '2024/2025-1', 35, 'Explores various programming paradigms including OOP, functional and declarative.', 'open'),
('MMC3113', 'User Experience Design',           3, 'Faculty of Computing',  '2024/2025-1', 30, 'Principles of UX/UI design, prototyping, usability testing and design thinking.', 'open'),
('ITC3084', 'Database Management',              3, 'Faculty of Computing',  '2024/2025-1', 40, 'Relational database design, SQL, normalization and database administration.', 'open'),
('ITC2014', 'Data Structures & Algorithms',     3, 'Faculty of Computing',  '2024/2025-1', 38, 'Fundamental data structures, algorithm analysis and problem solving techniques.', 'open'),
('NET3013', 'Computer Networks',                3, 'Faculty of Engineering','2024/2025-1', 35, 'Network protocols, OSI model, TCP/IP and network security fundamentals.', 'closed'),
('MAT2033', 'Discrete Mathematics',             3, 'Faculty of Sciences',   '2024/2025-1', 45, 'Logic, sets, relations, graph theory and combinatorics for computing students.', 'open');
