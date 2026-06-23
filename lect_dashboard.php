<?php
// =====================================================
// lect_dashboard.php — Lecturer Dashboard
// Student Registration System — UPTM
// =====================================================
session_start();
require_once 'db_connect.php';
redirectIfNotLoggedIn();

if ($_SESSION['role'] !== 'lecturer') {
    header("Location: login_page.php"); exit();
}

$lect_id = $_SESSION['user_id'];

// Fetch lecturer profile
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->bind_param("i", $lect_id);
$stmt->execute();
$lect = $stmt->get_result()->fetch_assoc();

$msg = $msg_type = '';
$tab = $_GET['tab'] ?? 'overview';

// ── Update Profile ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $dob       = $_POST['date_of_birth'] ?: null;
    $faculty   = trim($_POST['faculty']);
    $dept      = trim($_POST['department']);

    if (empty($full_name)) {
        $msg = 'Nama penuh wajib diisi.'; $msg_type = 'error';
    } else {
        $upd = $conn->prepare("UPDATE users SET full_name=?, phone=?, date_of_birth=?, faculty=?, department=? WHERE user_id=?");
        $upd->bind_param("sssssi", $full_name,$phone,$dob,$faculty,$dept,$lect_id);
        if ($upd->execute()) {
            $_SESSION['full_name'] = $full_name;
            $msg = 'Profil berjaya dikemaskini!'; $msg_type = 'success';
            $lect['full_name'] = $full_name; $lect['phone'] = $phone;
            $lect['date_of_birth'] = $dob; $lect['faculty'] = $faculty; $lect['department'] = $dept;
        } else { $msg = 'Ralat semasa kemaskini profil.'; $msg_type = 'error'; }
    }
    $tab = 'profile';
}

// ── Change Password ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password']     ?? '';
    $con = $_POST['confirm_password'] ?? '';

    if (!password_verify($cur, $lect['password'])) {
        $msg = 'Kata laluan semasa tidak betul.'; $msg_type = 'error';
    } elseif (strlen($new) < 8) {
        $msg = 'Kata laluan baharu mesti sekurang-kurangnya 8 aksara.'; $msg_type = 'error';
    } elseif ($new !== $con) {
        $msg = 'Kata laluan baharu dan pengesahan tidak sepadan.'; $msg_type = 'error';
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
        $upd->bind_param("si", $hashed, $lect_id);
        if ($upd->execute()) { $msg = 'Kata laluan berjaya ditukar!'; $msg_type = 'success'; }
        else { $msg = 'Ralat semasa menukar kata laluan.'; $msg_type = 'error'; }
    }
    $tab = 'profile';
}

// ══════════════════════════════════════════════════
// FETCH DATA
// ══════════════════════════════════════════════════

// Subjects this lecturer teaches (via class_lecturers)
$r = $conn->prepare("
    SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name,
           s.semester_no, s.credit_hours, s.status,
           c.course_code, c.course_name,
           COUNT(DISTINCT cl.class_id) AS class_count
    FROM class_lecturers cl
    JOIN subjects s  ON cl.subject_id = s.subject_id
    JOIN courses  c  ON s.course_id   = c.course_id
    WHERE cl.lecturer_id = ?
    GROUP BY s.subject_id, c.course_code, c.course_name
    ORDER BY c.course_code, s.semester_no, s.subject_code
");
$r->bind_param("i", $lect_id);
$r->execute();
$my_subjects = $r->get_result()->fetch_all(MYSQLI_ASSOC);

// Classes this lecturer teaches
$r = $conn->prepare("
    SELECT DISTINCT cls.class_id, cls.class_code, cls.class_name,
           cls.education_level, cls.semester_no, cls.max_students, cls.status,
           c.course_code, c.course_name,
           s.subject_code, s.subject_name,
           (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=cls.class_id) AS student_count
    FROM class_lecturers cl
    JOIN classes  cls ON cl.class_id    = cls.class_id
    JOIN courses  c   ON cls.course_id  = c.course_id
    LEFT JOIN subjects s ON cl.subject_id = s.subject_id
    WHERE cl.lecturer_id = ?
    ORDER BY c.course_code, cls.class_code
");
$r->bind_param("i", $lect_id);
$r->execute();
$my_classes = $r->get_result()->fetch_all(MYSQLI_ASSOC);

// Students this lecturer teaches — grouped by subject & class
// First get all class IDs this lecturer is assigned to
$class_ids = array_unique(array_column($my_classes, 'class_id'));

$students_by_subject = [];
if (!empty($class_ids)) {
    $in = implode(',', array_map('intval', $class_ids));
    $r  = $conn->query("
        SELECT
            u.user_id, u.full_name, u.student_no, u.email, u.phone,
            u.education_level, u.current_semester, u.faculty AS student_faculty,
            u.program AS student_program,
            cls.class_id, cls.class_code, cls.class_name,
            c.course_code, c.course_name,
            s.subject_code, s.subject_name,
            cl.subject_id
        FROM class_students cs
        JOIN users   u   ON cs.user_id   = u.user_id
        JOIN classes cls ON cs.class_id  = cls.class_id
        JOIN courses c   ON cls.course_id= c.course_id
        JOIN class_lecturers cl ON cl.class_id = cls.class_id AND cl.lecturer_id = $lect_id
        LEFT JOIN subjects s ON cl.subject_id = s.subject_id
        WHERE cs.class_id IN ($in)
        ORDER BY s.subject_code, c.course_code, u.full_name
    ");
    $all_students = $r->fetch_all(MYSQLI_ASSOC);

    // Group by subject_code (or 'Umum' if no subject)
    foreach ($all_students as $row) {
        $key = $row['subject_code'] ?: 'UMUM';
        $label = $row['subject_code']
            ? $row['subject_code'].' — '.$row['subject_name']
            : 'Pelajar Umum (Tiada Subjek Spesifik)';
        if (!isset($students_by_subject[$key])) {
            $students_by_subject[$key] = ['label' => $label, 'course' => $row['course_name'], 'rows' => []];
        }
        // Avoid duplicates within same subject group
        $uid = $row['user_id'];
        if (!isset($students_by_subject[$key]['rows'][$uid])) {
            $students_by_subject[$key]['rows'][$uid] = $row;
        }
    }
}

$total_subjects = count($my_subjects);
$total_classes  = count($my_classes);
$total_students = count(array_unique(array_column($all_students ?? [], 'user_id')));

function alertIcon($t) {
    return match($t) {'success'=>'circle-check','warning'=>'triangle-exclamation','info'=>'circle-info',default=>'circle-exclamation'};
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pensyarah — UPTM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --blue-deep:#0f2d6e; --blue-mid:#1a4db8; --blue-bright:#2563eb;
            --blue-light:#3b82f6; --blue-pale:#dbeafe;
            --white:#ffffff; --gray-50:#f8fafc; --gray-100:#f1f5f9;
            --gray-200:#e2e8f0; --gray-300:#cbd5e1; --gray-500:#64748b;
            --gray-700:#334155; --gray-900:#0f172a;
            --green-50:#f0fdf4; --green-500:#22c55e; --green-700:#15803d;
            --yellow-50:#fefce8; --yellow-700:#a16207;
            --red-50:#fef2f2; --red-500:#ef4444; --red-700:#b91c1c;
            --purple-50:#f5f3ff; --purple-700:#6d28d9;
            --teal-50:#f0fdfa; --teal-600:#0d9488;
            --sidebar-w:265px;
        }
        body { font-family:'Inter',sans-serif; background:var(--gray-50); color:var(--gray-700); display:flex; min-height:100vh; }

        /* SIDEBAR */
        .sidebar {
            width:var(--sidebar-w); flex-shrink:0;
            background:linear-gradient(175deg,#0a1f4e 0%,#1e40af 100%);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; bottom:0; z-index:100; overflow-y:auto;
        }
        .sidebar-brand { padding:26px 22px 18px; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo { font-size:26px; margin-bottom:6px; }
        .sidebar-brand h2 { color:var(--white); font-size:13px; font-weight:700; line-height:1.3; }
        .sidebar-brand span { color:rgba(255,255,255,0.5); font-size:11px; }

        .sidebar-profile { padding:18px 22px; border-bottom:1px solid rgba(255,255,255,0.1); }
        .profile-avatar { width:48px; height:48px; border-radius:12px; background:rgba(255,255,255,0.14); border:2px solid rgba(255,255,255,0.22); display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:9px; }
        .profile-name { color:var(--white); font-size:13px; font-weight:600; margin-bottom:2px; }
        .profile-sub  { color:rgba(255,255,255,0.5); font-size:11px; }
        .profile-badge { display:inline-block; margin-top:6px; background:rgba(13,148,136,0.25); border:1px solid rgba(13,148,136,0.4); border-radius:20px; padding:3px 10px; color:#5eead4; font-size:10px; font-weight:700; }

        .sidebar-nav { padding:14px 10px; flex:1; }
        .nav-label { color:rgba(255,255,255,0.32); font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; padding:0 12px; margin:14px 0 5px; }
        .nav-item { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:9px; cursor:pointer; color:rgba(255,255,255,0.62); font-size:13px; font-weight:500; text-decoration:none; transition:all .2s; margin-bottom:2px; }
        .nav-item i { width:17px; text-align:center; font-size:13px; }
        .nav-item:hover  { background:rgba(255,255,255,0.1); color:var(--white); }
        .nav-item.active { background:rgba(255,255,255,0.17); color:var(--white); font-weight:600; }

        .sidebar-footer { padding:14px 10px; border-top:1px solid rgba(255,255,255,0.1); }
        .btn-logout { display:flex; align-items:center; gap:10px; width:100%; padding:10px 13px; border-radius:9px; background:rgba(239,68,68,0.14); border:1px solid rgba(239,68,68,0.22); color:#fca5a5; font-size:13px; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; transition:all .2s; }
        .btn-logout:hover { background:rgba(239,68,68,0.26); }

        /* MAIN */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { background:var(--white); border-bottom:1px solid var(--gray-200); padding:15px 30px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .topbar h1 { font-size:17px; font-weight:700; color:var(--gray-900); }
        .topbar p  { font-size:12px; color:var(--gray-500); margin-top:1px; }
        .topbar-date { font-size:12px; color:var(--gray-500); }

        .content { padding:26px 30px; flex:1; }

        .alert { display:flex; align-items:flex-start; gap:11px; padding:13px 17px; border-radius:11px; font-size:14px; margin-bottom:22px; font-weight:500; line-height:1.5; }
        .alert-success { background:var(--green-50);  color:var(--green-700); border:1px solid #bbf7d0; }
        .alert-error   { background:var(--red-50);    color:var(--red-700);   border:1px solid #fecaca; }
        .alert-info    { background:var(--blue-pale);  color:var(--blue-mid);  border:1px solid #bfdbfe; }

        /* Stats */
        .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
        .stat-card { background:var(--white); border-radius:13px; padding:18px; border:1px solid var(--gray-200); display:flex; align-items:center; gap:14px; }
        .stat-icon { width:46px; height:46px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .si-blue   { background:var(--blue-pale); }
        .si-teal   { background:var(--teal-50); }
        .si-purple { background:var(--purple-50); }
        .stat-val  { font-size:26px; font-weight:700; color:var(--gray-900); line-height:1; }
        .stat-lbl  { font-size:12px; color:var(--gray-500); margin-top:3px; }

        /* Card */
        .card { background:var(--white); border-radius:13px; border:1px solid var(--gray-200); overflow:hidden; margin-bottom:22px; }
        .card-header { padding:17px 22px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
        .card-header h3 { font-size:15px; font-weight:700; color:var(--gray-900); }
        .card-header p  { font-size:12px; color:var(--gray-500); margin-top:2px; }
        .card-body { padding:22px; }

        /* Table */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        thead th { padding:10px 13px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--gray-500); background:var(--gray-50); border-bottom:1px solid var(--gray-200); white-space:nowrap; }
        tbody tr { border-bottom:1px solid var(--gray-100); transition:background .15s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:var(--gray-50); }
        tbody td { padding:11px 13px; font-size:13px; color:var(--gray-700); }

        /* Badges */
        .badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-blue   { background:var(--blue-pale); color:var(--blue-mid); }
        .badge-green  { background:var(--green-50);  color:var(--green-700); }
        .badge-yellow { background:var(--yellow-50); color:var(--yellow-700); }
        .badge-red    { background:var(--red-50);    color:var(--red-700); }
        .badge-gray   { background:var(--gray-100);  color:var(--gray-500); }
        .badge-purple { background:var(--purple-50); color:var(--purple-700); }
        .badge-teal   { background:var(--teal-50);   color:var(--teal-600); }

        /* Profile grid */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .profile-field label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--gray-500); display:block; margin-bottom:5px; }
        .profile-field .val  { font-size:13px; color:var(--gray-900); font-weight:500; padding:9px 13px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; }
        .profile-field .val.empty { color:var(--gray-300); font-style:italic; font-weight:400; }
        .profile-field.full  { grid-column:1/-1; }

        /* Form */
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
        .full { grid-column:1/-1; }
        .form-group label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--gray-500); margin-bottom:6px; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:10px 13px; border:1.5px solid var(--gray-200); border-radius:9px;
            font-size:13px; font-family:'Inter',sans-serif; color:var(--gray-700); background:var(--gray-50);
            outline:none; transition:border-color .2s; appearance:none;
        }
        .form-group input:focus, .form-group select:focus { border-color:var(--blue-bright); background:var(--white); box-shadow:0 0 0 3px rgba(37,99,235,0.08); }
        .form-group input[disabled] { background:var(--gray-100); color:var(--gray-300); cursor:not-allowed; }

        /* Buttons */
        .btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:8px; font-size:13px; font-weight:600; font-family:'Inter',sans-serif; cursor:pointer; border:none; transition:all .2s; text-decoration:none; }
        .btn-primary { background:var(--blue-bright); color:var(--white); }
        .btn-primary:hover { background:var(--blue-mid); }
        .btn-gray    { background:var(--gray-100); color:var(--gray-500); }
        .btn-gray:hover { background:var(--gray-200); }
        .btn-sm { padding:6px 11px; font-size:12px; }

        /* Subject group separator */
        .subject-group-header {
            background:linear-gradient(135deg,var(--blue-pale),#eff6ff);
            border:1px solid #bfdbfe; border-radius:10px;
            padding:12px 18px; margin:20px 0 12px;
            display:flex; align-items:center; gap:10px;
        }
        .subject-group-header .sub-code { font-size:13px; font-weight:700; color:var(--blue-bright); }
        .subject-group-header .sub-name { font-size:12px; color:var(--gray-500); }
        .subject-group-header .course-tag { margin-left:auto; }

        /* Empty */
        .empty { text-align:center; padding:40px 20px; }
        .empty .icon { font-size:44px; opacity:.3; margin-bottom:10px; }
        .empty p { font-size:13px; color:var(--gray-300); }

        /* Section card */
        .section-card { border:1px solid var(--gray-200); border-radius:12px; overflow:hidden; margin-bottom:18px; }
        .section-card-header { padding:13px 18px; background:var(--gray-50); border-bottom:1px solid var(--gray-200); display:flex; align-items:center; gap:10px; }
        .section-card-header h4 { font-size:13px; font-weight:700; color:var(--gray-900); flex:1; }

        @media (max-width:1100px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .two-col { grid-template-columns:1fr; } }
        @media (max-width:768px)  { .sidebar { transform:translateX(-100%); } .main { margin-left:0; } .profile-grid, .form-grid-2 { grid-template-columns:1fr; } .full { grid-column:1; } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo">📚</div>
        <h2>Student Registration<br>System</h2>
        <span>UPTM — Pensyarah Portal</span>
    </div>
    <div class="sidebar-profile">
        <div class="profile-avatar">👨‍🏫</div>
        <div class="profile-name"><?= htmlspecialchars($lect['full_name']) ?></div>
        <div class="profile-sub"><?= htmlspecialchars($lect['email']) ?></div>
        <span class="profile-badge">📚 Pensyarah</span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Utama</div>
        <a href="?tab=overview"  class="nav-item <?= $tab==='overview'?'active':'' ?>"><i class="fas fa-house"></i> Gambaran Keseluruhan</a>
        <a href="?tab=profile"   class="nav-item <?= $tab==='profile'?'active':'' ?>"><i class="fas fa-user"></i> Profil Saya</a>

        <div class="nav-label">Pengajaran</div>
        <a href="?tab=subjects"  class="nav-item <?= $tab==='subjects'?'active':'' ?>"><i class="fas fa-bookmark"></i> Subjek Saya</a>
        <a href="?tab=classes"   class="nav-item <?= $tab==='classes'?'active':'' ?>"><i class="fas fa-chalkboard"></i> Kelas Saya</a>
        <a href="?tab=students"  class="nav-item <?= $tab==='students'?'active':'' ?>"><i class="fas fa-users"></i> Pelajar Saya</a>
    </nav>
    <div class="sidebar-footer">
        <form method="POST" action="logout.php">
            <button type="submit" class="btn-logout"><i class="fas fa-right-from-bracket"></i> Log Keluar</button>
        </form>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div>
            <h1><?php
                $titles = ['overview'=>'Gambaran Keseluruhan','profile'=>'Profil Saya','subjects'=>'Subjek Saya','classes'=>'Kelas Saya','students'=>'Pelajar Saya'];
                echo $titles[$tab] ?? 'Dashboard';
            ?></h1>
            <p>Portal Pensyarah · <?= date('d M Y') ?></p>
        </div>
        <span class="topbar-date"><i class="fas fa-calendar" style="margin-right:5px;color:var(--gray-300)"></i><?= date('D, d M Y') ?></span>
    </div>

    <div class="content">

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <i class="fas fa-<?= alertIcon($msg_type) ?>" style="flex-shrink:0;margin-top:2px"></i>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
        <?php endif; ?>

        <!-- ══ OVERVIEW ══ -->
        <?php if ($tab === 'overview'): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon si-purple">📖</div>
                <div><div class="stat-val"><?= $total_subjects ?></div><div class="stat-lbl">Subjek Diajar</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-blue">🏫</div>
                <div><div class="stat-val"><?= $total_classes ?></div><div class="stat-lbl">Kelas / Section</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-teal">🎓</div>
                <div><div class="stat-val"><?= $total_students ?></div><div class="stat-lbl">Jumlah Pelajar</div></div>
            </div>
        </div>

        <div class="two-col">
            <!-- Subjects summary -->
            <div class="card">
                <div class="card-header">
                    <div><h3>📖 Subjek Saya</h3><p>Subjek yang anda diajar</p></div>
                    <a href="?tab=subjects" class="btn btn-primary btn-sm"><i class="fas fa-arrow-right"></i></a>
                </div>
                <?php if ($my_subjects): ?>
                <table><thead><tr><th>Kod</th><th>Nama Subjek</th><th>Kursus</th><th>Sem</th></tr></thead><tbody>
                <?php foreach ($my_subjects as $s): ?>
                <tr>
                    <td><span class="badge badge-purple"><?= htmlspecialchars($s['subject_code']) ?></span></td>
                    <td style="font-size:12px"><?= htmlspecialchars($s['subject_name']) ?></td>
                    <td><span class="badge badge-gray"><?= htmlspecialchars($s['course_code']) ?></span></td>
                    <td><?= $s['semester_no'] ? '<span class="badge badge-blue">Sem '.$s['semester_no'].'</span>' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else: ?>
                <div class="empty"><div class="icon">📖</div><p>Tiada subjek diassign lagi.</p></div>
                <?php endif; ?>
            </div>

            <!-- Classes summary -->
            <div class="card">
                <div class="card-header">
                    <div><h3>🏫 Kelas Saya</h3><p>Section yang anda kendalikan</p></div>
                    <a href="?tab=classes" class="btn btn-primary btn-sm"><i class="fas fa-arrow-right"></i></a>
                </div>
                <?php if ($my_classes): ?>
                <table><thead><tr><th>Kod</th><th>Kursus</th><th>Taraf</th><th>Pelajar</th></tr></thead><tbody>
                <?php foreach ($my_classes as $cl):
                    $edu = $cl['education_level'] ?? '';
                ?>
                <tr>
                    <td><span class="badge badge-blue" style="font-family:monospace"><?= htmlspecialchars($cl['class_code']) ?></span></td>
                    <td><span class="badge badge-gray"><?= htmlspecialchars($cl['course_code']) ?></span></td>
                    <td><span class="badge <?= $edu==='asasi'?'badge-yellow':'badge-teal' ?>"><?= ucfirst($edu) ?></span></td>
                    <td style="text-align:center"><strong><?= $cl['student_count'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else: ?>
                <div class="empty"><div class="icon">🏫</div><p>Tiada kelas diassign lagi.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══ PROFILE ══ -->
        <?php elseif ($tab === 'profile'): ?>
        <div class="two-col">
            <!-- Edit profile form -->
            <div class="card">
                <div class="card-header"><div><h3>👨‍🏫 Maklumat Pensyarah</h3><p>Kemaskini biodata anda</p></div></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-grid-2">
                            <div class="form-group full"><label>Nama Penuh *</label><input type="text" name="full_name" value="<?= htmlspecialchars($lect['full_name']) ?>" required></div>
                            <div class="form-group"><label>Username</label><input type="text" value="<?= htmlspecialchars($lect['username']) ?>" disabled></div>
                            <div class="form-group"><label>E-mel</label><input type="email" value="<?= htmlspecialchars($lect['email']) ?>" disabled></div>
                            <div class="form-group"><label>No. Telefon</label><input type="tel" name="phone" value="<?= htmlspecialchars($lect['phone'] ?? '') ?>" placeholder="0123456789"></div>
                            <div class="form-group"><label>Tarikh Lahir</label><input type="date" name="date_of_birth" value="<?= htmlspecialchars($lect['date_of_birth'] ?? '') ?>"></div>
                            <div class="form-group full"><label>Fakulti</label><input type="text" name="faculty" value="<?= htmlspecialchars($lect['faculty'] ?? '') ?>" placeholder="Cth: Faculty of Computing"></div>
                            <div class="form-group full"><label>Jabatan / Kepakaran</label><input type="text" name="department" value="<?= htmlspecialchars($lect['department'] ?? '') ?>" placeholder="Cth: Database & Software Engineering"></div>
                        </div>
                        <div style="margin-top:16px"><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan Perubahan</button></div>
                    </form>
                </div>
            </div>

            <!-- Account info + Change password -->
            <div>
                <div class="card">
                    <div class="card-header"><div><h3>🆔 Maklumat Akaun</h3></div></div>
                    <div class="card-body">
                        <div class="profile-grid">
                            <div class="profile-field"><label>Peranan</label><div class="val"><span class="badge badge-teal">📚 Pensyarah</span></div></div>
                            <div class="profile-field"><label>Status</label><div class="val"><span class="badge <?= $lect['status']==='active'?'badge-green':'badge-yellow' ?>"><?= ucfirst($lect['status']) ?></span></div></div>
                            <div class="profile-field"><label>Fakulti</label><div class="val <?= !$lect['faculty']?'empty':'' ?>"><?= $lect['faculty'] ?: 'Belum dikemaskini' ?></div></div>
                            <div class="profile-field"><label>Jabatan</label><div class="val <?= !$lect['department']?'empty':'' ?>"><?= $lect['department'] ?: 'Belum dikemaskini' ?></div></div>
                            <div class="profile-field full"><label>Tarikh Daftar</label><div class="val"><?= date('d M Y, h:i A', strtotime($lect['created_at'])) ?></div></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3>🔒 Tukar Kata Laluan</h3></div></div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return validateLectPw()">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group" style="margin-bottom:12px"><label>Kata Laluan Semasa *</label><input type="password" name="current_password" required></div>
                            <div class="form-group" style="margin-bottom:12px"><label>Kata Laluan Baharu *</label><input type="password" name="new_password" id="lectNewPw" minlength="8" required></div>
                            <div class="form-group" style="margin-bottom:16px"><label>Sahkan Kata Laluan *</label><input type="password" name="confirm_password" id="lectConfPw" minlength="8" required></div>
                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-key"></i> Tukar Kata Laluan</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ SUBJECTS ══ -->
        <?php elseif ($tab === 'subjects'): ?>
        <div class="card">
            <div class="card-header">
                <div><h3>📖 Senarai Subjek Saya</h3><p>Semua subjek yang anda kendalikan</p></div>
                <span class="badge badge-blue"><?= $total_subjects ?> subjek</span>
            </div>
            <?php if ($my_subjects): ?>
            <div class="table-wrap">
                <table><thead><tr><th>#</th><th>Kod Subjek</th><th>Nama Subjek</th><th>Kursus</th><th>Semester</th><th>Kredit</th><th>Kelas</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($my_subjects as $i => $s): ?>
                <tr>
                    <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                    <td><span class="badge badge-purple"><?= htmlspecialchars($s['subject_code']) ?></span></td>
                    <td><strong><?= htmlspecialchars($s['subject_name']) ?></strong></td>
                    <td><span class="badge badge-gray"><?= htmlspecialchars($s['course_code']) ?></span><br><small style="color:var(--gray-300)"><?= htmlspecialchars($s['course_name']) ?></small></td>
                    <td><?= $s['semester_no'] ? '<span class="badge badge-blue">Semester '.$s['semester_no'].'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                    <td style="text-align:center"><?= $s['credit_hours'] ?> Kredit</td>
                    <td style="text-align:center"><span class="badge badge-teal"><?= $s['class_count'] ?> kelas</span></td>
                    <td><span class="badge <?= $s['status']==='active'?'badge-green':'badge-gray' ?>"><?= $s['status']==='active'?'Aktif':'Tidak Aktif' ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
            <?php else: ?>
            <div class="empty"><div class="icon">📖</div><p>Tiada subjek diassign kepada anda lagi.<br>Sila hubungi pihak staf.</p></div>
            <?php endif; ?>
        </div>

        <!-- ══ CLASSES ══ -->
        <?php elseif ($tab === 'classes'): ?>
        <div class="card">
            <div class="card-header">
                <div><h3>🏫 Senarai Kelas / Section Saya</h3><p>Semua kelas yang anda kendalikan</p></div>
                <span class="badge badge-blue"><?= $total_classes ?> kelas</span>
            </div>
            <?php if ($my_classes): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Kod Section</th><th>Nama Kelas</th><th>Kursus</th><th>Subjek Diajar</th><th>Taraf</th><th>Semester</th><th>Pelajar</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($my_classes as $i => $cl):
                        $edu = $cl['education_level'] ?? '';
                    ?>
                    <tr>
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td><span class="badge badge-blue" style="font-family:monospace;letter-spacing:1px"><?= htmlspecialchars($cl['class_code']) ?></span></td>
                        <td style="font-size:12px;max-width:160px"><?= htmlspecialchars($cl['class_name']) ?></td>
                        <td><span class="badge badge-gray"><?= htmlspecialchars($cl['course_code']) ?></span></td>
                        <td><?= $cl['subject_code'] ? '<span class="badge badge-purple">'.htmlspecialchars($cl['subject_code']).'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td><span class="badge <?= $edu==='asasi'?'badge-yellow':'badge-teal' ?>"><?= ucfirst($edu) ?></span></td>
                        <td><?= $cl['semester_no'] ? '<span class="badge badge-blue">Sem '.$cl['semester_no'].'</span>' : '—' ?></td>
                        <td style="text-align:center"><strong><?= $cl['student_count'] ?></strong>/<?= $cl['max_students'] ?></td>
                        <td><span class="badge <?= $cl['status']==='active'?'badge-green':'badge-gray' ?>"><?= $cl['status']==='active'?'Aktif':'Tidak Aktif' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty"><div class="icon">🏫</div><p>Tiada kelas diassign kepada anda lagi.<br>Sila hubungi pihak staf.</p></div>
            <?php endif; ?>
        </div>

        <!-- ══ STUDENTS ══ -->
        <?php elseif ($tab === 'students'): ?>

        <?php if (empty($students_by_subject)): ?>
        <div class="card"><div class="empty"><div class="icon">🎓</div><p>Tiada pelajar dalam kelas anda lagi.</p></div></div>
        <?php else: ?>

        <div style="margin-bottom:18px;padding:13px 18px;background:var(--blue-pale);border:1px solid #bfdbfe;border-radius:11px;font-size:13px;color:var(--blue-mid)">
            <i class="fas fa-circle-info" style="margin-right:8px"></i>
            Pelajar dipaparkan mengikut <strong>subjek</strong> dan <strong>kursus</strong> mereka. Jumlah keseluruhan: <strong><?= $total_students ?> pelajar</strong>.
        </div>

        <?php foreach ($students_by_subject as $sub_key => $group):
            $rows = array_values($group['rows']);
        ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <h3>
                        <?php if ($sub_key === 'UMUM'): ?>
                        👥 Pelajar Umum
                        <?php else: ?>
                        📖 <?= htmlspecialchars($group['label']) ?>
                        <?php endif; ?>
                    </h3>
                    <p>Kursus: <?= htmlspecialchars($group['course']) ?> &nbsp;·&nbsp; <?= count($rows) ?> pelajar</p>
                </div>
                <span class="badge badge-teal"><?= count($rows) ?> pelajar</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Nama Pelajar</th><th>No. Pelajar</th><th>E-mel</th><th>Taraf Pendidikan</th><th>Semester</th><th>Section / Kelas</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $i => $s):
                        $edu = $s['education_level'] ?? '';
                        $sem = $s['current_semester'] ?? '';
                        $max_sem = $edu==='asasi' ? 2 : ($edu==='diploma' ? 4 : '—');
                    ?>
                    <tr>
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
                        <td><?= $s['student_no'] ? '<span class="badge badge-gray">'.htmlspecialchars($s['student_no']).'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td style="font-size:12px"><?= htmlspecialchars($s['email']) ?></td>
                        <td>
                            <?php if ($edu): ?>
                            <span class="badge <?= $edu==='asasi'?'badge-yellow':'badge-teal' ?>"><?= ucfirst($edu) ?></span>
                            <?php else: ?><span style="color:var(--gray-300)">—</span><?php endif; ?>
                        </td>
                        <td><?= $sem ? '<span class="badge badge-blue">Sem '.$sem.($max_sem!=='—'?' / '.$max_sem:'').'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td><span class="badge badge-blue" style="font-family:monospace"><?= htmlspecialchars($s['class_code']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
function validateLectPw() {
    const pw  = document.getElementById('lectNewPw').value;
    const cpw = document.getElementById('lectConfPw').value;
    if (pw.length < 8) { alert('Kata laluan baharu mesti sekurang-kurangnya 8 aksara.'); return false; }
    if (pw !== cpw)    { alert('Kata laluan tidak sepadan.'); return false; }
    return true;
}
</script>
</body>
</html>
