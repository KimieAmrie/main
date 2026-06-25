<?php
// =====================================================
// student_dashboard.php
// Student Registration System — UPTM
// =====================================================
session_start();
require_once 'db_connect.php';
redirectIfNotLoggedIn();

// Only students allowed
if ($_SESSION['role'] !== 'student') {
    header("Location: login_page.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ── Fetch student profile ──────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// ── Handle Apply Course ───────────────────────────
$msg = $msg_type = '';

// ========================================
// ADD SUBJECT
// ========================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'add_subject'
) {

    $subject_id = intval($_POST['subject_id']);

    $check = $conn->prepare("
        SELECT ss_id
        FROM student_subjects
        WHERE user_id=?
        AND subject_id=?
        AND status='active'
    ");

    $check->bind_param("ii", $user_id, $subject_id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {

        $msg = "Subject already added.";
        $msg_type = "warning";

    } else {

        $insert = $conn->prepare("
            INSERT INTO student_subjects
            (
                user_id,
                subject_id,
                status,
                assigned_at
            )
            VALUES
            (
                ?,
                ?,
                'active',
                NOW()
            )
        ");

        $insert->bind_param("ii", $user_id, $subject_id);

        if ($insert->execute()) {

            $msg = "Subject added successfully.";
            $msg_type = "success";

        } else {

            $msg = "Failed to add subject.";
            $msg_type = "error";
        }
    }
}

// ========================================
// DROP SUBJECT HANDLER
// ========================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'drop_subject'
) {
    // 1. Ambil kod subjek (berbentuk String/Teks, jangan guna intval)
    $subject_code = trim($_POST['subject_code']);

    // 2. Gunakan subquery di dalam DELETE untuk tukarkan kod subjek kepada ID dalam database
    $drop_stmt = $conn->prepare("
        DELETE FROM student_subjects 
        WHERE user_id = ? 
          AND status = 'active'
          AND subject_id = (SELECT subject_id FROM subjects WHERE subject_code = ? LIMIT 1)
    ");
    
    // "is" bermaksud integer untuk user_id, string untuk subject_code
    $drop_stmt->bind_param("is", $user_id, $subject_code);

    if ($drop_stmt->execute()) {
        $msg = "Subjek berjaya digugurkan (dropped).";
        $msg_type = "success";
    } else {
        $msg = "Gagal untuk menggugurkan subjek.";
        $msg_type = "error";
    }
    
    // Segarkan data dan kembali ke tab manage_subjects
    header("Location: student_dashboard.php?tab=manage_subjects");
    exit();
}
// ── Update Profile (Tarikh Lahir, Telefon) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $phone = trim($_POST['phone']);
    $dob   = $_POST['date_of_birth'] ?: null;

    $upd = $conn->prepare("UPDATE users SET phone=?, date_of_birth=? WHERE user_id=?");
    $upd->bind_param("ssi", $phone, $dob, $user_id);
    if ($upd->execute()) {
        $msg = 'Profil berjaya dikemaskini!'; $msg_type = 'success';
        // Refresh student data
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
    } else {
        $msg = 'Ralat semasa kemaskini profil.'; $msg_type = 'error';
    }
}

// ── Change Password ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pw = $_POST['current_password'] ?? '';
    $new_pw     = $_POST['new_password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    if (!password_verify($current_pw, $student['password'])) {
        $msg = 'Kata laluan semasa tidak betul.'; $msg_type = 'error';
    } elseif (strlen($new_pw) < 8) {
        $msg = 'Kata laluan baharu mesti sekurang-kurangnya 8 aksara.'; $msg_type = 'error';
    } elseif ($new_pw !== $confirm_pw) {
        $msg = 'Kata laluan baharu dan pengesahan tidak sepadan.'; $msg_type = 'error';
    } else {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
        $upd->bind_param("si", $hashed, $user_id);
        if ($upd->execute()) { $msg = 'Kata laluan berjaya ditukar!'; $msg_type = 'success'; }
        else { $msg = 'Ralat semasa menukar kata laluan.'; $msg_type = 'error'; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_course_id'])) {
    $course_id = intval($_POST['apply_course_id']);
    $semester  = '2024/2025-1';

    // Sekat pemohonan jika pelajar sudah mempunyai kursus yang diluluskan
    $chk_has_course = $conn->prepare("SELECT COUNT(*) AS c FROM course_registrations WHERE user_id=? AND status='approved'");
    $chk_has_course->bind_param("i", $user_id);
    $chk_has_course->execute();
    $already_has_approved_course = $chk_has_course->get_result()->fetch_assoc()['c'] > 0;

    // Check if already applied with active status (pending or approved only)
    // dropped and rejected = boleh mohon semula
    $chk = $conn->prepare("SELECT reg_id, status FROM course_registrations WHERE user_id=? AND course_id=? AND semester=? AND status IN ('pending','approved')");
    $chk->bind_param("iis", $user_id, $course_id, $semester);
    $chk->execute();
    $chk_result = $chk->get_result()->fetch_assoc();

    if ($already_has_approved_course) {
        $msg = 'Anda sudah mempunyai kursus yang telah diluluskan. Anda tidak boleh memohon kursus lain.';
        $msg_type = 'warning';
    } elseif ($chk_result) {
        if ($chk_result['status'] === 'pending') {
            $msg = 'Anda sudah mempunyai permohonan yang sedang menunggu kelulusan untuk kursus ini.';
        } else {
            $msg = 'Anda sudah berdaftar dalam kursus ini.';
        }
        $msg_type = 'warning';
    } else {
        // Check course still open
        $crs = $conn->prepare("SELECT status FROM courses WHERE course_id=?");
        $crs->bind_param("i", $course_id);
        $crs->execute();
        $crs_data = $crs->get_result()->fetch_assoc();

        if ($crs_data['status'] !== 'open') {
            $msg = 'Kursus ini sudah ditutup atau penuh.';
            $msg_type = 'error';
        } else {
            // Check if old dropped/rejected record exists — UPDATE instead of INSERT
            $old = $conn->prepare("SELECT reg_id FROM course_registrations WHERE user_id=? AND course_id=? AND semester=? AND status IN ('dropped','rejected')");
            $old->bind_param("iis", $user_id, $course_id, $semester);
            $old->execute();
            $old_rec = $old->get_result()->fetch_assoc();

            if ($old_rec) {
                // Reuse existing record — just update status back to pending
                $upd = $conn->prepare("UPDATE course_registrations SET status='pending', applied_at=NOW() WHERE reg_id=?");
                $upd->bind_param("i", $old_rec['reg_id']);
                $success = $upd->execute();
            } else {
                // Insert new record
                $ins = $conn->prepare("INSERT INTO course_registrations (user_id, course_id, semester, status) VALUES (?,?,?,'pending')");
                $ins->bind_param("iis", $user_id, $course_id, $semester);
                $success = $ins->execute();
            }

            if ($success) {
                $msg = 'Permohonan kursus berjaya dihantar! Sila tunggu kelulusan staf.';
                $msg_type = 'success';
            } else {
                $msg = 'Ralat berlaku. Sila cuba lagi.';
                $msg_type = 'error';
            }
        }
    }
}

// ── Handle Drop Course ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_reg_id'])) {
    $reg_id = intval($_POST['drop_reg_id']);
    $upd = $conn->prepare("UPDATE course_registrations SET status='dropped' WHERE reg_id=? AND user_id=? AND status='pending'");
    $upd->bind_param("ii", $reg_id, $user_id);
    if ($upd->execute() && $upd->affected_rows > 0) {
        $msg = 'Permohonan kursus telah dibatalkan.';
        $msg_type = 'info';
    } else {
        $msg = 'Hanya permohonan berstatus "Pending" boleh dibatalkan.';
        $msg_type = 'warning';
    }
}

// ── Active tab ────────────────────────────────────
$tab = $_GET['tab'] ?? 'overview';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['update_profile','change_password'])) {
    $tab = 'profile';
}

// ── Data: My Registered Courses ───────────────────
$my_courses_q = $conn->prepare("
    SELECT cr.reg_id, cr.status, cr.applied_at, cr.semester,
           c.course_code, c.course_name, c.education_level, c.faculty
    FROM course_registrations cr
    JOIN courses c ON cr.course_id = c.course_id
    WHERE cr.user_id = ?
    ORDER BY cr.applied_at DESC
");
$my_courses_q->bind_param("i", $user_id);
$my_courses_q->execute();
$my_courses = $my_courses_q->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Data: Available Courses ───────────────────────
$avail_q = $conn->prepare("
    SELECT c.*,
        (SELECT COUNT(*) FROM course_registrations cr2
         WHERE cr2.course_id = c.course_id AND cr2.status IN ('pending','approved')) AS enrolled_count,
        (SELECT COUNT(*) FROM course_registrations cr3
         WHERE cr3.course_id = c.course_id AND cr3.user_id = ? AND cr3.semester = c.semester
         AND cr3.status IN ('pending','approved')) AS already_applied,
        (SELECT status FROM course_registrations cr4
         WHERE cr4.course_id = c.course_id AND cr4.user_id = ?
         ORDER BY cr4.applied_at DESC LIMIT 1) AS prev_status
    FROM courses c
    ORDER BY c.faculty, c.course_code
");
$avail_q->bind_param("ii", $user_id, $user_id);
$avail_q->execute();
$avail_courses = $avail_q->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Summary counts ────────────────────────────────
$total_applied  = count($my_courses);
$total_approved = count(array_filter($my_courses, fn($r) => $r['status'] === 'approved'));
$total_pending  = count(array_filter($my_courses, fn($r) => $r['status'] === 'pending'));
$total_credits  = $total_approved;

// ── Data: My Subjects (via student_subjects) ──────
$my_subjects_q = $conn->prepare("
    SELECT ss.ss_id, ss.status AS enrol_status, ss.assigned_at,
           s.subject_code, s.subject_name, s.semester_no, s.credit_hours,
           c.course_code, c.course_name,
           cl.class_code, cl.class_name,
           u.full_name AS lecturer_name
    FROM student_subjects ss
    JOIN subjects s  ON ss.subject_id = s.subject_id
    JOIN courses  c  ON s.course_id   = c.course_id
    LEFT JOIN classes cl ON ss.class_id = cl.class_id
    LEFT JOIN class_lecturers clec ON clec.class_id = ss.class_id AND clec.subject_id = ss.subject_id
    LEFT JOIN users u ON clec.lecturer_id = u.user_id
    WHERE ss.user_id = ? AND ss.status = 'active'
    ORDER BY s.semester_no, c.course_code, s.subject_code
");
$my_subjects_q->bind_param("i", $user_id);
$my_subjects_q->execute();
$my_subjects = $my_subjects_q->get_result()->fetch_all(MYSQLI_ASSOC);

// ========================================
// AVAILABLE SUBJECTS
// ========================================
$current_semester = intval($student['current_semester'] ?? 1);

// Jika dalam DB bernilai 0, kita beri default sem 1 untuk paparan
if ($current_semester <= 0) {
    $current_semester = 1;
}

$available_subjects_q = $conn->prepare("
    SELECT
        s.*,
        c.course_code,
        c.course_name,
        (
            SELECT COUNT(*)
            FROM student_subjects ss
            WHERE ss.subject_id = s.subject_id
              AND ss.user_id = ?
              AND ss.status = 'active'
        ) AS already_added
    FROM subjects s
    INNER JOIN courses c
        ON c.course_id = s.course_id
    WHERE s.status = 'active'
      AND s.semester_no = ?
    ORDER BY s.subject_code
");

$available_subjects_q->bind_param(
    "ii",
    $user_id,
    $current_semester
);

$available_subjects_q->execute();
$available_subjects = $available_subjects_q->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Data: My Classes (via class_students) ─────────
$my_class_q = $conn->prepare("
    SELECT cl.class_id, cl.class_code, cl.class_name,
           cl.education_level, cl.semester_no, cl.section_num, cl.max_students, cl.status,
           c.course_code, c.course_name,
           (SELECT COUNT(*) FROM class_students cs2 WHERE cs2.class_id = cl.class_id) AS total_students,
           (SELECT GROUP_CONCAT(DISTINCT u2.full_name ORDER BY u2.full_name SEPARATOR ', ')
            FROM class_lecturers clec2 JOIN users u2 ON clec2.lecturer_id = u2.user_id
            WHERE clec2.class_id = cl.class_id) AS lecturers
    FROM class_students cs
    JOIN classes cl ON cs.class_id = cl.class_id
    JOIN courses  c ON cl.course_id = c.course_id
    WHERE cs.user_id = ?
    ORDER BY c.course_code, cl.class_code
");
$my_class_q->bind_param("i", $user_id);
$my_class_q->execute();
$my_classes = $my_class_q->get_result()->fetch_all(MYSQLI_ASSOC);

// Status badge helper
function statusBadge($status) {
    $map = [
        'pending'  => ['label' => 'Pending',   'class' => 'badge-warning'],
        'approved' => ['label' => 'Diluluskan', 'class' => 'badge-success'],
        'rejected' => ['label' => 'Ditolak',    'class' => 'badge-danger'],
        'dropped'  => ['label' => 'Dibatalkan', 'class' => 'badge-gray'],
    ];
    $b = $map[$status] ?? ['label' => $status, 'class' => 'badge-gray'];
    return "<span class='badge {$b['class']}'>{$b['label']}</span>";
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pelajar — UPTM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --blue-deep:   #0f2d6e;
            --blue-mid:    #1a4db8;
            --blue-bright: #2563eb;
            --blue-light:  #3b82f6;
            --blue-pale:   #dbeafe;
            --white:       #ffffff;
            --gray-50:     #f8fafc;
            --gray-100:    #f1f5f9;
            --gray-200:    #e2e8f0;
            --gray-300:    #cbd5e1;
            --gray-500:    #64748b;
            --gray-700:    #334155;
            --gray-900:    #0f172a;
            --green-50:    #f0fdf4;
            --green-500:   #22c55e;
            --green-700:   #15803d;
            --yellow-50:   #fefce8;
            --yellow-500:  #eab308;
            --yellow-700:  #a16207;
            --red-50:      #fef2f2;
            --red-500:     #ef4444;
            --red-700:     #b91c1c;
            --sidebar-w:   260px;
        }

        body { font-family:'Inter',sans-serif; background:var(--gray-50); color:var(--gray-700); display:flex; min-height:100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w); flex-shrink:0;
            background: linear-gradient(175deg, var(--blue-deep) 0%, var(--blue-mid) 100%);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; bottom:0;
            z-index:100; overflow-y:auto;
        }
        .sidebar-brand {
            padding:28px 24px 20px;
            border-bottom:1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand .logo { font-size:28px; margin-bottom:6px; }
        .sidebar-brand h2 { color:var(--white); font-size:14px; font-weight:700; line-height:1.3; }
        .sidebar-brand span { color:rgba(255,255,255,0.55); font-size:11px; }

        .sidebar-profile {
            padding:20px 24px;
            border-bottom:1px solid rgba(255,255,255,0.1);
        }
        .profile-avatar {
            width:52px; height:52px; border-radius:14px;
            background:rgba(255,255,255,0.15); border:2px solid rgba(255,255,255,0.25);
            display:flex; align-items:center; justify-content:center;
            font-size:22px; margin-bottom:10px;
        }
        .profile-name  { color:var(--white); font-size:13px; font-weight:600; margin-bottom:2px; }
        .profile-role  { color:rgba(255,255,255,0.5); font-size:11px; }
        .profile-badge {
            display:inline-block; margin-top:6px;
            background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2);
            border-radius:20px; padding:3px 10px;
            color:rgba(255,255,255,0.8); font-size:10px; font-weight:600;
        }

        .sidebar-nav { padding:16px 12px; flex:1; }
        .nav-label { color:rgba(255,255,255,0.35); font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; padding:0 12px; margin:16px 0 6px; }
        .nav-item {
            display:flex; align-items:center; gap:12px;
            padding:11px 14px; border-radius:10px; cursor:pointer;
            color:rgba(255,255,255,0.65); font-size:13px; font-weight:500;
            text-decoration:none; transition:all .2s; margin-bottom:2px;
        }
        .nav-item i { width:18px; text-align:center; font-size:14px; }
        .nav-item:hover  { background:rgba(255,255,255,0.1); color:var(--white); }
        .nav-item.active { background:rgba(255,255,255,0.18); color:var(--white); font-weight:600; }

        .sidebar-footer {
            padding:16px 12px;
            border-top:1px solid rgba(255,255,255,0.1);
        }
        .btn-logout {
            display:flex; align-items:center; gap:10px;
            width:100%; padding:11px 14px; border-radius:10px;
            background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.25);
            color:#fca5a5; font-size:13px; font-weight:600;
            cursor:pointer; font-family:'Inter',sans-serif; transition:all .2s;
        }
        .btn-logout:hover { background:rgba(239,68,68,0.28); color:#fecaca; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }

        .topbar {
            background:var(--white); border-bottom:1px solid var(--gray-200);
            padding:16px 32px; display:flex; align-items:center; justify-content:space-between;
            position:sticky; top:0; z-index:50;
        }
        .topbar-left h1 { font-size:18px; font-weight:700; color:var(--gray-900); }
        .topbar-left p  { font-size:12px; color:var(--gray-500); margin-top:1px; }
        .topbar-right   { display:flex; align-items:center; gap:12px; }
        .topbar-date    { font-size:12px; color:var(--gray-500); }

        /* ── CONTENT ── */
        .content { padding:28px 32px; flex:1; }

        /* Alert */
        .alert {
            display:flex; align-items:flex-start; gap:12px;
            padding:14px 18px; border-radius:12px; font-size:14px;
            margin-bottom:24px; font-weight:500; line-height:1.5;
        }
        .alert-success { background:var(--green-50); color:var(--green-700); border:1px solid #bbf7d0; }
        .alert-warning { background:var(--yellow-50); color:var(--yellow-700); border:1px solid #fde68a; }
        .alert-error   { background:var(--red-50);   color:var(--red-700);   border:1px solid #fecaca; }
        .alert-info    { background:var(--blue-pale); color:var(--blue-mid); border:1px solid #bfdbfe; }

        /* Stat cards */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
        .stat-card {
            background:var(--white); border-radius:14px; padding:20px;
            border:1px solid var(--gray-200); display:flex; align-items:center; gap:16px;
        }
        .stat-icon {
            width:48px; height:48px; border-radius:12px;
            display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0;
        }
        .stat-icon.blue   { background:var(--blue-pale); }
        .stat-icon.green  { background:var(--green-50); }
        .stat-icon.yellow { background:var(--yellow-50); }
        .stat-icon.purple { background:#f5f3ff; }
        .stat-val   { font-size:26px; font-weight:700; color:var(--gray-900); line-height:1; }
        .stat-label { font-size:12px; color:var(--gray-500); margin-top:3px; }

        /* Section card */
        .card {
            background:var(--white); border-radius:14px;
            border:1px solid var(--gray-200); overflow:hidden; margin-bottom:24px;
        }
        .card-header {
            padding:18px 24px; border-bottom:1px solid var(--gray-100);
            display:flex; align-items:center; justify-content:space-between;
        }
        .card-header h3 { font-size:15px; font-weight:700; color:var(--gray-900); }
        .card-header p  { font-size:12px; color:var(--gray-500); margin-top:2px; }
        .card-body { padding:24px; }

        /* Table */
        table { width:100%; border-collapse:collapse; }
        thead th {
            padding:10px 14px; text-align:left;
            font-size:11px; font-weight:700; text-transform:uppercase;
            letter-spacing:0.6px; color:var(--gray-500);
            background:var(--gray-50); border-bottom:1px solid var(--gray-200);
        }
        tbody tr { border-bottom:1px solid var(--gray-100); transition:background .15s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:var(--gray-50); }
        tbody td { padding:12px 14px; font-size:13px; color:var(--gray-700); }

        /* Badges */
        .badge { display:inline-block; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-success { background:var(--green-50);  color:var(--green-700); }
        .badge-warning { background:var(--yellow-50); color:var(--yellow-700); }
        .badge-danger  { background:var(--red-50);    color:var(--red-700); }
        .badge-gray    { background:var(--gray-100);  color:var(--gray-500); }
        .badge-blue    { background:var(--blue-pale); color:var(--blue-mid); }
        .badge-closed  { background:#fdf4ff; color:#7e22ce; }

        /* Course cards grid */
        .course-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px; }
        .course-card {
            border:1.5px solid var(--gray-200); border-radius:12px;
            padding:18px; background:var(--white); transition:border-color .2s, box-shadow .2s;
        }
        .course-card:hover { border-color:var(--blue-light); box-shadow:0 4px 16px rgba(37,99,235,0.1); }
        .course-card.applied { border-color:#bbf7d0; background:var(--green-50); }
        .course-card.closed  { border-color:var(--gray-200); background:var(--gray-50); opacity:.75; }

        .cc-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:10px; gap:8px; }
        .cc-code   { font-size:12px; font-weight:700; color:var(--blue-bright); background:var(--blue-pale); padding:3px 9px; border-radius:6px; flex-shrink:0; }
        .cc-name   { font-size:14px; font-weight:600; color:var(--gray-900); margin-bottom:6px; line-height:1.3; }
        .cc-desc   { font-size:12px; color:var(--gray-500); line-height:1.5; margin-bottom:12px; }
        .cc-meta   { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
        .cc-meta span { font-size:11px; color:var(--gray-500); display:flex; align-items:center; gap:4px; }
        .cc-footer { border-top:1px solid var(--gray-100); padding-top:12px; }

        /* Buttons */
        .btn {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 16px; border-radius:8px; font-size:13px;
            font-weight:600; font-family:'Inter',sans-serif;
            cursor:pointer; border:none; transition:all .2s; text-decoration:none;
        }
        .btn-primary { background:var(--blue-bright); color:var(--white); }
        .btn-primary:hover { background:var(--blue-mid); }
        .btn-danger  { background:var(--red-50); color:var(--red-700); border:1px solid #fecaca; }
        .btn-danger:hover { background:#fecaca; }
        .btn-sm { padding:6px 12px; font-size:12px; }
        .btn:disabled { opacity:.5; cursor:not-allowed; }

        /* Profile grid */
        .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .profile-field label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--gray-500); display:block; margin-bottom:4px; }
        .profile-field .val  { font-size:14px; color:var(--gray-900); font-weight:500; padding:10px 14px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; }
        .profile-field .val.empty { color:var(--gray-300); font-style:italic; }

        /* Empty state */
        .empty-state { text-align:center; padding:48px 20px; }
        .empty-state .icon { font-size:48px; margin-bottom:12px; opacity:.4; }
        .empty-state h4 { font-size:15px; font-weight:600; color:var(--gray-500); margin-bottom:6px; }
        .empty-state p  { font-size:13px; color:var(--gray-300); }

        /* Search bar */
        .search-wrap { position:relative; margin-bottom:20px; }
        .search-wrap i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--gray-300); font-size:14px; }
        .search-wrap input {
            width:100%; padding:11px 14px 11px 40px;
            border:1.5px solid var(--gray-200); border-radius:10px;
            font-size:14px; font-family:'Inter',sans-serif; color:var(--gray-700);
            background:var(--gray-50); outline:none; transition:border-color .2s;
        }
        .search-wrap input:focus { border-color:var(--blue-bright); background:var(--white); }

        /* Filter tabs */
        .filter-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
        .filter-tab {
            padding:7px 16px; border-radius:20px; font-size:12px; font-weight:600;
            cursor:pointer; border:1.5px solid var(--gray-200);
            background:var(--white); color:var(--gray-500); transition:all .2s;
        }
        .filter-tab.active, .filter-tab:hover { background:var(--blue-bright); color:var(--white); border-color:var(--blue-bright); }

        /* Semester tag */
        .semester-tag { font-size:11px; color:var(--gray-500); background:var(--gray-100); padding:2px 8px; border-radius:4px; }

        @media (max-width:1100px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }
        @media (max-width:768px)  {
            .sidebar { transform:translateX(-100%); }
            .main { margin-left:0; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .profile-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<!-- ══════════════ SIDEBAR ══════════════ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo">🎓</div>
        <h2>Student Registration<br>System</h2>
        <span>UPTM Academic Portal</span>
    </div>

    <div class="sidebar-profile">
        <div class="profile-avatar">👤</div>
        <div class="profile-name"><?= htmlspecialchars($student['full_name']) ?></div>
        <div class="profile-role"><?= htmlspecialchars($student['email']) ?></div>
        <span class="profile-badge">🎓 Pelajar</span>
        <?php if ($student['student_no']): ?>
            <div style="margin-top:4px"><span class="profile-badge"><?= htmlspecialchars($student['student_no']) ?></span></div>
        <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Menu Utama</div>
        <a href="?tab=overview"      class="nav-item <?= $tab==='overview'      ? 'active':'' ?>"><i class="fas fa-house"></i> Gambaran Keseluruhan</a>
        <a href="?tab=profile"       class="nav-item <?= $tab==='profile'       ? 'active':'' ?>"><i class="fas fa-user"></i> Profil Saya</a>

        <div class="nav-label">Akademik</div>
        <a href="?tab=my_subjects"   class="nav-item <?= $tab==='my_subjects'   ? 'active':'' ?>"><i class="fas fa-bookmark"></i> Subjek Saya</a>
        <a href="?tab=manage_subjects" class="nav-item <?= $tab==='manage_subjects' ? 'active':'' ?>">
    <i class="fas fa-plus-minus"></i> Add / Drop Subject
</a>
        <a href="?tab=my_class"      class="nav-item <?= $tab==='my_class'      ? 'active':'' ?>"><i class="fas fa-chalkboard"></i> Kelas Saya</a>

        <div class="nav-label">Kursus</div>
        <a href="?tab=view_courses"  class="nav-item <?= $tab==='view_courses'  ? 'active':'' ?>"><i class="fas fa-book-open"></i> Senarai Kursus</a>
        <a href="?tab=apply_course"  class="nav-item <?= $tab==='apply_course'  ? 'active':'' ?>"><i class="fas fa-plus-circle"></i> Mohon Kursus</a>
        <a href="?tab=my_courses"    class="nav-item <?= $tab==='my_courses'    ? 'active':'' ?>"><i class="fas fa-list-check"></i> Kursus Saya</a>
        <a href="?tab=reg_status"    class="nav-item <?= $tab==='reg_status'    ? 'active':'' ?>"><i class="fas fa-clock-rotate-left"></i> Status Pendaftaran</a>
    </nav>

    <div class="sidebar-footer">
        <form method="POST" action="logout.php">
            <button type="submit" class="btn-logout"><i class="fas fa-right-from-bracket"></i> Log Keluar</button>
        </form>
    </div>
</aside>

<!-- ══════════════ MAIN ══════════════ -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>
                <?php
                $titles = [
                    'overview'    => 'Gambaran Keseluruhan',
                    'profile'     => 'Profil Saya',
                    'my_subjects' => 'Subjek Saya',
                    'my_class'    => 'Kelas Saya',
                    'view_courses'=> 'Senarai Kursus Tersedia',
                    'apply_course'=> 'Mohon Kursus',
                    'my_courses'  => 'Kursus Saya',
                    'reg_status'  => 'Status Pendaftaran',
                ];
                echo $titles[$tab] ?? 'Dashboard';
                ?>
            </h1>
            <p>Semester 2024/2025-1 · <?= date('d M Y') ?></p>
        </div>
        <div class="topbar-right">
            <span class="topbar-date"><i class="fas fa-calendar" style="margin-right:5px;color:var(--gray-300)"></i><?= date('D, d M Y') ?></span>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>">
                <i class="fas fa-<?= $msg_type==='success'?'circle-check':($msg_type==='warning'?'triangle-exclamation':($msg_type==='info'?'circle-info':'circle-exclamation')) ?>" style="flex-shrink:0;margin-top:2px"></i>
                <span><?= htmlspecialchars($msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- ══ TAB: OVERVIEW ══ -->
        <?php if ($tab === 'overview'): ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📚</div>
                <div><div class="stat-val"><?= $total_applied ?></div><div class="stat-label">Jumlah Permohonan</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">✅</div>
                <div><div class="stat-val"><?= $total_approved ?></div><div class="stat-label">Kursus Diluluskan</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow">⏳</div>
                <div><div class="stat-val"><?= $total_pending ?></div><div class="stat-label">Menunggu Kelulusan</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">🏅</div>
                <div>
                    <div class="stat-val" style="font-size:18px">
                        <?php
                        $edu_lvl = $student['education_level'] ?? null;
                        $sem_no  = $student['current_semester'] ?? null;
                        if ($edu_lvl && $sem_no) {
                            $max_sem = $edu_lvl === 'asasi' ? 2 : 4;
                            echo "Sem $sem_no / $max_sem";
                        } else {
                            echo '—';
                        }
                        ?>
                    </div>
                    <div class="stat-label"><?= $edu_lvl ? ucfirst($edu_lvl) : 'Taraf Pendidikan' ?></div>
                </div>
            </div>
        </div>

        <!-- Profile Summary -->
        <div class="card">
            <div class="card-header">
                <div><h3>👤 Ringkasan Profil</h3><p>Maklumat peribadi dan akademik anda</p></div>
                <a href="?tab=profile" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Lihat Lengkap</a>
            </div>
            <div class="card-body">
                <div class="profile-grid">
                    <div class="profile-field"><label>Nama Penuh</label><div class="val"><?= htmlspecialchars($student['full_name']) ?></div></div>
                    <div class="profile-field"><label>Username</label><div class="val"><?= htmlspecialchars($student['username']) ?></div></div>
                    <div class="profile-field"><label>E-mel</label><div class="val"><?= htmlspecialchars($student['email']) ?></div></div>
                    <div class="profile-field"><label>No. Telefon</label><div class="val <?= !$student['phone']?'empty':'' ?>"><?= $student['phone'] ? htmlspecialchars($student['phone']) : 'Belum dikemaskini' ?></div></div>
                    <div class="profile-field"><label>No. Pelajar</label><div class="val <?= !$student['student_no']?'empty':'' ?>"><?= $student['student_no'] ? htmlspecialchars($student['student_no']) : 'Belum ditetapkan oleh admin' ?></div></div>
                    <div class="profile-field"><label>Fakulti</label><div class="val <?= !$student['faculty']?'empty':'' ?>"><?= $student['faculty'] ? htmlspecialchars($student['faculty']) : 'Belum ditetapkan' ?></div></div>
                    <div class="profile-field"><label>Program</label><div class="val <?= !$student['program']?'empty':'' ?>"><?= $student['program'] ? htmlspecialchars($student['program']) : 'Belum ditetapkan' ?></div></div>
                    <div class="profile-field"><label>Tahun Pengajian</label><div class="val <?= !$student['year_of_study']?'empty':'' ?>"><?= $student['year_of_study'] ? 'Tahun '.$student['year_of_study'] : 'Belum ditetapkan' ?></div></div>
                </div>
            </div>
        </div>

        <!-- Recent registrations -->
        <div class="card">
            <div class="card-header">
                <div><h3>📋 Permohonan Terkini</h3><p>5 permohonan kursus terbaru anda</p></div>
                <a href="?tab=reg_status" class="btn btn-primary btn-sm"><i class="fas fa-list"></i> Lihat Semua</a>
            </div>
            <?php $recent = array_slice($my_courses, 0, 5); ?>
            <?php if ($recent): ?>
            <table>
                <thead><tr><th>Kod</th><th>Nama Kursus</th><th>Taraf</th><th>Status</th><th>Tarikh Mohon</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($r['course_code']) ?></span></td>
                    <td><?= htmlspecialchars($r['course_name']) ?></td>
                    <td style="text-align:center"><?= ucfirst($r['education_level'] ?? 'both') ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td><?= date('d M Y', strtotime($r['applied_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><div class="icon">📭</div><h4>Tiada permohonan lagi</h4><p>Anda belum memohon sebarang kursus.</p></div>
            <?php endif; ?>
        </div>

        <!-- ══ TAB: PROFILE ══ -->
        <?php elseif ($tab === 'profile'): ?>
        <div class="card">
            <div class="card-header">
                <div><h3>👤 Maklumat Peribadi</h3><p>Kemaskini maklumat peribadi anda</p></div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="profile-grid">
                        <div class="profile-field"><label>Nama Penuh</label><div class="val"><?= htmlspecialchars($student['full_name']) ?></div></div>
                        <div class="profile-field"><label>Username</label><div class="val"><?= htmlspecialchars($student['username']) ?></div></div>
                        <div class="profile-field"><label>Alamat E-mel</label><div class="val"><?= htmlspecialchars($student['email']) ?></div></div>
                        <div class="profile-field"><label>Peranan</label><div class="val"><span class="badge badge-blue">🎓 Pelajar</span></div></div>

                        <div class="profile-field">
                            <label>No. Telefon</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>" placeholder="Cth: 0123456789"
                                style="width:100%;padding:10px 13px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;color:var(--gray-700);background:var(--gray-50);outline:none">
                        </div>
                        <div class="profile-field">
                            <label>Tarikh Lahir</label>
                            <input type="date" name="date_of_birth" value="<?= htmlspecialchars($student['date_of_birth'] ?? '') ?>"
                                style="width:100%;padding:10px 13px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;color:var(--gray-700);background:var(--gray-50);outline:none">
                        </div>

                        <div class="profile-field"><label>Status Akaun</label><div class="val"><span class="badge badge-<?= $student['status']==='active'?'success':'warning' ?>"><?= ucfirst($student['status']) ?></span></div></div>
                        <div class="profile-field"><label>Tarikh Daftar</label><div class="val"><?= date('d M Y, h:i A', strtotime($student['created_at'])) ?></div></div>
                    </div>
                    <div style="margin-top:18px"><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan Perubahan</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div><h3>🏫 Maklumat Akademik</h3><p>Dikemaskini oleh pentadbir selepas kelulusan</p></div>
            </div>
            <div class="card-body">
                <div class="profile-grid">
                    <div class="profile-field"><label>No. Pelajar</label><div class="val <?= !$student['student_no']?'empty':'' ?>"><?= $student['student_no'] ?: 'Belum ditetapkan oleh admin' ?></div></div>
                    <div class="profile-field"><label>Taraf Pendidikan</label><div class="val <?= !$student['education_level']?'empty':'' ?>"><?= $student['education_level'] ? ucfirst($student['education_level']) : 'Belum ditetapkan' ?></div></div>
                    <div class="profile-field"><label>Fakulti</label><div class="val <?= !$student['faculty']?'empty':'' ?>"><?= $student['faculty'] ?: 'Belum ditetapkan' ?></div></div>
                    <div class="profile-field"><label>Program Pengajian</label><div class="val <?= !$student['program']?'empty':'' ?>"><?= $student['program'] ?: 'Belum ditetapkan' ?></div></div>
                    <div class="profile-field"><label>Tahun Pengajian</label><div class="val <?= !$student['year_of_study']?'empty':'' ?>"><?= $student['year_of_study'] ? 'Tahun '.$student['year_of_study'] : 'Belum ditetapkan' ?></div></div>
                </div>
                <div style="margin-top:16px;padding:12px 16px;background:var(--blue-pale);border-radius:10px;font-size:13px;color:var(--blue-mid)">
                    <i class="fas fa-circle-info" style="margin-right:8px"></i>
                    Maklumat akademik anda akan dikemaskini oleh pentadbir. Hubungi pejabat akademik jika ada pertanyaan.
                </div>
            </div>
        </div>

        <!-- Tukar Kata Laluan -->
        <div class="card">
            <div class="card-header">
                <div><h3>🔒 Tukar Kata Laluan</h3><p>Pastikan kata laluan baharu kukuh dan mudah diingati</p></div>
            </div>
            <div class="card-body">
                <form method="POST" onsubmit="return validateStudentPwForm()" style="max-width:420px">
                    <input type="hidden" name="action" value="change_password">
                    <div class="profile-field" style="margin-bottom:14px">
                        <label>Kata Laluan Semasa *</label>
                        <input type="password" name="current_password" required
                            style="width:100%;padding:10px 13px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;color:var(--gray-700);background:var(--gray-50);outline:none">
                    </div>
                    <div class="profile-field" style="margin-bottom:14px">
                        <label>Kata Laluan Baharu *</label>
                        <input type="password" name="new_password" id="studentNewPw" minlength="8" required
                            style="width:100%;padding:10px 13px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;color:var(--gray-700);background:var(--gray-50);outline:none">
                    </div>
                    <div class="profile-field" style="margin-bottom:18px">
                        <label>Sahkan Kata Laluan Baharu *</label>
                        <input type="password" name="confirm_password" id="studentConfirmPw" minlength="8" required
                            style="width:100%;padding:10px 13px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;color:var(--gray-700);background:var(--gray-50);outline:none">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Tukar Kata Laluan</button>
                </form>
            </div>
        </div>

        <!-- ══ TAB: MY SUBJECTS ══ -->
        <?php elseif ($tab === 'my_subjects'): ?>
        <div class="card">
            <div class="card-header">
                <div><h3>📖 Subjek Saya</h3><p>Senarai subjek yang anda akan pelajari semester ini</p></div>
                <span class="badge badge-blue"><?= count($my_subjects) ?> subjek</span>
            </div>
            <?php if ($my_subjects): ?>
            <table>
                <thead>
                    <tr><th>#</th><th>Kod Subjek</th><th>Nama Subjek</th><th>Kursus</th><th>Semester</th><th>Kredit</th><th>Kelas</th><th>Pensyarah</th><th>Status</th><th>Tindakan</th></tr>
                </thead>
                <tbody>
                <?php foreach ($my_subjects as $i => $s): ?>
<tr>
    <td style="color:var(--gray-300)"><?= $i+1 ?></td>
    <td><span class="badge badge-blue"><?= htmlspecialchars($s['subject_code']) ?></span></td>
    <td><strong><?= htmlspecialchars($s['subject_name']) ?></strong></td>
    <td><span class="badge badge-gray"><?= htmlspecialchars($s['course_code']) ?></span></td>
    <td><?= $s['semester_no'] ? '<span class="badge badge-warning">Sem '.$s['semester_no'].'</span>' : '—' ?></td>
    <td style="text-align:center"><?= $s['credit_hours'] ?> Kredit</td>
    <td><?= $s['class_code'] ? '<span class="badge badge-success" style="font-family:monospace">'.htmlspecialchars($s['class_code']).'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
    <td style="font-size:12px"><?= $s['lecturer_name'] ? htmlspecialchars($s['lecturer_name']) : '<span style="color:var(--gray-300)">Belum ditetapkan</span>' ?></td>
    <td><span class="badge badge-success"><?= htmlspecialchars($s['enrol_status']) ?> </span> </td>
     <td>
    <form method="POST" onsubmit="return confirm('Adakah anda ingin drop subjek tersebut?');" style="margin:0;">
        <input type="hidden" name="action" value="drop_subject">
        <input type="hidden" name="subject_code" value="<?= htmlspecialchars($s['subject_code']) ?>">            
        <button type="submit" class="btn btn-danger btn-sm" style="padding: 5px 10px;">
            Drop
        </button>
    </form> 
</td>
    </tr>
    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="icon">📖</div>
                <h4>Tiada subjek lagi</h4>
                <p>Subjek anda belum diassign oleh staf. Sila hubungi pejabat akademik.</p>
            </div>
            <?php endif; ?>
        </div>

       <?php elseif ($tab === 'manage_subjects'): ?>

<div class="card">

    <div class="card-header">
        <div>
            <h3>➕ Add / Drop Subject</h3>
            <p>
                Semester <?= htmlspecialchars($current_semester) ?> 
                Subject Registration
            </p>
        </div>
    </div>

    <div style="padding: 20px;">

        <input
            type="text"
            id="subjectSearch"
            placeholder="Search subject..."
            style="
                width: 100%;
                padding: 12px;
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 8px;
            "
        >

        <table id="subjectTable" class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Subject</th>
                    <th>Semester</th>
                    <th>Credit</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($available_subjects)): ?>
                    <?php foreach ($available_subjects as $s): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($s['subject_code']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($s['subject_name']) ?>
                            </td>
                            <td>
                                Semester <?= htmlspecialchars($s['semester_no']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($s['credit_hours']) ?>
                            </td>
                            <td>
                                <?php if ($s['already_added'] > 0): ?>
                                    <span class="badge badge-success">
                                        Added
                                    </span>
                                <?php else: ?>
                                    <form method="POST" style="margin: 0;">
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="add_subject"
                                        >
                                        <input
                                            type="hidden"
                                            name="subject_id"
                                            value="<?= htmlspecialchars($s['subject_id']) ?>"
                                        >
                                        <button
                                            class="btn btn-primary"
                                            type="submit"
                                        >
                                            Add
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px; color: #777;">
                            No subjects available for this semester.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

</div>

        <!-- ══ TAB: MY CLASS ══ -->
        <?php elseif ($tab === 'my_class'): ?>
        <?php if ($my_classes): ?>
            <?php foreach ($my_classes as $cl):
                $edu = $cl['education_level'] ?? '';
                $edu_label = match($edu) { 'asasi'=>'Asasi','diploma'=>'Diploma', default=>'—' };
                $edu_badge = match($edu) { 'asasi'=>'badge-warning','diploma'=>'badge-blue', default=>'badge-gray' };
            ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3><span style="font-family:monospace;color:var(--blue-bright)"><?= htmlspecialchars($cl['class_code']) ?></span> &nbsp;·&nbsp; <?= htmlspecialchars($cl['class_name']) ?></h3>
                        <p>Kursus: <?= htmlspecialchars($cl['course_code'].' — '.$cl['course_name']) ?></p>
                    </div>
                    <span class="badge <?= $cl['status']==='active'?'badge-success':'badge-gray' ?>"><?= $cl['status']==='active'?'Aktif':'Tidak Aktif' ?></span>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--gray-500);margin-bottom:5px">Taraf Pendidikan</div>
                            <span class="badge <?= $edu_badge ?>"><?= $edu_label ?></span>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--gray-500);margin-bottom:5px">Semester</div>
                            <span class="badge badge-warning"><?= $cl['semester_no'] ? 'Semester '.$cl['semester_no'] : '—' ?></span>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--gray-500);margin-bottom:5px">No. Section</div>
                            <span style="font-size:14px;font-weight:700;color:var(--gray-900)"><?= $cl['section_num'] ? 'Section '.str_pad($cl['section_num'],2,'0',STR_PAD_LEFT) : '—' ?></span>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--gray-500);margin-bottom:5px">Bilangan Pelajar</div>
                            <span style="font-size:14px;font-weight:700;color:var(--gray-900)"><?= $cl['total_students'] ?>/<?= $cl['max_students'] ?></span>
                        </div>
                    </div>
                    <?php if ($cl['lecturers']): ?>
                    <div style="margin-top:16px;padding:12px 16px;background:var(--blue-pale);border-radius:10px;font-size:13px;color:var(--blue-mid)">
                        <i class="fas fa-chalkboard-user" style="margin-right:8px"></i>
                        <strong>Pensyarah:</strong> <?= htmlspecialchars($cl['lecturers']) ?>
                    </div>
                    <?php else: ?>
                    <div style="margin-top:16px;padding:12px 16px;background:var(--gray-50);border-radius:10px;font-size:13px;color:var(--gray-300)">
                        <i class="fas fa-circle-info" style="margin-right:8px"></i>
                        Pensyarah belum ditetapkan untuk kelas ini.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <div class="icon">🏫</div>
                <h4>Tiada kelas lagi</h4>
                <p>Anda belum dimasukkan ke dalam mana-mana kelas. Sila hubungi pejabat akademik.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ TAB: VIEW COURSES ══ -->
        <?php elseif ($tab === 'view_courses'): ?>
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Cari kursus mengikut kod, nama atau fakulti..." oninput="filterCourses()">
        </div>
        <div class="filter-tabs">
            <div class="filter-tab active" onclick="filterByStatus('all',this)">Semua (<?= count($avail_courses) ?>)</div>
            <div class="filter-tab" onclick="filterByStatus('open',this)">Dibuka</div>
            <div class="filter-tab" onclick="filterByStatus('closed',this)">Ditutup</div>
        </div>
        <div class="course-grid" id="courseGrid">
            <?php foreach ($avail_courses as $c): ?>
            <div class="course-card <?= $c['already_applied']?'applied':($c['status']!=='open'?'closed':'') ?>"
                 data-status="<?= $c['status'] ?>"
                 data-search="<?= strtolower($c['course_code'].' '.$c['course_name'].' '.$c['faculty']) ?>">
                <div class="cc-header">
                    <span class="cc-code"><?= htmlspecialchars($c['course_code']) ?></span>
                    <?php if ($c['status']==='open'): ?>
                        <span class="badge badge-success">Dibuka</span>
                    <?php else: ?>
                        <span class="badge badge-closed">Ditutup</span>
                    <?php endif; ?>
                </div>
                <div class="cc-name"><?= htmlspecialchars($c['course_name']) ?></div>
                <div class="cc-desc"><?= htmlspecialchars($c['description']) ?></div>
                <div class="cc-meta">
                    <span><i class="fas fa-graduation-cap"></i> <?= $c['education_level']==='asasi'?'Asasi':($c['education_level']==='diploma'?'Diploma':'Asasi & Diploma') ?></span>
                    <span><i class="fas fa-building-columns"></i> <?= htmlspecialchars($c['faculty']) ?></span>
                    <span><i class="fas fa-users"></i> <?= $c['enrolled_count'] ?>/<?= $c['max_students'] ?></span>
                </div>
                <div class="cc-footer">
                    <span class="semester-tag">📅 <?= $c['semester'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ══ TAB: APPLY COURSE ══ -->
        <?php elseif ($tab === 'apply_course'): ?>
        <?php if ($total_approved > 0): ?>
        <div style="background:linear-gradient(135deg,#dbeafe,#eff6ff);border:1px solid #bfdbfe;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px">
            <span style="font-size:26px">✅</span>
            <div>
                <div style="font-size:15px;font-weight:700;color:var(--blue-mid)">Anda sudah mempunyai kursus yang diluluskan</div>
                <div style="font-size:13px;color:var(--blue-mid);margin-top:2px">Anda tidak boleh memohon kursus lain selagi sudah ada kursus berdaftar. Sila rujuk tab "Kursus Saya".</div>
            </div>
        </div>
        <?php endif; ?>
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchApply" placeholder="Cari kursus untuk mohon..." oninput="filterApply()">
        </div>
        <div class="course-grid" id="applyGrid">
            <?php foreach ($avail_courses as $c): ?>
            <?php if ($c['status'] === 'open'): ?>
            <?php
                $prev = $c['prev_status'] ?? null;
                $can_reapply = in_array($prev, ['dropped','rejected']) && $total_approved === 0;
                $is_applied  = $c['already_applied'] > 0;
            ?>
            <div class="course-card <?= $is_applied ? 'applied' : ($can_reapply ? '' : '') ?>"
                 data-search="<?= strtolower($c['course_code'].' '.$c['course_name']) ?>">
                <div class="cc-header">
                    <span class="cc-code"><?= htmlspecialchars($c['course_code']) ?></span>
                    <?php if ($is_applied): ?>
                        <span class="badge badge-success">✓ Dalam Proses</span>
                    <?php elseif ($prev === 'rejected'): ?>
                        <span class="badge badge-danger">✗ Ditolak</span>
                    <?php elseif ($prev === 'dropped'): ?>
                        <span class="badge badge-gray">Dibatalkan</span>
                    <?php else: ?>
                        <span class="badge badge-success">Dibuka</span>
                    <?php endif; ?>
                </div>
                <div class="cc-name"><?= htmlspecialchars($c['course_name']) ?></div>
                <div class="cc-desc"><?= htmlspecialchars($c['description']) ?></div>
                <div class="cc-meta">
                    <span><i class="fas fa-graduation-cap"></i> <?= $c['education_level']==='asasi'?'Asasi':($c['education_level']==='diploma'?'Diploma':'Asasi & Diploma') ?></span>
                    <span><i class="fas fa-users"></i> <?= $c['enrolled_count'] ?>/<?= $c['max_students'] ?> pelajar</span>
                    <span><i class="fas fa-building-columns"></i> <?= htmlspecialchars($c['faculty']) ?></span>
                </div>
                <div class="cc-footer">
                    <?php if ($is_applied): ?>
                        <button class="btn btn-sm" disabled style="width:100%;justify-content:center;background:var(--green-50);color:var(--green-700);border:1px solid #bbf7d0">
                            <i class="fas fa-check"></i> Sudah Dipohon / Menunggu Kelulusan
                        </button>
                    <?php elseif ($total_approved > 0): ?>
                        <button class="btn btn-sm" disabled style="width:100%;justify-content:center;background:var(--gray-100);color:var(--gray-300)">
                            <i class="fas fa-lock"></i> Sudah Mempunyai Kursus
                        </button>
                    <?php elseif ($can_reapply): ?>
                        <form method="POST" style="margin:0" onsubmit="return confirm('Anda pasti mahu mohon semula kursus <?= htmlspecialchars($c['course_name'],ENT_QUOTES) ?> (<?= htmlspecialchars($c['course_code'],ENT_QUOTES) ?>)?')">
                            <input type="hidden" name="apply_course_id" value="<?= $c['course_id'] ?>">
                            <button type="submit" class="btn btn-sm" style="width:100%;justify-content:center;background:var(--yellow-50);color:var(--yellow-700);border:1px solid #fde68a">
                                <i class="fas fa-rotate-right"></i> Mohon Semula
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="margin:0" onsubmit="return confirm('Anda pasti mahu mohon kursus <?= htmlspecialchars($c['course_name'],ENT_QUOTES) ?> (<?= htmlspecialchars($c['course_code'],ENT_QUOTES) ?>)?')">
                            <input type="hidden" name="apply_course_id" value="<?= $c['course_id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;justify-content:center">
                                <i class="fas fa-plus"></i> Mohon Kursus Ini
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- ══ TAB: MY COURSES ══ -->
        <?php elseif ($tab === 'my_courses'): ?>
        <?php
        $approved_courses = array_filter($my_courses, fn($r) => $r['status'] === 'approved');
        ?>
        <?php if ($approved_courses): ?>
        <div class="course-grid">
            <?php foreach ($approved_courses as $r): ?>
            <div class="course-card" style="border-color:#bbf7d0">
                <div class="cc-header">
                    <span class="cc-code"><?= htmlspecialchars($r['course_code']) ?></span>
                    <span class="badge badge-success">✓ Diluluskan</span>
                </div>
                <div class="cc-name"><?= htmlspecialchars($r['course_name']) ?></div>
                <div class="cc-meta">
                    <span><i class="fas fa-graduation-cap"></i> <?= $r['education_level']==='asasi'?'Asasi':($r['education_level']==='diploma'?'Diploma':'Asasi & Diploma') ?></span>
                    <span><i class="fas fa-building-columns"></i> <?= htmlspecialchars($r['faculty']) ?></span>
                </div>
                <div class="cc-footer">
                    <span class="semester-tag">📅 <?= $r['semester'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:16px;padding:14px 18px;background:var(--green-50);border:1px solid #bbf7d0;border-radius:12px;font-size:13px;color:var(--green-700)">
            <i class="fas fa-circle-check" style="margin-right:8px"></i>
            Jumlah kursus diluluskan: <strong><?= $total_credits ?> kursus</strong>
        </div>
        <?php else: ?>
        <div class="card"><div class="empty-state"><div class="icon">📚</div><h4>Tiada kursus diluluskan lagi</h4><p>Permohonan anda sedang menunggu kelulusan pentadbir.</p></div></div>
        <?php endif; ?>

        <!-- ══ TAB: REG STATUS ══ -->
        <?php elseif ($tab === 'reg_status'): ?>
        <div class="card">
            <div class="card-header">
                <div><h3>📋 Status Semua Permohonan</h3><p>Senarai lengkap permohonan pendaftaran kursus anda</p></div>
            </div>
            <?php if ($my_courses): ?>
            <table>
                <thead>
                    <tr><th>#</th><th>Kod Kursus</th><th>Nama Kursus</th><th>Taraf</th><th>Semester</th><th>Status</th><th>Tarikh Mohon</th><th>Tindakan</th></tr>
                </thead>
                <tbody>
                <?php foreach ($my_courses as $i => $r): ?>
                <tr>
                    <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($r['course_code']) ?></span></td>
                    <td><?= htmlspecialchars($r['course_name']) ?></td>
                    <td style="text-align:center"><?= ucfirst($r['education_level'] ?? 'both') ?></td>
                    <td><span class="semester-tag"><?= $r['semester'] ?></span></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td><?= date('d M Y', strtotime($r['applied_at'])) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <form method="POST" style="margin:0" onsubmit="return confirm('Batalkan permohonan kursus ini?')">
                            <input type="hidden" name="drop_reg_id" value="<?= $r['reg_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-xmark"></i> Batal</button>
                        </form>
                        <?php else: ?>
                        <span style="color:var(--gray-300);font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><div class="icon">📭</div><h4>Tiada permohonan</h4><p>Anda belum membuat sebarang permohonan kursus.</p></div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main -->

<script>
// Search for view_courses tab
function filterCourses() {
    const q = document.getElementById('searchInput')?.value.toLowerCase() || '';
    document.querySelectorAll('#courseGrid .course-card').forEach(card => {
        const match = card.dataset.search.includes(q);
        card.style.display = match ? '' : 'none';
    });
}

// Filter by status
function filterByStatus(status, el) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('#courseGrid .course-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}

// Search for apply tab
function filterApply() {
    const q = document.getElementById('searchApply')?.value.toLowerCase() || '';
    document.querySelectorAll('#applyGrid .course-card').forEach(card => {
        card.style.display = card.dataset.search.includes(q) ? '' : 'none';
    });
}

// Validate change password form
function validateStudentPwForm() {
    const newPw = document.getElementById('studentNewPw').value;
    const confirmPw = document.getElementById('studentConfirmPw').value;
    if (newPw !== confirmPw) {
        alert('Kata laluan baharu dan pengesahan tidak sepadan.');
        return false;
    }
    if (newPw.length < 8) {
        alert('Kata laluan baharu mesti sekurang-kurangnya 8 aksara.');
        return false;
    }
    return true;
}


document
.getElementById("subjectSearch")
.addEventListener("keyup", function() {

    let value =
        this.value.toLowerCase();

    let rows =
        document.querySelectorAll(
            "#subjectTable tbody tr"
        );

    rows.forEach(function(row){

        let text =
            row.innerText.toLowerCase();

        row.style.display =
            text.includes(value)
            ? ""
            : "none";

    });

});

</script>
</body>
</html>