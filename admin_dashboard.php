<?php
// =====================================================
// admin_dashboard.php — Admin Dashboard
// Student Registration System — UPTM
// =====================================================
session_start();
require_once 'db_connect.php';
redirectIfNotLoggedIn();

if ($_SESSION['role'] !== 'admin') {
    header("Location: login_page.php"); exit();
}

$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$msg = $msg_type = '';
$tab = $_GET['tab'] ?? 'overview';

// ══════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════

// ── Update User Role ──────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $uid      = intval($_POST['user_id']);
    $new_role = $_POST['new_role'] ?? '';
    $allowed  = ['student','staff','lecturer','admin'];

    if (!$uid || !in_array($new_role, $allowed)) {
        $msg = 'Maklumat tidak sah.'; $msg_type = 'error';
    } elseif ($uid === $admin_id) {
        $msg = 'Anda tidak boleh mengubah role akaun anda sendiri.'; $msg_type = 'warning';
    } else {
        $upd = $conn->prepare("UPDATE users SET role=? WHERE user_id=?");
        $upd->bind_param("si", $new_role, $uid);
        if ($upd->execute()) { $msg = 'Role pengguna berjaya dikemaskini!'; $msg_type = 'success'; }
        else { $msg = 'Ralat semasa kemaskini role.'; $msg_type = 'error'; }
    }
    $tab = 'users';
}

// ── Update User Status ────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $uid        = intval($_POST['user_id']);
    $new_status = $_POST['new_status'] ?? '';
    $allowed    = ['active','inactive','pending'];

    if (!$uid || !in_array($new_status, $allowed)) {
        $msg = 'Maklumat tidak sah.'; $msg_type = 'error';
    } elseif ($uid === $admin_id) {
        $msg = 'Anda tidak boleh mengubah status akaun anda sendiri.'; $msg_type = 'warning';
    } else {
        $upd = $conn->prepare("UPDATE users SET status=? WHERE user_id=?");
        $upd->bind_param("si", $new_status, $uid);
        if ($upd->execute()) { $msg = 'Status pengguna berjaya dikemaskini!'; $msg_type = 'success'; }
        else { $msg = 'Ralat semasa kemaskini status.'; $msg_type = 'error'; }
    }
    $tab = 'users';
}

// ── Delete User ───────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $uid = intval($_POST['user_id']);
    if ($uid === $admin_id) {
        $msg = 'Anda tidak boleh memadam akaun anda sendiri.'; $msg_type = 'warning';
    } else {
        $del = $conn->prepare("DELETE FROM users WHERE user_id=?");
        $del->bind_param("i", $uid);
        if ($del->execute() && $del->affected_rows > 0) { $msg = 'Pengguna berjaya dipadam.'; $msg_type = 'info'; }
        else { $msg = 'Ralat semasa memadam pengguna.'; $msg_type = 'error'; }
    }
    $tab = 'users';
}

// ── Approve Pending Users ─────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'approve_user') {
    $uid = intval($_POST['user_id']);
    $upd = $conn->prepare("UPDATE users SET status='active' WHERE user_id=? AND status='pending'");
    $upd->bind_param("i", $uid);
    if ($upd->execute() && $upd->affected_rows > 0) { $msg = 'Akaun pengguna berjaya diluluskan!'; $msg_type = 'success'; }
    else { $msg = 'Gagal meluluskan akaun.'; $msg_type = 'error'; }
    $tab = 'users';
}

// ── Bulk Approve All Pending ──────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'approve_all_pending') {
    $upd = $conn->query("UPDATE users SET status='active' WHERE status='pending'");
    $msg = "Semua akaun pending berjaya diluluskan!"; $msg_type = 'success';
    $tab = 'users';
}

// ══════════════════════════════════════════════════
// FETCH DATA
// ══════════════════════════════════════════════════

// All users
$all_users = $conn->query("SELECT * FROM users ORDER BY role, full_name")->fetch_all(MYSQLI_ASSOC);

// Group by role
$students  = array_filter($all_users, fn($u) => $u['role'] === 'student');
$staffs    = array_filter($all_users, fn($u) => $u['role'] === 'staff');
$lecturers = array_filter($all_users, fn($u) => $u['role'] === 'lecturer');
$admins    = array_filter($all_users, fn($u) => $u['role'] === 'admin');
$pending   = array_filter($all_users, fn($u) => $u['status'] === 'pending');

// Stats
$total_users     = count($all_users);
$total_students  = count($students);
$total_staff     = count($staffs);
$total_lecturers = count($lecturers);
$total_pending   = count($pending);
$total_courses   = $conn->query("SELECT COUNT(*) FROM courses")->fetch_row()[0];
$total_classes   = $conn->query("SELECT COUNT(*) FROM classes")->fetch_row()[0];
$total_subjects  = $conn->query("SELECT COUNT(*) FROM subjects")->fetch_row()[0];

// Staff with assignments
$staff_detail = $conn->query("
    SELECT u.*,
        (SELECT COUNT(DISTINCT cl.class_id)
         FROM class_students cl WHERE cl.user_id = u.user_id) AS classes_assigned,
        (SELECT COUNT(DISTINCT cr.course_id)
         FROM course_registrations cr WHERE cr.user_id = u.user_id) AS courses_registered
    FROM users u WHERE u.role = 'staff'
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

// Lecturer detail with classes + subjects
$lect_detail = $conn->query("
    SELECT u.*,
        (SELECT COUNT(DISTINCT cl.class_id)
         FROM class_lecturers cl WHERE cl.lecturer_id = u.user_id) AS classes_count,
        (SELECT COUNT(DISTINCT cl.subject_id)
         FROM class_lecturers cl WHERE cl.lecturer_id = u.user_id AND cl.subject_id IS NOT NULL) AS subjects_count,
        (SELECT GROUP_CONCAT(DISTINCT s.subject_code ORDER BY s.subject_code SEPARATOR ', ')
         FROM class_lecturers cl JOIN subjects s ON cl.subject_id = s.subject_id
         WHERE cl.lecturer_id = u.user_id) AS subject_codes
    FROM users u WHERE u.role = 'lecturer'
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

// Login activity (last 10)
$login_activity = $conn->query("
    SELECT ll.*, u.full_name, u.role
    FROM login_logs ll JOIN users u ON ll.user_id = u.user_id
    ORDER BY ll.login_time DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Course registrations summary
$reg_summary = $conn->query("
    SELECT c.course_code, c.course_name, c.education_level,
        COUNT(cr.reg_id) AS total_reg,
        SUM(cr.status='pending')  AS pending_count,
        SUM(cr.status='approved') AS approved_count,
        SUM(cr.status='rejected') AS rejected_count
    FROM courses c
    LEFT JOIN course_registrations cr ON c.course_id = cr.course_id
    GROUP BY c.course_id
    ORDER BY total_reg DESC
")->fetch_all(MYSQLI_ASSOC);

function alertIcon($t) {
    return match($t) {'success'=>'circle-check','warning'=>'triangle-exclamation','info'=>'circle-info',default=>'circle-exclamation'};
}
function roleBadge($role) {
    return match($role) {
        'student'  => '<span class="badge badge-blue">🎓 Pelajar</span>',
        'staff'    => '<span class="badge badge-yellow">💼 Staf</span>',
        'lecturer' => '<span class="badge badge-teal">📚 Pensyarah</span>',
        'admin'    => '<span class="badge badge-red">🔑 Admin</span>',
        default    => '<span class="badge badge-gray">'.htmlspecialchars($role).'</span>',
    };
}
function statusBadge($s) {
    return match($s) {
        'active'   => '<span class="badge badge-green">Aktif</span>',
        'pending'  => '<span class="badge badge-yellow">Pending</span>',
        'inactive' => '<span class="badge badge-red">Tidak Aktif</span>',
        default    => '<span class="badge badge-gray">'.htmlspecialchars($s).'</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — UPTM</title>
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
            --yellow-50:#fefce8; --yellow-500:#eab308; --yellow-700:#a16207;
            --red-50:#fef2f2; --red-500:#ef4444; --red-700:#b91c1c;
            --purple-50:#f5f3ff; --purple-700:#6d28d9;
            --teal-50:#f0fdfa; --teal-600:#0d9488;
            --orange-50:#fff7ed; --orange-600:#ea580c;
            --sidebar-w:270px;
        }
        body { font-family:'Inter',sans-serif; background:var(--gray-50); color:var(--gray-700); display:flex; min-height:100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width:var(--sidebar-w); flex-shrink:0;
            background:linear-gradient(175deg,#0a1228 0%,#0f2d6e 50%,#1a3a8f 100%);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; bottom:0; z-index:100; overflow-y:auto;
        }
        .sidebar-brand { padding:26px 22px 18px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .sidebar-brand .logo { font-size:28px; margin-bottom:6px; }
        .sidebar-brand h2 { color:var(--white); font-size:13px; font-weight:700; line-height:1.3; }
        .sidebar-brand span { color:rgba(255,255,255,0.45); font-size:11px; }

        .sidebar-profile { padding:18px 22px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .profile-avatar { width:46px; height:46px; border-radius:12px; background:rgba(255,165,0,0.2); border:2px solid rgba(255,165,0,0.4); display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:8px; }
        .profile-name { color:var(--white); font-size:13px; font-weight:600; margin-bottom:2px; }
        .profile-sub  { color:rgba(255,255,255,0.45); font-size:11px; }
        .profile-badge { display:inline-block; margin-top:6px; background:rgba(239,68,68,0.2); border:1px solid rgba(239,68,68,0.4); border-radius:20px; padding:3px 10px; color:#fca5a5; font-size:10px; font-weight:700; }

        /* Pending alert in sidebar */
        .sidebar-alert { margin:12px; background:rgba(234,179,8,0.15); border:1px solid rgba(234,179,8,0.3); border-radius:10px; padding:10px 13px; display:flex; align-items:center; gap:9px; cursor:pointer; text-decoration:none; }
        .sidebar-alert span { color:#fde68a; font-size:12px; font-weight:600; }
        .sidebar-alert .count { background:var(--yellow-500); color:var(--white); border-radius:20px; padding:2px 8px; font-size:11px; font-weight:700; }

        .sidebar-nav { padding:10px 10px; flex:1; }
        .nav-label { color:rgba(255,255,255,0.28); font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; padding:0 12px; margin:14px 0 5px; }
        .nav-item { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:9px; cursor:pointer; color:rgba(255,255,255,0.6); font-size:13px; font-weight:500; text-decoration:none; transition:all .2s; margin-bottom:2px; }
        .nav-item i { width:17px; text-align:center; font-size:13px; }
        .nav-item:hover  { background:rgba(255,255,255,0.09); color:var(--white); }
        .nav-item.active { background:rgba(255,255,255,0.15); color:var(--white); font-weight:600; }
        .nav-item .nav-badge { margin-left:auto; background:var(--yellow-500); color:var(--white); border-radius:20px; padding:1px 7px; font-size:10px; font-weight:700; }

        .sidebar-footer { padding:12px 10px; border-top:1px solid rgba(255,255,255,0.08); }
        .btn-logout { display:flex; align-items:center; gap:10px; width:100%; padding:10px 13px; border-radius:9px; background:rgba(239,68,68,0.14); border:1px solid rgba(239,68,68,0.22); color:#fca5a5; font-size:13px; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; transition:all .2s; }
        .btn-logout:hover { background:rgba(239,68,68,0.26); }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { background:var(--white); border-bottom:1px solid var(--gray-200); padding:15px 30px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .topbar h1 { font-size:17px; font-weight:700; color:var(--gray-900); }
        .topbar p  { font-size:12px; color:var(--gray-500); margin-top:1px; }

        .content { padding:26px 30px; flex:1; }

        /* ── Alert ── */
        .alert { display:flex; align-items:flex-start; gap:11px; padding:13px 17px; border-radius:11px; font-size:14px; margin-bottom:22px; font-weight:500; line-height:1.5; }
        .alert-success { background:var(--green-50);  color:var(--green-700); border:1px solid #bbf7d0; }
        .alert-warning { background:var(--yellow-50); color:var(--yellow-700);border:1px solid #fde68a; }
        .alert-error   { background:var(--red-50);    color:var(--red-700);   border:1px solid #fecaca; }
        .alert-info    { background:var(--blue-pale);  color:var(--blue-mid);  border:1px solid #bfdbfe; }

        /* ── Stats ── */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
        .stats-grid-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; margin-bottom:22px; }
        .stat-card { background:var(--white); border-radius:13px; padding:18px 20px; border:1px solid var(--gray-200); display:flex; align-items:center; gap:14px; }
        .stat-card.highlight { border-left:4px solid var(--yellow-500); }
        .stat-icon { width:46px; height:46px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .si-blue   { background:var(--blue-pale); }
        .si-green  { background:var(--green-50); }
        .si-yellow { background:var(--yellow-50); }
        .si-red    { background:var(--red-50); }
        .si-purple { background:var(--purple-50); }
        .si-teal   { background:var(--teal-50); }
        .si-orange { background:var(--orange-50); }
        .stat-val  { font-size:26px; font-weight:700; color:var(--gray-900); line-height:1; }
        .stat-lbl  { font-size:12px; color:var(--gray-500); margin-top:3px; }

        /* ── Card ── */
        .card { background:var(--white); border-radius:13px; border:1px solid var(--gray-200); overflow:hidden; margin-bottom:22px; }
        .card-header { padding:16px 22px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
        .card-header h3 { font-size:15px; font-weight:700; color:var(--gray-900); }
        .card-header p  { font-size:12px; color:var(--gray-500); margin-top:2px; }
        .card-body { padding:22px; }

        /* ── Table ── */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        thead th { padding:10px 13px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--gray-500); background:var(--gray-50); border-bottom:1px solid var(--gray-200); white-space:nowrap; }
        tbody tr { border-bottom:1px solid var(--gray-100); transition:background .15s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:var(--gray-50); }
        tbody td { padding:11px 13px; font-size:13px; color:var(--gray-700); vertical-align:middle; }

        /* ── Badges ── */
        .badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge-blue   { background:var(--blue-pale); color:var(--blue-mid); }
        .badge-green  { background:var(--green-50);  color:var(--green-700); }
        .badge-yellow { background:var(--yellow-50); color:var(--yellow-700); }
        .badge-red    { background:var(--red-50);    color:var(--red-700); }
        .badge-gray   { background:var(--gray-100);  color:var(--gray-500); }
        .badge-purple { background:var(--purple-50); color:var(--purple-700); }
        .badge-teal   { background:var(--teal-50);   color:var(--teal-600); }
        .badge-orange { background:var(--orange-50); color:var(--orange-600); }

        /* ── Buttons ── */
        .btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:600; font-family:'Inter',sans-serif; cursor:pointer; border:none; transition:all .2s; text-decoration:none; white-space:nowrap; }
        .btn-primary { background:var(--blue-bright); color:var(--white); }
        .btn-primary:hover { background:var(--blue-mid); }
        .btn-success { background:var(--green-50); color:var(--green-700); border:1px solid #bbf7d0; }
        .btn-success:hover { background:#dcfce7; }
        .btn-warning { background:var(--yellow-50); color:var(--yellow-700); border:1px solid #fde68a; }
        .btn-warning:hover { background:#fef9c3; }
        .btn-danger  { background:var(--red-50); color:var(--red-700); border:1px solid #fecaca; }
        .btn-danger:hover { background:#fee2e2; }
        .btn-gray    { background:var(--gray-100); color:var(--gray-500); }
        .btn-gray:hover { background:var(--gray-200); }
        .btn-sm { padding:5px 10px; font-size:11px; }
        .btn-print { background:var(--purple-50); color:var(--purple-700); border:1px solid #e9d5ff; }
        .btn-print:hover { background:#f3e8ff; }

        /* ── Search ── */
        .search-wrap { position:relative; }
        .search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--gray-300); font-size:13px; }
        .search-wrap input { width:100%; padding:9px 12px 9px 36px; border:1.5px solid var(--gray-200); border-radius:9px; font-size:13px; font-family:'Inter',sans-serif; color:var(--gray-700); background:var(--gray-50); outline:none; transition:border-color .2s; }
        .search-wrap input:focus { border-color:var(--blue-bright); background:var(--white); }

        /* ── Filter bar ── */
        .filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px; }

        /* ── Role tabs ── */
        .role-tabs { display:flex; gap:6px; margin-bottom:18px; flex-wrap:wrap; }
        .role-tab { padding:7px 16px; border-radius:20px; font-size:12px; font-weight:600; cursor:pointer; border:1.5px solid var(--gray-200); background:var(--white); color:var(--gray-500); transition:all .2s; }
        .role-tab.active-student  { background:var(--blue-pale);  color:var(--blue-mid);    border-color:#bfdbfe; }
        .role-tab.active-staff    { background:var(--yellow-50);  color:var(--yellow-700);  border-color:#fde68a; }
        .role-tab.active-lecturer { background:var(--teal-50);    color:var(--teal-600);    border-color:#99f6e4; }
        .role-tab.active-admin    { background:var(--red-50);     color:var(--red-700);     border-color:#fecaca; }
        .role-tab.active-all      { background:var(--gray-900);   color:var(--white);       border-color:var(--gray-900); }

        /* ── User detail row expand ── */
        .detail-row { display:none; background:#fafbff; }
        .detail-row.show { display:table-row; }
        .detail-box { padding:14px 20px; display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        .detail-field label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--gray-300); display:block; margin-bottom:3px; }
        .detail-field .val  { font-size:12px; color:var(--gray-700); font-weight:500; }
        .detail-field .val.empty { color:var(--gray-300); font-style:italic; }

        /* ── Inline role/status edit form ── */
        .inline-form { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .inline-select { padding:5px 10px; border:1.5px solid var(--gray-200); border-radius:7px; font-size:12px; font-family:'Inter',sans-serif; color:var(--gray-700); background:var(--gray-50); outline:none; cursor:pointer; appearance:none; }
        .inline-select:focus { border-color:var(--blue-bright); }

        /* ── Pending section ── */
        .pending-banner { background:linear-gradient(135deg,#fef9c3,#fef3c7); border:1px solid #fde68a; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
        .pending-banner .info { display:flex; align-items:center; gap:12px; }
        .pending-banner .icon { font-size:28px; }
        .pending-banner h4 { font-size:15px; font-weight:700; color:var(--yellow-700); }
        .pending-banner p  { font-size:13px; color:#92400e; margin-top:2px; }

        /* ── Report cards ── */
        .report-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
        .report-card { border:1.5px solid var(--gray-200); border-radius:12px; padding:20px; cursor:pointer; transition:all .2s; background:var(--white); }
        .report-card:hover { border-color:var(--blue-light); box-shadow:0 4px 16px rgba(37,99,235,0.1); }
        .report-card .rc-icon { font-size:32px; margin-bottom:12px; }
        .report-card h4 { font-size:14px; font-weight:700; color:var(--gray-900); margin-bottom:5px; }
        .report-card p  { font-size:12px; color:var(--gray-500); line-height:1.5; margin-bottom:14px; }

        /* ── Print area ── */
        .print-area { display:none; }
        @media print {
            body * { visibility:hidden; }
            .print-area, .print-area * { visibility:visible; }
            .print-area { display:block !important; position:absolute; left:0; top:0; width:100%; }
            .no-print { display:none !important; }
        }

        /* ── Empty ── */
        .empty { text-align:center; padding:36px; }
        .empty .icon { font-size:40px; opacity:.3; margin-bottom:10px; }
        .empty p { font-size:13px; color:var(--gray-300); }

        /* ── Two col ── */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }

        /* ── Activity list ── */
        .activity-list { padding:0; }
        .activity-item { display:flex; align-items:center; gap:12px; padding:11px 22px; border-bottom:1px solid var(--gray-100); }
        .activity-item:last-child { border-bottom:none; }
        .activity-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .activity-dot.success { background:var(--green-500); }
        .activity-dot.failed  { background:var(--red-500); }
        .activity-meta { font-size:12px; color:var(--gray-300); margin-left:auto; white-space:nowrap; }

        @media (max-width:1200px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .two-col { grid-template-columns:1fr; } .report-grid { grid-template-columns:1fr; } }
        @media (max-width:768px)  { .sidebar { transform:translateX(-100%); } .main { margin-left:0; } .detail-box { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo">🔑</div>
        <h2>Student Registration<br>System</h2>
        <span>UPTM — Admin Portal</span>
    </div>
    <div class="sidebar-profile">
        <div class="profile-avatar">👑</div>
        <div class="profile-name"><?= htmlspecialchars($admin['full_name']) ?></div>
        <div class="profile-sub"><?= htmlspecialchars($admin['email']) ?></div>
        <span class="profile-badge">🔑 Administrator</span>
    </div>

    <?php if ($total_pending > 0): ?>
    <a href="?tab=users&filter=pending" class="sidebar-alert no-print">
        <i class="fas fa-bell" style="color:#fde68a;font-size:14px"></i>
        <span><?= $total_pending ?> akaun menunggu kelulusan</span>
        <span class="count"><?= $total_pending ?></span>
    </a>
    <?php endif; ?>

    <nav class="sidebar-nav">
        <div class="nav-label">Utama</div>
        <a href="?tab=overview"  class="nav-item <?= $tab==='overview'?'active':'' ?>"><i class="fas fa-house"></i> Gambaran Keseluruhan</a>

        <div class="nav-label">Pengurusan Pengguna</div>
        <a href="?tab=users"     class="nav-item <?= $tab==='users'?'active':'' ?>">
            <i class="fas fa-users"></i> Semua Pengguna
            <?php if ($total_pending > 0): ?><span class="nav-badge"><?= $total_pending ?></span><?php endif; ?>
        </a>
        <a href="?tab=staff"     class="nav-item <?= $tab==='staff'?'active':'' ?>"><i class="fas fa-briefcase"></i> Senarai Staf</a>
        <a href="?tab=lecturers" class="nav-item <?= $tab==='lecturers'?'active':'' ?>"><i class="fas fa-chalkboard-user"></i> Senarai Pensyarah</a>

        <div class="nav-label">Laporan</div>
        <a href="?tab=reports"   class="nav-item <?= $tab==='reports'?'active':'' ?>"><i class="fas fa-chart-bar"></i> Jana Laporan</a>
    </nav>
    <div class="sidebar-footer">
        <form method="POST" action="logout.php">
            <button type="submit" class="btn-logout"><i class="fas fa-right-from-bracket"></i> Log Keluar</button>
        </form>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main">
    <div class="topbar">
        <div>
            <h1><?php
                $titles = ['overview'=>'Gambaran Keseluruhan','users'=>'Pengurusan Pengguna','staff'=>'Senarai Staf','lecturers'=>'Senarai Pensyarah','reports'=>'Jana Laporan'];
                echo $titles[$tab] ?? 'Dashboard';
            ?></h1>
            <p>Admin Portal · <?= date('d M Y, h:i A') ?></p>
        </div>
        <span style="font-size:12px;color:var(--gray-500)"><i class="fas fa-calendar" style="margin-right:5px;color:var(--gray-300)"></i><?= date('D, d M Y') ?></span>
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
            <div class="stat-card"><div class="stat-icon si-blue">👥</div><div><div class="stat-val"><?= $total_users ?></div><div class="stat-lbl">Jumlah Pengguna</div></div></div>
            <div class="stat-card"><div class="stat-icon si-green">🎓</div><div><div class="stat-val"><?= $total_students ?></div><div class="stat-lbl">Pelajar</div></div></div>
            <div class="stat-card"><div class="stat-icon si-teal">📚</div><div><div class="stat-val"><?= $total_lecturers ?></div><div class="stat-lbl">Pensyarah</div></div></div>
            <div class="stat-card highlight"><div class="stat-icon si-yellow">⏳</div><div><div class="stat-val"><?= $total_pending ?></div><div class="stat-lbl">Pending Kelulusan</div></div></div>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon si-yellow">💼</div><div><div class="stat-val"><?= $total_staff ?></div><div class="stat-lbl">Staf</div></div></div>
            <div class="stat-card"><div class="stat-icon si-purple">📖</div><div><div class="stat-val"><?= $total_subjects ?></div><div class="stat-lbl">Subjek</div></div></div>
            <div class="stat-card"><div class="stat-icon si-blue">📚</div><div><div class="stat-val"><?= $total_courses ?></div><div class="stat-lbl">Kursus</div></div></div>
            <div class="stat-card"><div class="stat-icon si-teal">🏫</div><div><div class="stat-val"><?= $total_classes ?></div><div class="stat-lbl">Kelas/Section</div></div></div>
        </div>

        <?php if ($total_pending > 0): ?>
        <div class="pending-banner">
            <div class="info">
                <span class="icon">⚠️</span>
                <div>
                    <h4><?= $total_pending ?> akaun menunggu kelulusan</h4>
                    <p>Akaun baharu mendaftar dan memerlukan kelulusan anda sebelum boleh log masuk.</p>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <form method="POST"><input type="hidden" name="action" value="approve_all_pending"><button type="submit" class="btn btn-success" onclick="return confirm('Luluskan semua <?= $total_pending ?> akaun pending?')"><i class="fas fa-check-double"></i> Lulus Semua</button></form>
                <a href="?tab=users&filter=pending" class="btn btn-warning"><i class="fas fa-eye"></i> Semak Satu-Satu</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="two-col">
            <!-- Recent Pending -->
            <div class="card">
                <div class="card-header"><div><h3>⏳ Akaun Pending Terkini</h3><p>Menunggu kelulusan admin</p></div><a href="?tab=users&filter=pending" class="btn btn-primary btn-sm"><i class="fas fa-arrow-right"></i></a></div>
                <?php $recent_pending = array_slice(array_values($pending), 0, 5); ?>
                <?php if ($recent_pending): ?>
                <table><thead><tr><th>Nama</th><th>Role</th><th>Tarikh Daftar</th><th>Tindakan</th></tr></thead><tbody>
                <?php foreach ($recent_pending as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u['full_name']) ?></strong><br><small style="color:var(--gray-300)"><?= htmlspecialchars($u['username']) ?></small></td>
                    <td><?= roleBadge($u['role']) ?></td>
                    <td style="font-size:11px;color:var(--gray-300)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve_user">
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Lulus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else: ?><div class="empty"><div class="icon">✅</div><p>Tiada akaun pending.</p></div><?php endif; ?>
            </div>

            <!-- Login Activity -->
            <div class="card">
                <div class="card-header"><div><h3>🕐 Aktiviti Log Masuk Terkini</h3><p>10 aktiviti terakhir</p></div></div>
                <?php if ($login_activity): ?>
                <div class="activity-list">
                <?php foreach ($login_activity as $log): ?>
                <div class="activity-item">
                    <div class="activity-dot <?= $log['status'] ?>"></div>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--gray-900)"><?= htmlspecialchars($log['full_name']) ?></div>
                        <div style="font-size:11px;color:var(--gray-300)"><?= roleBadge($log['role']) ?></div>
                    </div>
                    <div class="activity-meta"><?= date('d M, h:i A', strtotime($log['login_time'])) ?></div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php else: ?><div class="empty"><div class="icon">🕐</div><p>Tiada aktiviti.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- Course registration summary -->
        <div class="card">
            <div class="card-header"><div><h3>📋 Ringkasan Pendaftaran Kursus</h3><p>Status permohonan mengikut kursus</p></div></div>
            <div class="table-wrap">
                <table><thead><tr><th>Kod Kursus</th><th>Nama Kursus</th><th>Taraf</th><th>Jumlah Mohon</th><th>Pending</th><th>Diluluskan</th><th>Ditolak</th></tr></thead><tbody>
                <?php foreach ($reg_summary as $r): ?>
                <tr>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($r['course_code']) ?></span></td>
                    <td><?= htmlspecialchars($r['course_name']) ?></td>
                    <td><?php
                        $el = $r['education_level'] ?? 'both';
                        echo match($el) { 'asasi'=>'<span class="badge badge-yellow">Asasi</span>', 'diploma'=>'<span class="badge badge-teal">Diploma</span>', default=>'<span class="badge badge-gray">Kedua-dua</span>' };
                    ?></td>
                    <td style="text-align:center;font-weight:700"><?= $r['total_reg'] ?></td>
                    <td style="text-align:center"><?= $r['pending_count'] > 0 ? '<span class="badge badge-yellow">'.$r['pending_count'].'</span>' : '—' ?></td>
                    <td style="text-align:center"><?= $r['approved_count'] > 0 ? '<span class="badge badge-green">'.$r['approved_count'].'</span>' : '—' ?></td>
                    <td style="text-align:center"><?= $r['rejected_count'] > 0 ? '<span class="badge badge-red">'.$r['rejected_count'].'</span>' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>

        <!-- ══ USERS ══ -->
        <?php elseif ($tab === 'users'): ?>

        <?php
        $filter = $_GET['filter'] ?? 'all';
        ?>

        <?php if ($total_pending > 0 && $filter !== 'pending'): ?>
        <div class="pending-banner" style="margin-bottom:18px">
            <div class="info">
                <span class="icon">⚠️</span>
                <div>
                    <h4><?= $total_pending ?> akaun menunggu kelulusan</h4>
                    <p>Perlu diluluskan sebelum pengguna boleh log masuk.</p>
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <form method="POST"><input type="hidden" name="action" value="approve_all_pending"><button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Lulus semua pending?')"><i class="fas fa-check-double"></i> Lulus Semua</button></form>
                <a href="?tab=users&filter=pending" class="btn btn-warning btn-sm"><i class="fas fa-eye"></i> Lihat</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Role filter tabs -->
        <div class="role-tabs">
            <button class="role-tab <?= $filter==='all'?'active-all':'' ?>" onclick="filterUsers('all',this)">Semua (<?= $total_users ?>)</button>
            <button class="role-tab <?= $filter==='student'?'active-student':'' ?>" onclick="filterUsers('student',this)">🎓 Pelajar (<?= $total_students ?>)</button>
            <button class="role-tab <?= $filter==='staff'?'active-staff':'' ?>" onclick="filterUsers('staff',this)">💼 Staf (<?= $total_staff ?>)</button>
            <button class="role-tab <?= $filter==='lecturer'?'active-lecturer':'' ?>" onclick="filterUsers('lecturer',this)">📚 Pensyarah (<?= $total_lecturers ?>)</button>
            <button class="role-tab <?= $filter==='admin'?'active-admin':'' ?>" onclick="filterUsers('admin',this)">🔑 Admin (<?= count($admins) ?>)</button>
            <button class="role-tab <?= $filter==='pending'?'active-all':'' ?>" onclick="filterUsers('pending',this)" style="<?= $total_pending>0?'border-color:#fde68a;background:var(--yellow-50);color:var(--yellow-700)':'' ?>">⏳ Pending (<?= $total_pending ?>)</button>
        </div>

        <!-- Search -->
        <div class="filter-bar">
            <div class="search-wrap" style="flex:1;max-width:360px">
                <i class="fas fa-search"></i>
                <input type="text" id="userSearch" placeholder="Cari nama, username, e-mel..." oninput="searchUsers()">
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h3>👥 Senarai Pengguna</h3><p>Klik baris untuk lihat maklumat lanjut · Tukar role/status terus dalam jadual</p></div>
                <span class="badge badge-gray" id="userCount"><?= $total_users ?> pengguna</span>
            </div>
            <div class="table-wrap">
                <table id="userTable">
                    <thead><tr><th></th><th>#</th><th>Nama Penuh</th><th>Username</th><th>E-mel</th><th>Role</th><th>Status</th><th>Tarikh Daftar</th><th>Tindakan</th></tr></thead>
                    <tbody>
                    <?php foreach ($all_users as $i => $u):
                        $is_self = $u['user_id'] === $admin_id;
                    ?>
                    <tr class="user-row"
                        data-role="<?= $u['role'] ?>"
                        data-status="<?= $u['status'] ?>"
                        data-search="<?= strtolower($u['full_name'].' '.$u['username'].' '.$u['email']) ?>"
                        onclick="toggleDetail(<?= $u['user_id'] ?>)">
                        <td><i class="fas fa-chevron-right" id="arrow_<?= $u['user_id'] ?>" style="color:var(--gray-300);font-size:11px;transition:transform .2s"></i></td>
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($u['full_name']) ?></strong><?= $is_self ? ' <span class="badge badge-orange" style="font-size:10px">Anda</span>' : '' ?></td>
                        <td style="font-family:monospace;font-size:12px">@<?= htmlspecialchars($u['username']) ?></td>
                        <td style="font-size:12px"><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= roleBadge($u['role']) ?></td>
                        <td><?= statusBadge($u['status']) ?></td>
                        <td style="font-size:11px;color:var(--gray-300)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td onclick="event.stopPropagation()">
                            <div style="display:flex;gap:5px;flex-wrap:wrap">
                                <?php if ($u['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="approve_user">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Lulus</button>
                                </form>
                                <?php endif; ?>
                                <?php if (!$is_self): ?>
                                <button class="btn btn-warning btn-sm" onclick="openEditUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)"><i class="fas fa-pen"></i></button>
                                <form method="POST" onsubmit="return confirm('Padam pengguna <?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <!-- Detail expand row -->
                    <tr class="detail-row" id="detail_<?= $u['user_id'] ?>">
                        <td colspan="9">
                            <div class="detail-box">
                                <div class="detail-field"><label>No. Telefon</label><div class="val <?= !$u['phone']?'empty':'' ?>"><?= $u['phone'] ?: 'Tiada' ?></div></div>
                                <div class="detail-field"><label>Tarikh Lahir</label><div class="val <?= !$u['date_of_birth']?'empty':'' ?>"><?= $u['date_of_birth'] ? date('d M Y', strtotime($u['date_of_birth'])) : 'Tiada' ?></div></div>
                                <div class="detail-field"><label>Taraf Pendidikan</label><div class="val <?= !$u['education_level']?'empty':'' ?>"><?= $u['education_level'] ? ucfirst($u['education_level']) : 'Tiada' ?></div></div>
                                <div class="detail-field"><label>Semester</label><div class="val <?= !$u['current_semester']?'empty':'' ?>"><?= $u['current_semester'] ? 'Semester '.$u['current_semester'] : 'Tiada' ?></div></div>
                                <div class="detail-field"><label>Fakulti</label><div class="val <?= !$u['faculty']?'empty':'' ?>"><?= $u['faculty'] ?: 'Tiada' ?></div></div>
                                <div class="detail-field"><label>Program / Jabatan</label><div class="val <?= !($u['program']||$u['department'])?'empty':'' ?>"><?= $u['program'] ?: ($u['department'] ?: 'Tiada') ?></div></div>
                                <div class="detail-field"><label>No. Pelajar / Staf</label><div class="val <?= !($u['student_no']||$u['staff_no'])?'empty':'' ?>"><?= $u['student_no'] ?: ($u['staff_no'] ?: 'Tiada') ?></div></div>
                                <div class="detail-field"><label>Kemaskini Terakhir</label><div class="val"><?= date('d M Y, h:i A', strtotime($u['updated_at'])) ?></div></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div class="modal-overlay" id="editUserModal">
            <div class="modal-box" style="max-width:480px">
                <div class="modal-header">
                    <h3><i class="fas fa-pen"></i> Edit Pengguna</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editUserModal')"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="modal-body">
                    <div id="editUserName" style="padding:12px 16px;background:var(--gray-50);border-radius:10px;margin-bottom:18px;font-weight:600;color:var(--gray-900);font-size:14px"></div>

                    <!-- Change Role -->
                    <div style="margin-bottom:20px">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--gray-500);margin-bottom:10px">Tukar Role</div>
                        <form method="POST" id="roleForm">
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="user_id" id="edit_user_id_role">
                            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:12px" id="roleCards">
                                <?php foreach (['student'=>['🎓','Pelajar','badge-blue'],'staff'=>['💼','Staf','badge-yellow'],'lecturer'=>['📚','Pensyarah','badge-teal'],'admin'=>['🔑','Admin','badge-red']] as $r => $rd): ?>
                                <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:1.5px solid var(--gray-200);border-radius:9px;cursor:pointer;transition:all .15s" id="roleCard_<?= $r ?>">
                                    <input type="radio" name="new_role" value="<?= $r ?>" id="roleRadio_<?= $r ?>" style="accent-color:var(--blue-bright)">
                                    <span style="font-size:16px"><?= $rd[0] ?></span>
                                    <span style="font-size:13px;font-weight:600;color:var(--gray-700)"><?= $rd[1] ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-arrows-rotate"></i> Tukar Role</button>
                        </form>
                    </div>

                    <div style="border-top:1px solid var(--gray-100);padding-top:18px">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--gray-500);margin-bottom:10px">Tukar Status</div>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="user_id" id="edit_user_id_status">
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">
                                <?php foreach (['active'=>['✅','Aktif'],'pending'=>['⏳','Pending'],'inactive'=>['❌','Tidak Aktif']] as $s => $sd): ?>
                                <label style="display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 8px;border:1.5px solid var(--gray-200);border-radius:9px;cursor:pointer;font-size:12px;font-weight:600;color:var(--gray-700);text-align:center" id="statusCard_<?= $s ?>">
                                    <input type="radio" name="new_status" value="<?= $s ?>" id="statusRadio_<?= $s ?>" style="accent-color:var(--blue-bright)">
                                    <span style="font-size:18px"><?= $sd[0] ?></span>
                                    <?= $sd[1] ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-warning" style="width:100%;justify-content:center"><i class="fas fa-circle-half-stroke"></i> Tukar Status</button>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-gray" onclick="closeModal('editUserModal')">Tutup</button>
                </div>
            </div>
        </div>

        <!-- ══ STAFF LIST ══ -->
        <?php elseif ($tab === 'staff'): ?>
        <div class="card">
            <div class="card-header">
                <div><h3>💼 Senarai Staf</h3><p>Maklumat staf dan tugasan mereka</p></div>
                <span class="badge badge-yellow"><?= count($staff_detail) ?> staf</span>
            </div>
            <?php if ($staff_detail): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Nama Staf</th><th>Username</th><th>E-mel</th><th>No. Telefon</th><th>Jabatan</th><th>No. Staf</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($staff_detail as $i => $s): ?>
                    <tr>
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($s['full_name']) ?></strong>
                            <?php if ($s['date_of_birth']): ?>
                            <br><small style="color:var(--gray-300)">DOB: <?= date('d M Y', strtotime($s['date_of_birth'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="font-family:monospace;font-size:12px">@<?= htmlspecialchars($s['username']) ?></td>
                        <td style="font-size:12px"><?= htmlspecialchars($s['email']) ?></td>
                        <td><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                        <td>
                            <?php if ($s['department']): ?>
                            <span class="badge badge-yellow"><?= htmlspecialchars($s['department']) ?></span>
                            <?php else: ?>
                            <span style="color:var(--gray-300)">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $s['staff_no'] ? '<span class="badge badge-gray">'.htmlspecialchars($s['staff_no']).'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td><?= statusBadge($s['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty"><div class="icon">💼</div><p>Tiada staf berdaftar.</p></div><?php endif; ?>
        </div>

        <!-- ══ LECTURERS ══ -->
        <?php elseif ($tab === 'lecturers'): ?>
        <div class="card">
            <div class="card-header">
                <div><h3>📚 Senarai Pensyarah</h3><p>Maklumat pensyarah dan subjek yang diajar</p></div>
                <span class="badge badge-teal"><?= count($lect_detail) ?> pensyarah</span>
            </div>
            <?php if ($lect_detail): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Nama Pensyarah</th><th>E-mel</th><th>Fakulti</th><th>Jabatan / Kepakaran</th><th>Kelas</th><th>Subjek</th><th>Kod Subjek</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($lect_detail as $i => $l): ?>
                    <tr>
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($l['full_name']) ?></strong>
                            <?php if ($l['staff_no']): ?><br><span class="badge badge-gray" style="margin-top:3px"><?= htmlspecialchars($l['staff_no']) ?></span><?php endif; ?>
                        </td>
                        <td style="font-size:12px"><?= htmlspecialchars($l['email']) ?></td>
                        <td style="font-size:12px"><?= htmlspecialchars($l['faculty'] ?? '—') ?></td>
                        <td><?= $l['department'] ? '<span class="badge badge-teal">'.htmlspecialchars($l['department']).'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td style="text-align:center"><span class="badge badge-blue"><?= $l['classes_count'] ?> kelas</span></td>
                        <td style="text-align:center"><span class="badge badge-purple"><?= $l['subjects_count'] ?> subjek</span></td>
                        <td style="font-size:11px;max-width:180px"><?= $l['subject_codes'] ? htmlspecialchars($l['subject_codes']) : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td><?= statusBadge($l['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty"><div class="icon">📚</div><p>Tiada pensyarah berdaftar.</p></div><?php endif; ?>
        </div>

        <!-- ══ REPORTS ══ -->
        <?php elseif ($tab === 'reports'): ?>

        <div class="report-grid" style="margin-bottom:22px">
            <div class="report-card" onclick="printReport('users')">
                <div class="rc-icon">👥</div>
                <h4>Laporan Senarai Pengguna</h4>
                <p>Senarai lengkap semua pengguna beserta role, status, dan maklumat peribadi.</p>
                <button class="btn btn-print"><i class="fas fa-print"></i> Cetak / Jana PDF</button>
            </div>
            <div class="report-card" onclick="printReport('students')">
                <div class="rc-icon">🎓</div>
                <h4>Laporan Senarai Pelajar</h4>
                <p>Maklumat pelajar termasuk taraf pendidikan, semester, fakulti dan program pengajian.</p>
                <button class="btn btn-print"><i class="fas fa-print"></i> Cetak / Jana PDF</button>
            </div>
            <div class="report-card" onclick="printReport('staff')">
                <div class="rc-icon">💼</div>
                <h4>Laporan Senarai Staf</h4>
                <p>Senarai staf beserta jabatan dan maklumat hubungan.</p>
                <button class="btn btn-print"><i class="fas fa-print"></i> Cetak / Jana PDF</button>
            </div>
            <div class="report-card" onclick="printReport('lecturers')">
                <div class="rc-icon">📚</div>
                <h4>Laporan Senarai Pensyarah</h4>
                <p>Senarai pensyarah, kelas yang diajar dan subjek yang dikendalikan.</p>
                <button class="btn btn-print"><i class="fas fa-print"></i> Cetak / Jana PDF</button>
            </div>
            <div class="report-card" onclick="printReport('registrations')">
                <div class="rc-icon">📋</div>
                <h4>Laporan Pendaftaran Kursus</h4>
                <p>Status permohonan kursus oleh pelajar — pending, diluluskan dan ditolak.</p>
                <button class="btn btn-print"><i class="fas fa-print"></i> Cetak / Jana PDF</button>
            </div>
            <div class="report-card" onclick="printReport('pending')">
                <div class="rc-icon">⏳</div>
                <h4>Laporan Akaun Pending</h4>
                <p>Senarai akaun yang masih menunggu kelulusan admin.</p>
                <button class="btn btn-print"><i class="fas fa-print"></i> Cetak / Jana PDF</button>
            </div>
        </div>

        <!-- Print areas (hidden, shown only on print) -->
        <?php
        $print_date = date('d M Y, h:i A');
        $uptm_header = '<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #1a4db8">
            <h2 style="color:#0f2d6e;font-size:20px;margin-bottom:4px">Universiti Poly-Tech Malaysia (UPTM)</h2>
            <h3 style="color:#1a4db8;font-size:15px;font-weight:600">Sistem Pendaftaran Pelajar</h3>
            <p style="color:#64748b;font-size:12px;margin-top:6px">Dicetak pada: '.$print_date.'</p></div>';
        $tbl_style = 'style="width:100%;border-collapse:collapse;font-size:12px"';
        $th_style  = 'style="padding:8px 10px;background:#1a4db8;color:white;text-align:left;font-weight:600"';
        $td_style  = 'style="padding:7px 10px;border-bottom:1px solid #e2e8f0"';
        ?>

        <!-- Report: All Users -->
        <div class="print-area" id="print_users">
            <?= $uptm_header ?>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:14px">Laporan Senarai Pengguna</h3>
            <table <?= $tbl_style ?>><thead><tr>
                <th <?= $th_style ?>>#</th><th <?= $th_style ?>>Nama Penuh</th><th <?= $th_style ?>>Username</th><th <?= $th_style ?>>E-mel</th><th <?= $th_style ?>>Role</th><th <?= $th_style ?>>Status</th><th <?= $th_style ?>>Tarikh Daftar</th>
            </tr></thead><tbody>
            <?php foreach ($all_users as $i => $u): ?>
            <tr><td <?= $td_style ?>><?= $i+1 ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['full_name']) ?></td><td <?= $td_style ?>>@<?= htmlspecialchars($u['username']) ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['email']) ?></td><td <?= $td_style ?>><?= ucfirst($u['role']) ?></td><td <?= $td_style ?>><?= ucfirst($u['status']) ?></td><td <?= $td_style ?>><?= date('d M Y', strtotime($u['created_at'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <p style="margin-top:16px;font-size:11px;color:#64748b">Jumlah: <?= $total_users ?> pengguna</p>
        </div>

        <!-- Report: Students -->
        <div class="print-area" id="print_students">
            <?= $uptm_header ?>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:14px">Laporan Senarai Pelajar</h3>
            <table <?= $tbl_style ?>><thead><tr>
                <th <?= $th_style ?>>#</th><th <?= $th_style ?>>Nama</th><th <?= $th_style ?>>No. Pelajar</th><th <?= $th_style ?>>E-mel</th><th <?= $th_style ?>>Taraf</th><th <?= $th_style ?>>Semester</th><th <?= $th_style ?>>Fakulti</th><th <?= $th_style ?>>Program</th><th <?= $th_style ?>>Status</th>
            </tr></thead><tbody>
            <?php foreach ($students as $i => $u): ?>
            <tr><td <?= $td_style ?>><?= $i+1 ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['full_name']) ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['student_no'] ?? '—') ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['email']) ?></td><td <?= $td_style ?>><?= ucfirst($u['education_level'] ?? '—') ?></td><td <?= $td_style ?>>Sem <?= $u['current_semester'] ?? '—' ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['faculty'] ?? '—') ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['program'] ?? '—') ?></td><td <?= $td_style ?>><?= ucfirst($u['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <p style="margin-top:16px;font-size:11px;color:#64748b">Jumlah: <?= $total_students ?> pelajar</p>
        </div>

        <!-- Report: Staff -->
        <div class="print-area" id="print_staff">
            <?= $uptm_header ?>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:14px">Laporan Senarai Staf</h3>
            <table <?= $tbl_style ?>><thead><tr>
                <th <?= $th_style ?>>#</th><th <?= $th_style ?>>Nama</th><th <?= $th_style ?>>No. Staf</th><th <?= $th_style ?>>E-mel</th><th <?= $th_style ?>>No. Telefon</th><th <?= $th_style ?>>Jabatan</th><th <?= $th_style ?>>Status</th>
            </tr></thead><tbody>
            <?php foreach ($staff_detail as $i => $u): ?>
            <tr><td <?= $td_style ?>><?= $i+1 ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['full_name']) ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['staff_no'] ?? '—') ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['email']) ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['phone'] ?? '—') ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['department'] ?? '—') ?></td><td <?= $td_style ?>><?= ucfirst($u['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <p style="margin-top:16px;font-size:11px;color:#64748b">Jumlah: <?= $total_staff ?> staf</p>
        </div>

        <!-- Report: Lecturers -->
        <div class="print-area" id="print_lecturers">
            <?= $uptm_header ?>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:14px">Laporan Senarai Pensyarah</h3>
            <table <?= $tbl_style ?>><thead><tr>
                <th <?= $th_style ?>>#</th><th <?= $th_style ?>>Nama</th><th <?= $th_style ?>>E-mel</th><th <?= $th_style ?>>Fakulti</th><th <?= $th_style ?>>Jabatan</th><th <?= $th_style ?>>Kelas</th><th <?= $th_style ?>>Subjek</th><th <?= $th_style ?>>Status</th>
            </tr></thead><tbody>
            <?php foreach ($lect_detail as $i => $u): ?>
            <tr><td <?= $td_style ?>><?= $i+1 ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['full_name']) ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['email']) ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['faculty'] ?? '—') ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['department'] ?? '—') ?></td><td <?= $td_style ?>><?= $u['classes_count'] ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['subject_codes'] ?? '—') ?></td><td <?= $td_style ?>><?= ucfirst($u['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>

        <!-- Report: Registrations -->
        <div class="print-area" id="print_registrations">
            <?= $uptm_header ?>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:14px">Laporan Pendaftaran Kursus</h3>
            <table <?= $tbl_style ?>><thead><tr>
                <th <?= $th_style ?>>#</th><th <?= $th_style ?>>Kod Kursus</th><th <?= $th_style ?>>Nama Kursus</th><th <?= $th_style ?>>Taraf</th><th <?= $th_style ?>>Jumlah Mohon</th><th <?= $th_style ?>>Pending</th><th <?= $th_style ?>>Diluluskan</th><th <?= $th_style ?>>Ditolak</th>
            </tr></thead><tbody>
            <?php foreach ($reg_summary as $i => $r): ?>
            <tr><td <?= $td_style ?>><?= $i+1 ?></td><td <?= $td_style ?>><?= htmlspecialchars($r['course_code']) ?></td><td <?= $td_style ?>><?= htmlspecialchars($r['course_name']) ?></td><td <?= $td_style ?>><?= ucfirst($r['education_level'] ?? '—') ?></td><td <?= $td_style ?>><?= $r['total_reg'] ?></td><td <?= $td_style ?>><?= $r['pending_count'] ?></td><td <?= $td_style ?>><?= $r['approved_count'] ?></td><td <?= $td_style ?>><?= $r['rejected_count'] ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>

        <!-- Report: Pending -->
        <div class="print-area" id="print_pending">
            <?= $uptm_header ?>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:14px">Laporan Akaun Pending</h3>
            <table <?= $tbl_style ?>><thead><tr>
                <th <?= $th_style ?>>#</th><th <?= $th_style ?>>Nama Penuh</th><th <?= $th_style ?>>Username</th><th <?= $th_style ?>>E-mel</th><th <?= $th_style ?>>Role</th><th <?= $th_style ?>>Tarikh Daftar</th>
            </tr></thead><tbody>
            <?php foreach ($pending as $i => $u): ?>
            <tr><td <?= $td_style ?>><?= $i+1 ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['full_name']) ?></td><td <?= $td_style ?>>@<?= htmlspecialchars($u['username']) ?></td><td <?= $td_style ?>><?= htmlspecialchars($u['email']) ?></td><td <?= $td_style ?>><?= ucfirst($u['role']) ?></td><td <?= $td_style ?>><?= date('d M Y', strtotime($u['created_at'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <p style="margin-top:16px;font-size:11px;color:#64748b">Jumlah: <?= $total_pending ?> akaun pending</p>
        </div>

        <?php endif; ?>
    </div>
</div>

<!-- Modal styles -->
<style>
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(2px); }
.modal-overlay.show { display:flex; }
.modal-box { background:var(--white); border-radius:16px; width:100%; max-width:640px; max-height:88vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3); animation:modalPop .18s ease; }
@keyframes modalPop { from{opacity:0;transform:translateY(10px) scale(.98)} to{opacity:1;transform:translateY(0) scale(1)} }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--gray-100); position:sticky; top:0; background:var(--white); z-index:2; }
.modal-header h3 { font-size:15px; font-weight:700; color:var(--gray-900); display:flex; align-items:center; gap:8px; }
.modal-close { background:var(--gray-100); border:none; width:30px; height:30px; border-radius:8px; color:var(--gray-500); cursor:pointer; display:flex; align-items:center; justify-content:center; }
.modal-close:hover { background:var(--gray-200); }
.modal-body { padding:22px; }
.modal-footer { display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid var(--gray-100); }
</style>

<script>
// ── User Table Filters ─────────────────────────────
function filterUsers(role, el) {
    document.querySelectorAll('.role-tab').forEach(t => t.className = 'role-tab');
    el.classList.add('active-' + (role === 'all' ? 'all' : role === 'pending' ? 'all' : role));

    document.querySelectorAll('.user-row').forEach(row => {
        const matchRole   = role === 'all'     ? true
                          : role === 'pending' ? row.dataset.status === 'pending'
                          : row.dataset.role   === role;
        const nextRow = row.nextElementSibling; // detail row
        row.style.display = matchRole ? '' : 'none';
        if (nextRow && nextRow.classList.contains('detail-row')) {
            if (!matchRole) nextRow.classList.remove('show');
        }
    });
    updateCount();
}

function searchUsers() {
    const q = document.getElementById('userSearch').value.toLowerCase();
    document.querySelectorAll('.user-row').forEach(row => {
        const match = row.dataset.search.includes(q);
        const nextRow = row.nextElementSibling;
        if (row.style.display !== 'none') {
            row.style.display = match ? '' : 'none';
            if (nextRow && nextRow.classList.contains('detail-row') && !match) {
                nextRow.classList.remove('show');
            }
        }
    });
    updateCount();
}

function updateCount() {
    const visible = [...document.querySelectorAll('.user-row')].filter(r => r.style.display !== 'none').length;
    const el = document.getElementById('userCount');
    if (el) el.textContent = visible + ' pengguna';
}

// ── Toggle detail row ──────────────────────────────
function toggleDetail(uid) {
    const detail = document.getElementById('detail_' + uid);
    const arrow  = document.getElementById('arrow_' + uid);
    if (!detail) return;
    const isOpen = detail.classList.contains('show');
    // Close all
    document.querySelectorAll('.detail-row').forEach(r => r.classList.remove('show'));
    document.querySelectorAll('[id^="arrow_"]').forEach(a => a.style.transform = '');
    // Toggle current
    if (!isOpen) {
        detail.classList.add('show');
        if (arrow) arrow.style.transform = 'rotate(90deg)';
    }
}

// ── Edit User Modal ────────────────────────────────
function openEditUser(u) {
    document.getElementById('editUserName').textContent = u.full_name + ' (@' + u.username + ')';
    document.getElementById('edit_user_id_role').value   = u.user_id;
    document.getElementById('edit_user_id_status').value = u.user_id;
    // Set current role radio
    document.querySelectorAll('[name="new_role"]').forEach(r => {
        r.checked = (r.value === u.role);
        const card = document.getElementById('roleCard_' + r.value);
        if (card) card.style.borderColor = r.checked ? 'var(--blue-bright)' : 'var(--gray-200)';
        if (card) card.style.background  = r.checked ? 'var(--blue-pale)'   : 'var(--white)';
    });
    // Set current status radio
    document.querySelectorAll('[name="new_status"]').forEach(r => {
        r.checked = (r.value === u.status);
        const card = document.getElementById('statusCard_' + r.value);
        if (card) card.style.borderColor = r.checked ? 'var(--blue-bright)' : 'var(--gray-200)';
        if (card) card.style.background  = r.checked ? 'var(--blue-pale)'   : 'var(--white)';
    });
    document.getElementById('editUserModal').classList.add('show');
}

// Highlight role/status card on change
document.querySelectorAll('[name="new_role"]').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('[name="new_role"]').forEach(x => {
            const c = document.getElementById('roleCard_' + x.value);
            if (c) { c.style.borderColor = x.checked ? 'var(--blue-bright)' : 'var(--gray-200)'; c.style.background = x.checked ? 'var(--blue-pale)' : 'var(--white)'; }
        });
    });
});
document.querySelectorAll('[name="new_status"]').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('[name="new_status"]').forEach(x => {
            const c = document.getElementById('statusCard_' + x.value);
            if (c) { c.style.borderColor = x.checked ? 'var(--blue-bright)' : 'var(--gray-200)'; c.style.background = x.checked ? 'var(--blue-pale)' : 'var(--white)'; }
        });
    });
});

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.getElementById('editUserModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal('editUserModal'); });

// ── Print Reports ──────────────────────────────────
function printReport(type) {
    // Hide all print areas, show target
    document.querySelectorAll('.print-area').forEach(el => el.style.display = 'none');
    const target = document.getElementById('print_' + type);
    if (target) {
        target.style.display = 'block';
        setTimeout(() => { window.print(); target.style.display = 'none'; }, 200);
    }
}

// Apply URL filter on load
<?php if (isset($_GET['filter']) && $_GET['filter']): ?>
window.addEventListener('DOMContentLoaded', () => {
    const btn = document.querySelector('.role-tab:nth-child(<?=
        match($_GET['filter']) {
            'student'=>'2','staff'=>'3','lecturer'=>'4','admin'=>'5','pending'=>'6',default=>'1'
        }
    ?>)');
    if (btn) btn.click();
});
<?php endif; ?>
</script>
</body>
</html>
