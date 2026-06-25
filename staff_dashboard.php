<?php
// =====================================================
// staff_dashboard.php — Staff Dashboard
// Student Registration System — UPTM
// =====================================================
session_start();
require_once 'db_connect.php';
redirectIfNotLoggedIn();

if ($_SESSION['role'] !== 'staff') {
    header("Location: login_page.php");
    exit();
}

$staff_id = $_SESSION['user_id'];

// ── Fetch staff profile ───────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();

$msg = $msg_type = '';
$tab = $_GET['tab'] ?? 'overview';

// ══════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════

// ── Add Course ───────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add_course') {
    $code    = trim($_POST['course_code']);
    $name    = trim($_POST['course_name']);
    $edu_lvl = $_POST['education_level'] ?? 'both';
    $fac     = trim($_POST['faculty']);
    $max     = intval($_POST['max_students']);
    $desc    = trim($_POST['description']);

    $allowed_edu = ['asasi','diploma','both'];
    if (empty($code) || empty($name)) {
        $msg = 'Kod dan nama kursus wajib diisi.'; $msg_type = 'error';
    } elseif (!in_array($edu_lvl, $allowed_edu)) {
        $msg = 'Taraf pendidikan tidak sah.'; $msg_type = 'error';
    } else {
        $ins = $conn->prepare("INSERT INTO courses (course_code,course_name,education_level,faculty,max_students,description,status) VALUES (?,?,?,?,?,?,'open')");
        $ins->bind_param("ssssis", $code,$name,$edu_lvl,$fac,$max,$desc);
        if ($ins->execute()) { $msg = "Kursus '$name' berjaya ditambah!"; $msg_type = 'success'; }
        else { $msg = 'Ralat: Kod kursus mungkin sudah wujud. ('.$conn->error.')'; $msg_type = 'error'; }
    }
    $tab = 'courses';
}

// ── Edit Course ──────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'edit_course') {
    $course_id = intval($_POST['course_id']);
    $code      = trim($_POST['course_code']);
    $name      = trim($_POST['course_name']);
    $edu_lvl   = $_POST['education_level'] ?? 'both';
    $fac       = trim($_POST['faculty']);
    $max       = intval($_POST['max_students']);
    $desc      = trim($_POST['description']);
    $status    = $_POST['status'] ?? 'open';

    if (empty($code) || empty($name) || !$course_id) {
        $msg = 'Maklumat kursus tidak lengkap.'; $msg_type = 'error';
    } else {
        $upd = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, education_level=?, faculty=?, max_students=?, description=?, status=? WHERE course_id=?");
        $upd->bind_param("ssssissi", $code,$name,$edu_lvl,$fac,$max,$desc,$status,$course_id);
        if ($upd->execute()) { $msg = "Kursus '$name' berjaya dikemaskini!"; $msg_type = 'success'; }
        else { $msg = 'Ralat: Kod kursus mungkin telah digunakan oleh kursus lain.'; $msg_type = 'error'; }
    }
    $tab = 'courses';
}

// ── Add Subject (boleh untuk pelbagai kursus sekaligus) ──
if (isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    $code        = trim($_POST['subject_code']);
    $name        = trim($_POST['subject_name']);
    $course_ids  = $_POST['course_ids'] ?? [];   // array of course_id
    $sem_no      = intval($_POST['semester_no']);
    $cred        = intval($_POST['credit_hours']);
    $desc        = trim($_POST['description']);

    if (empty($code) || empty($name) || empty($course_ids) || !$sem_no) {
        $msg = 'Sila isi semua maklumat subjek termasuk semester dan sekurang-kurangnya satu kursus.'; $msg_type = 'error';
    } else {
        $success_count = 0;
        $fail_count = 0;
        foreach ($course_ids as $cid) {
            $cid = intval($cid);
            // Generate unique code per course if more than one course selected
            $final_code = (count($course_ids) > 1) ? $code.'-C'.$cid : $code;
            $ins = $conn->prepare("INSERT INTO subjects (subject_code,subject_name,course_id,semester_no,credit_hours,description,created_by) VALUES (?,?,?,?,?,?,?)");
            $ins->bind_param("ssiiisi", $final_code,$name,$cid,$sem_no,$cred,$desc,$staff_id);
            if ($ins->execute()) { $success_count++; } else { $fail_count++; }
        }
        if ($success_count > 0) {
            $msg = "Subjek '$name' berjaya ditambah untuk $success_count kursus!" . ($fail_count > 0 ? " ($fail_count gagal — kod mungkin pertindihan)" : '');
            $msg_type = 'success';
        } else {
            $msg = 'Ralat: Kod subjek mungkin sudah wujud.'; $msg_type = 'error';
        }
    }
    $tab = 'subjects';
}

// ── Edit Subject ─────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'edit_subject') {
    $subject_id = intval($_POST['subject_id']);
    $code       = trim($_POST['subject_code']);
    $name       = trim($_POST['subject_name']);
    $course_id  = intval($_POST['course_id']);
    $sem_no     = intval($_POST['semester_no']);
    $cred       = intval($_POST['credit_hours']);
    $desc       = trim($_POST['description']);
    $status     = $_POST['status'] ?? 'active';

    if (empty($code) || empty($name) || !$course_id || !$sem_no) {
        $msg = 'Maklumat subjek tidak lengkap.'; $msg_type = 'error';
    } else {
        $upd = $conn->prepare("UPDATE subjects SET subject_code=?, subject_name=?, course_id=?, semester_no=?, credit_hours=?, description=?, status=? WHERE subject_id=?");
        $upd->bind_param("ssiiissi", $code,$name,$course_id,$sem_no,$cred,$desc,$status,$subject_id);
        if ($upd->execute()) { $msg = "Subjek '$name' berjaya dikemaskini!"; $msg_type = 'success'; }
        else { $msg = 'Ralat: Kod subjek mungkin telah digunakan.'; $msg_type = 'error'; }
    }
    $tab = 'subjects';
}

// ── Add Section/Class ────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add_class') {
    $course_id    = intval($_POST['course_id']);
    $edu_lvl      = $_POST['education_level'] ?? '';
    $sem_no       = intval($_POST['semester_no']);
    $max          = intval($_POST['max_students']) ?: 40;
    $section_num  = intval($_POST['section_num']); // 1, 2, 3 ...

    // Fetch course code to auto-generate class_code
    $course_row = null;
    if ($course_id) {
        $cr = $conn->prepare("SELECT course_code, course_name FROM courses WHERE course_id=?");
        $cr->bind_param("i", $course_id);
        $cr->execute();
        $course_row = $cr->get_result()->fetch_assoc();
    }

    if (!$course_id || !$sem_no || !$section_num || !$edu_lvl) {
        $msg = 'Sila lengkapkan semua maklumat section.'; $msg_type = 'error';
    } elseif (!$course_row) {
        $msg = 'Kursus tidak dijumpai.'; $msg_type = 'error';
    } else {
        $class_code = $course_row['course_code'] . '_' . str_pad($section_num, 2, '0', STR_PAD_LEFT);
        $class_name = $course_row['course_name'] . ' — Section ' . str_pad($section_num, 2, '0', STR_PAD_LEFT);
        $semester   = '2024/2025-' . $sem_no;

        $ins = $conn->prepare("INSERT INTO classes (class_code,class_name,course_id,semester,education_level,semester_no,section_num,max_students,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->bind_param("ssissiiii", $class_code, $class_name, $course_id, $semester, $edu_lvl, $sem_no, $section_num, $max, $staff_id);

        if ($ins->execute()) {
            $msg = "Section '$class_code' berjaya ditambah!"; $msg_type = 'success';
        } else {
            $msg = 'Ralat: Kod section ini mungkin sudah wujud untuk kursus ini.'; $msg_type = 'error';
        }
    }
    $tab = 'classes';
}

// ── Edit Section/Class ───────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'edit_class') {
    $class_id    = intval($_POST['class_id']);
    $course_id   = intval($_POST['course_id']);
    $edu_lvl     = $_POST['education_level'] ?? '';
    $sem_no      = intval($_POST['semester_no']);
    $section_num = intval($_POST['section_num']);
    $max         = intval($_POST['max_students']) ?: 40;
    $status      = $_POST['status'] ?? 'active';

    $course_row = null;
    if ($course_id) {
        $cr = $conn->prepare("SELECT course_code, course_name FROM courses WHERE course_id=?");
        $cr->bind_param("i", $course_id);
        $cr->execute();
        $course_row = $cr->get_result()->fetch_assoc();
    }

    if (!$course_id || !$sem_no || !$section_num) {
        $msg = 'Maklumat section tidak lengkap.'; $msg_type = 'error';
    } else {
        $class_code = $course_row['course_code'] . '_' . str_pad($section_num, 2, '0', STR_PAD_LEFT);
        $class_name = $course_row['course_name'] . ' — Section ' . str_pad($section_num, 2, '0', STR_PAD_LEFT);
        $semester   = '2024/2025-' . $sem_no;

        $upd = $conn->prepare("UPDATE classes SET class_code=?, class_name=?, course_id=?, semester=?, education_level=?, semester_no=?, section_num=?, max_students=?, status=? WHERE class_id=?");
        $upd->bind_param("ssissiiisi", $class_code, $class_name, $course_id, $semester, $edu_lvl, $sem_no, $section_num, $max, $status, $class_id);
        if ($upd->execute()) { $msg = "Section '$class_code' berjaya dikemaskini!"; $msg_type = 'success'; }
        else { $msg = 'Ralat: Kod section mungkin telah digunakan.'; $msg_type = 'error'; }
    }
    $tab = 'classes';
}

// ── Delete Course ─────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_course') {
    $course_id = intval($_POST['course_id']);
    $del = $conn->prepare("DELETE FROM courses WHERE course_id=?");
    $del->bind_param("i", $course_id);
    if ($del->execute() && $del->affected_rows > 0) { $msg = 'Kursus berjaya dipadam.'; $msg_type = 'info'; }
    else { $msg = 'Ralat: Kursus mungkin masih mempunyai kelas atau subjek.'; $msg_type = 'error'; }
    $tab = 'courses';
}

// ── Delete Subject ────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_subject') {
    $subject_id = intval($_POST['subject_id']);
    $del = $conn->prepare("DELETE FROM subjects WHERE subject_id=?");
    $del->bind_param("i", $subject_id);
    if ($del->execute() && $del->affected_rows > 0) { $msg = 'Subjek berjaya dipadam.'; $msg_type = 'info'; }
    else { $msg = 'Ralat semasa memadam subjek.'; $msg_type = 'error'; }
    $tab = 'subjects';
}

// ── Delete Class ──────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_class') {
    $class_id = intval($_POST['class_id']);
    $del = $conn->prepare("DELETE FROM classes WHERE class_id=?");
    $del->bind_param("i", $class_id);
    if ($del->execute() && $del->affected_rows > 0) { $msg = 'Section/kelas berjaya dipadam.'; $msg_type = 'info'; }
    else { $msg = 'Ralat semasa memadam section.'; $msg_type = 'error'; }
    $tab = 'classes';
}

// ── Approve Course Registration ───────────────────
if (isset($_POST['action']) && $_POST['action'] === 'approve_reg') {
    $reg_id = intval($_POST['reg_id']);

    // Ambil maklumat kursus berkaitan permohonan ini terlebih dahulu
    $rinfo = $conn->prepare("
        SELECT cr.user_id, c.faculty, c.course_name, c.education_level
        FROM course_registrations cr
        JOIN courses c ON cr.course_id = c.course_id
        WHERE cr.reg_id = ?
    ");
    $rinfo->bind_param("i", $reg_id);
    $rinfo->execute();
    $rdata = $rinfo->get_result()->fetch_assoc();

    $upd = $conn->prepare("UPDATE course_registrations SET status='approved' WHERE reg_id=?");
    $upd->bind_param("i", $reg_id);
    if ($upd->execute() && $upd->affected_rows > 0) {
        // Kemaskini fakulti, program & taraf pengajian pelajar berdasarkan kursus yang diluluskan
        if ($rdata) {
            $edu_for_user = ($rdata['education_level'] === 'both') ? null : $rdata['education_level'];
            $uupd = $conn->prepare("UPDATE users SET faculty=?, program=?, education_level=COALESCE(?, education_level), year_of_study=COALESCE(year_of_study,1), current_semester=COALESCE(current_semester,1) WHERE user_id=?");
            $uupd->bind_param("sssi", $rdata['faculty'], $rdata['course_name'], $edu_for_user, $rdata['user_id']);
            $uupd->execute();
        }
        $msg = 'Permohonan berjaya diluluskan! Maklumat fakulti, program & tahun pengajian pelajar telah dikemaskini.'; $msg_type = 'success';
    } else {
        $msg = 'Ralat semasa meluluskan permohonan.'; $msg_type = 'error';
    }
    $tab = 'course_requests';
}

// ── Reject Course Registration ────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'reject_reg') {
    $reg_id = intval($_POST['reg_id']);
    $upd = $conn->prepare("UPDATE course_registrations SET status='rejected' WHERE reg_id=?");
    $upd->bind_param("i", $reg_id);
    if ($upd->execute() && $upd->affected_rows > 0) {
        $msg = 'Permohonan telah ditolak.'; $msg_type = 'info';
    } else {
        $msg = 'Ralat semasa menolak permohonan.'; $msg_type = 'error';
    }
    $tab = 'course_requests';
}

// ── Bulk Approve All Pending Registrations ────────
if (isset($_POST['action']) && $_POST['action'] === 'approve_all_regs') {
    $pend = $conn->query("
        SELECT cr.reg_id, cr.user_id, c.faculty, c.course_name, c.education_level
        FROM course_registrations cr
        JOIN courses c ON cr.course_id = c.course_id
        WHERE cr.status = 'pending'
    ");
    $pend_rows = $pend ? $pend->fetch_all(MYSQLI_ASSOC) : [];

    $conn->query("UPDATE course_registrations SET status='approved' WHERE status='pending'");

    foreach ($pend_rows as $rdata) {
        $edu_for_user = ($rdata['education_level'] === 'both') ? null : $rdata['education_level'];
        $uupd = $conn->prepare("UPDATE users SET faculty=?, program=?, education_level=COALESCE(?, education_level), year_of_study=COALESCE(year_of_study,1), current_semester=COALESCE(current_semester,1) WHERE user_id=?");
        $uupd->bind_param("sssi", $rdata['faculty'], $rdata['course_name'], $edu_for_user, $rdata['user_id']);
        $uupd->execute();
    }

    $msg = 'Semua permohonan pending berjaya diluluskan! Maklumat akademik pelajar telah dikemaskini.'; $msg_type = 'success';
    $tab = 'course_requests';
}

// ── Set/Update No. ID Pelajar ─────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'set_student_no') {
    $student_user_id = intval($_POST['student_user_id']);
    $student_no       = trim($_POST['student_no']);

    if (!$student_user_id || empty($student_no)) {
        $msg = 'No. ID Pelajar tidak boleh kosong.'; $msg_type = 'error';
    } else {
        $upd = $conn->prepare("UPDATE users SET student_no=? WHERE user_id=? AND role='student'");
        $upd->bind_param("si", $student_no, $student_user_id);
        if ($upd->execute()) {
            $msg = "No. ID Pelajar '$student_no' berjaya ditetapkan!"; $msg_type = 'success';
        } else {
            $msg = 'Ralat semasa menetapkan No. ID Pelajar.'; $msg_type = 'error';
        }
    }
    $tab = 'senarai_pelajar';
}

// ── Update Staff Profile ─────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $dob       = $_POST['date_of_birth'] ?: null;
    $dept      = trim($_POST['department']);

    if (empty($full_name)) {
        $msg = 'Nama penuh wajib diisi.'; $msg_type = 'error';
    } else {
        $upd = $conn->prepare("UPDATE users SET full_name=?, phone=?, date_of_birth=?, department=? WHERE user_id=?");
        $upd->bind_param("ssssi", $full_name,$phone,$dob,$dept,$staff_id);
        if ($upd->execute()) {
            $_SESSION['full_name'] = $full_name;
            $msg = 'Profil berjaya dikemaskini!'; $msg_type = 'success';
        } else { $msg = 'Ralat semasa kemaskini profil.'; $msg_type = 'error'; }
    }
    $tab = 'profile';
}

// ── Change Password ───────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pw = $_POST['current_password'] ?? '';
    $new_pw     = $_POST['new_password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    $chk = $conn->prepare("SELECT password FROM users WHERE user_id=?");
    $chk->bind_param("i", $staff_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();

    if (!password_verify($current_pw, $row['password'])) {
        $msg = 'Kata laluan semasa tidak betul.'; $msg_type = 'error';
    } elseif (strlen($new_pw) < 8) {
        $msg = 'Kata laluan baharu mesti sekurang-kurangnya 8 aksara.'; $msg_type = 'error';
    } elseif ($new_pw !== $confirm_pw) {
        $msg = 'Kata laluan baharu dan pengesahan tidak sepadan.'; $msg_type = 'error';
    } else {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
        $upd->bind_param("si", $hashed, $staff_id);
        if ($upd->execute()) { $msg = 'Kata laluan berjaya ditukar!'; $msg_type = 'success'; }
        else { $msg = 'Ralat semasa menukar kata laluan.'; $msg_type = 'error'; }
    }
    $tab = 'profile';
}

// ── Assign Student to Class ──────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'assign_student') {
    $class_id = intval($_POST['class_id']);
    $user_id  = intval($_POST['student_id']);

    $chk = $conn->prepare("SELECT cs_id FROM class_students WHERE class_id=? AND user_id=?");
    $chk->bind_param("ii", $class_id, $user_id);
    $chk->execute(); $chk->store_result();

    if ($chk->num_rows > 0) {
        $msg = 'Pelajar ini sudah berada dalam kelas tersebut.'; $msg_type = 'warning';
    } else {
        $ins = $conn->prepare("INSERT INTO class_students (class_id,user_id,assigned_by) VALUES (?,?,?)");
        $ins->bind_param("iii", $class_id, $user_id, $staff_id);
        if ($ins->execute()) { $msg = 'Pelajar berjaya dimasukkan ke dalam kelas!'; $msg_type = 'success'; }
        else { $msg = 'Ralat semasa assign pelajar.'; $msg_type = 'error'; }
    }
    $tab = 'assign_student';
}

// ── Remove Student from Class ────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'remove_student') {
    $cs_id = intval($_POST['cs_id']);
    $del = $conn->prepare("DELETE FROM class_students WHERE cs_id=?");
    $del->bind_param("i", $cs_id);
    if ($del->execute()) { $msg = 'Pelajar berjaya dikeluarkan dari kelas.'; $msg_type = 'info'; }
    $tab = 'assign_student';
}

// ── Assign Subject to Student ────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'assign_subject_student') {
    $user_id    = intval($_POST['student_id']);
    $subject_id = intval($_POST['subject_id']);
    $class_id   = intval($_POST['class_id']) ?: null;

    $chk = $conn->prepare("SELECT ss_id FROM student_subjects WHERE user_id=? AND subject_id=?");
    $chk->bind_param("ii", $user_id, $subject_id);
    $chk->execute(); $chk->store_result();

    if ($chk->num_rows > 0) {
        $msg = 'Subjek ini sudah diassign kepada pelajar tersebut.'; $msg_type = 'warning';
    } else {
        $ins = $conn->prepare("INSERT INTO student_subjects (user_id,subject_id,class_id,assigned_by) VALUES (?,?,?,?)");
        $ins->bind_param("iiii", $user_id, $subject_id, $class_id, $staff_id);
        if ($ins->execute()) { $msg = 'Subjek berjaya ditambah untuk pelajar!'; $msg_type = 'success'; }
        else { $msg = 'Ralat semasa assign subjek.'; $msg_type = 'error'; }
    }
    $tab = 'assign_subject';
}

// ── Remove Student Subject ───────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'remove_student_subject') {
    $ss_id = intval($_POST['ss_id']);
    $del = $conn->prepare("DELETE FROM student_subjects WHERE ss_id=?");
    $del->bind_param("i", $ss_id);
    if ($del->execute()) { $msg = 'Subjek pelajar berjaya dibuang.'; $msg_type = 'info'; }
    $tab = 'assign_subject';
}

// ── Assign Lecturer to Class + Subject ───────────
if (isset($_POST['action']) && $_POST['action'] === 'assign_lecturer') {
    $class_id    = intval($_POST['class_id']);
    $lecturer_id = intval($_POST['lecturer_id']);
    $subject_id  = intval($_POST['subject_id']) ?: null;

    $ins = $conn->prepare("INSERT INTO class_lecturers (class_id,lecturer_id,subject_id,assigned_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE assigned_by=?");
    $ins->bind_param("iiiii", $class_id, $lecturer_id, $subject_id, $staff_id, $staff_id);
    if ($ins->execute()) { $msg = 'Pensyarah berjaya di-assign ke kelas!'; $msg_type = 'success'; }
    else { $msg = 'Ralat semasa assign pensyarah.'; $msg_type = 'error'; }
    $tab = 'assign_lecturer';
}

// ── Assign Subject to Lecturer (directly, without class) ──
if (isset($_POST['action']) && $_POST['action'] === 'assign_lect_subject') {
    $lecturer_id = intval($_POST['lecturer_id']);
    $subject_id  = intval($_POST['subject_id']);
    $class_id    = intval($_POST['class_id']) ?: null;

    if (!$lecturer_id || !$subject_id) {
        $msg = 'Sila pilih pensyarah dan subjek.'; $msg_type = 'error';
    } else {
        // Check if assignment already exists
        $chk = $conn->prepare("SELECT cl_id FROM class_lecturers WHERE lecturer_id=? AND subject_id=?" . ($class_id ? " AND class_id=?" : " AND class_id IS NULL"));
        if ($class_id) {
            $chk->bind_param("iii", $lecturer_id, $subject_id, $class_id);
        } else {
            $chk->bind_param("ii", $lecturer_id, $subject_id);
        }
        $chk->execute(); $chk->store_result();

        if ($chk->num_rows > 0) {
            $msg = 'Pensyarah ini sudah diassign kepada subjek tersebut.'; $msg_type = 'warning';
        } else {
            if ($class_id) {
                $ins = $conn->prepare("INSERT INTO class_lecturers (class_id, lecturer_id, subject_id, assigned_by) VALUES (?,?,?,?)");
                $ins->bind_param("iiii", $class_id, $lecturer_id, $subject_id, $staff_id);
            } else {
                // Assign to all classes that have this subject
                $get_classes = $conn->prepare("
                    SELECT DISTINCT cl.class_id FROM classes cl
                    JOIN subjects s ON s.course_id = cl.course_id
                    WHERE s.subject_id = ?
                ");
                $get_classes->bind_param("i", $subject_id);
                $get_classes->execute();
                $class_rows = $get_classes->get_result()->fetch_all(MYSQLI_ASSOC);

                $success_count = 0;
                foreach ($class_rows as $cr) {
                    $ins = $conn->prepare("INSERT IGNORE INTO class_lecturers (class_id, lecturer_id, subject_id, assigned_by) VALUES (?,?,?,?)");
                    $ins->bind_param("iiii", $cr['class_id'], $lecturer_id, $subject_id, $staff_id);
                    if ($ins->execute()) $success_count++;
                }
                $msg = "Subjek berjaya diassign kepada pensyarah untuk $success_count kelas!"; $msg_type = 'success';
                $tab = 'assign_lect_subject'; goto skip_insert;
            }
            if ($ins->execute()) { $msg = 'Subjek berjaya diassign kepada pensyarah!'; $msg_type = 'success'; }
            else { $msg = 'Ralat semasa assign subjek.'; $msg_type = 'error'; }
        }
    }
    skip_insert:
    $tab = 'assign_lect_subject';
}

// ── Remove Lecturer Subject Assignment ────────────
if (isset($_POST['action']) && $_POST['action'] === 'remove_lect_subject') {
    $cl_id = intval($_POST['cl_id']);
    $del = $conn->prepare("DELETE FROM class_lecturers WHERE cl_id=?");
    $del->bind_param("i", $cl_id);
    if ($del->execute()) { $msg = 'Assignment subjek pensyarah berjaya dibuang.'; $msg_type = 'info'; }
    $tab = 'assign_lect_subject';
}

// ── Remove Lecturer from Class ───────────────────
if (isset($_POST['action']) && $_POST['action'] === 'remove_lecturer') {
    $cl_id = intval($_POST['cl_id']);
    $del = $conn->prepare("DELETE FROM class_lecturers WHERE cl_id=?");
    $del->bind_param("i", $cl_id);
    if ($del->execute()) { $msg = 'Pensyarah berjaya dikeluarkan dari kelas.'; $msg_type = 'info'; }
    $tab = 'assign_lecturer';
}

// ══════════════════════════════════════════════════
// AUTO-CREATE TABLES IF NOT EXISTS
// ══════════════════════════════════════════════════
$conn->query("CREATE TABLE IF NOT EXISTS subjects (
    subject_id   INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(30)  UNIQUE NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    course_id    INT NOT NULL,
    credit_hours INT DEFAULT 3,
    description  TEXT DEFAULT NULL,
    status       ENUM('active','inactive') DEFAULT 'active',
    created_by   INT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)  REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS classes (
    class_id     INT AUTO_INCREMENT PRIMARY KEY,
    class_code   VARCHAR(30)  UNIQUE NOT NULL,
    class_name   VARCHAR(100) NOT NULL,
    course_id    INT NOT NULL,
    semester     VARCHAR(20) DEFAULT '2024/2025-1',
    day          VARCHAR(20) DEFAULT NULL,
    time_start   TIME DEFAULT NULL,
    time_end     TIME DEFAULT NULL,
    venue        VARCHAR(100) DEFAULT NULL,
    max_students INT DEFAULT 40,
    status       ENUM('active','inactive') DEFAULT 'active',
    created_by   INT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)  REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS class_students (
    cs_id       INT AUTO_INCREMENT PRIMARY KEY,
    class_id    INT NOT NULL,
    user_id     INT NOT NULL,
    assigned_by INT DEFAULT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cs (class_id, user_id),
    FOREIGN KEY (class_id)    REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS class_lecturers (
    cl_id       INT AUTO_INCREMENT PRIMARY KEY,
    class_id    INT NOT NULL,
    lecturer_id INT NOT NULL,
    subject_id  INT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cl (class_id, lecturer_id, subject_id),
    FOREIGN KEY (class_id)    REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id)  REFERENCES subjects(subject_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS student_subjects (
    ss_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    subject_id  INT NOT NULL,
    class_id    INT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    status      ENUM('active','dropped') DEFAULT 'active',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ss (user_id, subject_id),
    FOREIGN KEY (user_id)     REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id)  REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)    REFERENCES classes(class_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
)");

// ══════════════════════════════════════════════════
// FETCH DATA
// ══════════════════════════════════════════════════
$r = $conn->query("SELECT * FROM courses ORDER BY course_code");
$courses = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

$r = $conn->query("SELECT s.*, c.course_name, c.course_code FROM subjects s JOIN courses c ON s.course_id=c.course_id ORDER BY s.subject_code");
$subjects = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

$r = $conn->query("SELECT cl.*, c.course_name, c.course_code FROM classes cl JOIN courses c ON cl.course_id=c.course_id ORDER BY cl.class_code");
$classes = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// Dropdown students (for assign forms)
$r = $conn->query("SELECT user_id, full_name, username, student_no, faculty FROM users WHERE role='student' AND status='active' ORDER BY full_name");
$students = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// Full student list for senarai pelajar tab
$r = $conn->query("
    SELECT
        u.user_id, u.full_name, u.username, u.date_of_birth, u.phone, u.email,
        u.faculty, u.program AS course_name, u.student_no,
        u.education_level, u.current_semester, u.intake_year,
        u.year_of_study, u.status,
        (SELECT GROUP_CONCAT(cl.class_code ORDER BY cl.class_code SEPARATOR ', ')
         FROM class_students cs JOIN classes cl ON cs.class_id=cl.class_id
         WHERE cs.user_id=u.user_id) AS kelas
    FROM users u
    WHERE u.role = 'student'
    ORDER BY u.education_level, u.full_name
");
$students_full = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

$r = $conn->query("SELECT user_id, full_name, username, department FROM users WHERE role='lecturer' AND status='active' ORDER BY full_name");
$lecturers = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// Full lecturer list (semua status) untuk tab Senarai Pensyarah
$r = $conn->query("
    SELECT u.user_id, u.full_name, u.username, u.email, u.phone, u.date_of_birth,
           u.staff_no, u.department, u.status, u.created_at,
           (SELECT COUNT(DISTINCT cl.class_id) FROM class_lecturers cl WHERE cl.lecturer_id = u.user_id) AS total_classes,
           (SELECT GROUP_CONCAT(DISTINCT s.subject_code ORDER BY s.subject_code SEPARATOR ', ')
              FROM class_lecturers cl2 JOIN subjects s ON cl2.subject_id = s.subject_id
              WHERE cl2.lecturer_id = u.user_id) AS subject_codes
    FROM users u
    WHERE u.role = 'lecturer'
    ORDER BY u.full_name
");
$lecturers_full = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// Semester definitions
$r = $conn->query("SELECT * FROM semester_definitions ORDER BY education_level, semester_no");
$sem_defs = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// Overview counts
$total_courses   = count($courses);
$total_subjects  = count($subjects);
$total_classes   = count($classes);
$total_students  = count($students);
$total_asasi     = count(array_filter($students_full, fn($s) => $s['education_level'] === 'asasi'));
$total_diploma   = count(array_filter($students_full, fn($s) => $s['education_level'] === 'diploma'));

function alertIcon($type) {
    return match($type) {
        'success' => 'circle-check',
        'warning' => 'triangle-exclamation',
        'info'    => 'circle-info',
        default   => 'circle-exclamation'
    };
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Staf — UPTM</title>
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
            --yellow-700:  #a16207;
            --red-50:      #fef2f2;
            --red-500:     #ef4444;
            --red-700:     #b91c1c;
            --purple-50:   #f5f3ff;
            --purple-700:  #6d28d9;
            --sidebar-w:   265px;
        }
        body { font-family:'Inter',sans-serif; background:var(--gray-50); color:var(--gray-700); display:flex; min-height:100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width:var(--sidebar-w); flex-shrink:0;
            background:linear-gradient(175deg,#0a1f4e 0%,var(--blue-mid) 100%);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; bottom:0; z-index:100; overflow-y:auto;
        }
        .sidebar-brand { padding:26px 22px 18px; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo { font-size:26px; margin-bottom:6px; }
        .sidebar-brand h2 { color:var(--white); font-size:13px; font-weight:700; line-height:1.3; }
        .sidebar-brand span { color:rgba(255,255,255,0.5); font-size:11px; }

        .sidebar-profile { padding:18px 22px; border-bottom:1px solid rgba(255,255,255,0.1); }
        .profile-avatar {
            width:48px; height:48px; border-radius:12px;
            background:rgba(255,255,255,0.14); border:2px solid rgba(255,255,255,0.22);
            display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:9px;
        }
        .profile-name  { color:var(--white); font-size:13px; font-weight:600; margin-bottom:2px; }
        .profile-sub   { color:rgba(255,255,255,0.5); font-size:11px; }
        .profile-badge {
            display:inline-block; margin-top:6px;
            background:rgba(255,165,0,0.2); border:1px solid rgba(255,165,0,0.35);
            border-radius:20px; padding:3px 10px;
            color:#fcd34d; font-size:10px; font-weight:700;
        }

        .sidebar-nav { padding:14px 10px; flex:1; }
        .nav-label { color:rgba(255,255,255,0.32); font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; padding:0 12px; margin:14px 0 5px; }
        .nav-item {
            display:flex; align-items:center; gap:11px;
            padding:10px 13px; border-radius:9px; cursor:pointer;
            color:rgba(255,255,255,0.62); font-size:13px; font-weight:500;
            text-decoration:none; transition:all .2s; margin-bottom:2px;
        }
        .nav-item i { width:17px; text-align:center; font-size:13px; }
        .nav-item:hover  { background:rgba(255,255,255,0.1); color:var(--white); }
        .nav-item.active { background:rgba(255,255,255,0.17); color:var(--white); font-weight:600; }

        .sidebar-footer { padding:14px 10px; border-top:1px solid rgba(255,255,255,0.1); }
        .btn-logout {
            display:flex; align-items:center; gap:10px; width:100%;
            padding:10px 13px; border-radius:9px;
            background:rgba(239,68,68,0.14); border:1px solid rgba(239,68,68,0.22);
            color:#fca5a5; font-size:13px; font-weight:600;
            cursor:pointer; font-family:'Inter',sans-serif; transition:all .2s;
        }
        .btn-logout:hover { background:rgba(239,68,68,0.26); }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar {
            background:var(--white); border-bottom:1px solid var(--gray-200);
            padding:15px 30px; display:flex; align-items:center; justify-content:space-between;
            position:sticky; top:0; z-index:50;
        }
        .topbar h1 { font-size:17px; font-weight:700; color:var(--gray-900); }
        .topbar p  { font-size:12px; color:var(--gray-500); margin-top:1px; }
        .topbar-date { font-size:12px; color:var(--gray-500); }

        .content { padding:26px 30px; flex:1; }

        /* Alert */
        .alert {
            display:flex; align-items:flex-start; gap:11px;
            padding:13px 17px; border-radius:11px; font-size:14px;
            margin-bottom:22px; font-weight:500; line-height:1.5;
        }
        .alert-success { background:var(--green-50);  color:var(--green-700); border:1px solid #bbf7d0; }
        .alert-warning { background:var(--yellow-50); color:var(--yellow-700);border:1px solid #fde68a; }
        .alert-error   { background:var(--red-50);    color:var(--red-700);   border:1px solid #fecaca; }
        .alert-info    { background:var(--blue-pale);  color:var(--blue-mid);  border:1px solid #bfdbfe; }

        /* Stats */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:26px; }
        .stat-card {
            background:var(--white); border-radius:13px; padding:18px;
            border:1px solid var(--gray-200); display:flex; align-items:center; gap:14px;
        }
        .stat-icon { width:46px; height:46px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .si-blue   { background:var(--blue-pale); }
        .si-green  { background:var(--green-50); }
        .si-yellow { background:var(--yellow-50); }
        .si-purple { background:var(--purple-50); }
        .stat-val  { font-size:26px; font-weight:700; color:var(--gray-900); line-height:1; }
        .stat-lbl  { font-size:12px; color:var(--gray-500); margin-top:3px; }

        /* Card */
        .card { background:var(--white); border-radius:13px; border:1px solid var(--gray-200); overflow:hidden; margin-bottom:22px; }
        .card-header {
            padding:17px 22px; border-bottom:1px solid var(--gray-100);
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
        }
        .card-header h3 { font-size:15px; font-weight:700; color:var(--gray-900); }
        .card-header p  { font-size:12px; color:var(--gray-500); margin-top:2px; }
        .card-body { padding:22px; }

        /* Form */
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
        .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px 18px; }
        .full { grid-column:1/-1; }
        .form-group label {
            display:block; font-size:11px; font-weight:700; text-transform:uppercase;
            letter-spacing:0.6px; color:var(--gray-500); margin-bottom:6px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width:100%; padding:10px 13px;
            border:1.5px solid var(--gray-200); border-radius:9px;
            font-size:13px; font-family:'Inter',sans-serif; color:var(--gray-700);
            background:var(--gray-50); outline:none; transition:border-color .2s;
            appearance:none; -webkit-appearance:none;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { border-color:var(--blue-bright); background:var(--white); box-shadow:0 0 0 3px rgba(37,99,235,0.08); }
        .form-group textarea { resize:vertical; min-height:70px; }

        /* Table */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        thead th {
            padding:10px 13px; text-align:left; font-size:11px;
            font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
            color:var(--gray-500); background:var(--gray-50); border-bottom:1px solid var(--gray-200);
            white-space:nowrap;
        }
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

        /* Buttons */
        .btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:9px 16px; border-radius:8px; font-size:13px; font-weight:600;
            font-family:'Inter',sans-serif; cursor:pointer; border:none; transition:all .2s; text-decoration:none;
        }
        .btn-primary { background:var(--blue-bright); color:var(--white); }
        .btn-primary:hover { background:var(--blue-mid); }
        .btn-danger  { background:var(--red-50);   color:var(--red-700); border:1px solid #fecaca; }
        .btn-danger:hover { background:#fee2e2; }
        .btn-sm { padding:6px 11px; font-size:12px; }
        .btn-success { background:var(--green-50); color:var(--green-700); border:1px solid #bbf7d0; }

        /* Section divider */
        .section-sep { height:1px; background:var(--gray-100); margin:22px 0; }

        /* Two-col layout for assign pages */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }

        /* Search */
        .search-wrap { position:relative; margin-bottom:14px; }
        .search-wrap i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--gray-300); font-size:13px; }
        .search-wrap input {
            width:100%; padding:9px 13px 9px 37px;
            border:1.5px solid var(--gray-200); border-radius:9px;
            font-size:13px; font-family:'Inter',sans-serif; color:var(--gray-700);
            background:var(--gray-50); outline:none; transition:border-color .2s;
        }
        .search-wrap input:focus { border-color:var(--blue-bright); background:var(--white); }

        /* Course checkbox cards (subjects form) */
        .course-check-item {
            display:flex; align-items:center; gap:8px;
            padding:8px 11px; border-radius:8px; cursor:pointer;
            border:1.5px solid var(--gray-200); background:var(--white);
            transition:all .15s; user-select:none;
        }
        .course-check-item:hover { border-color:var(--blue-light); background:#eff6ff; }
        .course-check-item.selected { border-color:var(--blue-bright); background:var(--blue-pale); }
        .course-check-item input[type="checkbox"] { width:14px; height:14px; accent-color:var(--blue-bright); flex-shrink:0; pointer-events:none; }
        .course-check-code { font-size:11px; font-weight:700; color:var(--blue-bright); background:rgba(37,99,235,0.1); padding:2px 7px; border-radius:4px; white-space:nowrap; flex-shrink:0; }
        .course-check-name { font-size:12px; color:var(--gray-700); font-weight:500; line-height:1.3; }
        .course-check-item.selected .course-check-name { color:var(--blue-mid); }

        /* Empty state */
        .empty { text-align:center; padding:36px 16px; }
        .empty .icon { font-size:40px; opacity:.35; margin-bottom:10px; }
        .empty p { font-size:13px; color:var(--gray-300); }

        /* Profile grid (used in Profile tab) */
        .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .profile-field label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--gray-500); display:block; margin-bottom:5px; }
        .profile-field .val  { font-size:13px; color:var(--gray-900); font-weight:500; padding:9px 13px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; }
        .profile-field.full  { grid-column:1/-1; }

        .btn-gray { background:var(--gray-100); color:var(--gray-500); }
        .btn-gray:hover { background:var(--gray-200); }

        /* Modal */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55);
            z-index:1000; align-items:center; justify-content:center; padding:20px;
            backdrop-filter:blur(2px);
        }
        .modal-overlay.show { display:flex; }
        .modal-box {
            background:var(--white); border-radius:16px; width:100%; max-width:640px;
            max-height:88vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);
            animation:modalPop .18s ease;
        }
        @keyframes modalPop { from { opacity:0; transform:translateY(10px) scale(.98); } to { opacity:1; transform:translateY(0) scale(1); } }
        .modal-header {
            display:flex; align-items:center; justify-content:space-between;
            padding:18px 22px; border-bottom:1px solid var(--gray-100);
            position:sticky; top:0; background:var(--white); z-index:2;
        }
        .modal-header h3 { font-size:16px; font-weight:700; color:var(--gray-900); display:flex; align-items:center; gap:8px; }
        .modal-close {
            background:var(--gray-100); border:none; width:30px; height:30px; border-radius:8px;
            color:var(--gray-500); cursor:pointer; display:flex; align-items:center; justify-content:center;
            transition:background .2s;
        }
        .modal-close:hover { background:var(--gray-200); }
        .modal-body { padding:22px; }
        .modal-footer {
            display:flex; justify-content:flex-end; gap:10px;
            padding:16px 22px; border-top:1px solid var(--gray-100);
            position:sticky; bottom:0; background:var(--white);
        }

        @media (max-width:1100px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .two-col { grid-template-columns:1fr; } }
        @media (max-width:768px)  { .sidebar { transform:translateX(-100%); } .main { margin-left:0; } .form-grid-2,.form-grid-3 { grid-template-columns:1fr; } .full { grid-column:1; } .profile-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo">💼</div>
        <h2>Student Registration<br>System</h2>
        <span>UPTM — Staff Portal</span>
    </div>
    <div class="sidebar-profile">
        <div class="profile-avatar">👤</div>
        <div class="profile-name"><?= htmlspecialchars($staff['full_name']) ?></div>
        <div class="profile-sub"><?= htmlspecialchars($staff['email']) ?></div>
        <span class="profile-badge">💼 Staf</span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Utama</div>
        <a href="?tab=overview"         class="nav-item <?= $tab==='overview'?'active':'' ?>"><i class="fas fa-house"></i> Gambaran Keseluruhan</a>
        <a href="?tab=profile"          class="nav-item <?= $tab==='profile'?'active':'' ?>"><i class="fas fa-user"></i> Profil Saya</a>

        <div class="nav-label">Pengurusan Akademik</div>
        <a href="?tab=courses"          class="nav-item <?= $tab==='courses'?'active':'' ?>"><i class="fas fa-book"></i> Kursus</a>
        <a href="?tab=subjects"         class="nav-item <?= $tab==='subjects'?'active':'' ?>"><i class="fas fa-bookmark"></i> Subjek</a>
        <a href="?tab=classes"          class="nav-item <?= $tab==='classes'?'active':'' ?>"><i class="fas fa-chalkboard"></i> Kelas</a>

        <div class="nav-label">Pengurusan Pelajar & Pensyarah</div>
        <a href="?tab=course_requests"  class="nav-item <?= $tab==='course_requests'?'active':'' ?>">
            <i class="fas fa-clipboard-check"></i> Permohonan Kursus
            <?php
            $pending_regs = $conn->query("SELECT COUNT(*) FROM course_registrations WHERE status='pending'")->fetch_row()[0];
            if ($pending_regs > 0): ?>
            <span style="margin-left:auto;background:#ef4444;color:white;border-radius:20px;padding:1px 7px;font-size:10px;font-weight:700"><?= $pending_regs ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=senarai_pelajar"  class="nav-item <?= $tab==='senarai_pelajar'?'active':'' ?>"><i class="fas fa-users"></i> Senarai Pelajar</a>
        <a href="?tab=senarai_pensyarah" class="nav-item <?= $tab==='senarai_pensyarah'?'active':'' ?>"><i class="fas fa-chalkboard-teacher"></i> Senarai Pensyarah</a>
        <a href="?tab=assign_student"   class="nav-item <?= $tab==='assign_student'?'active':'' ?>"><i class="fas fa-user-plus"></i> Assign Pelajar ke Kelas</a>
        <a href="?tab=assign_subject"   class="nav-item <?= $tab==='assign_subject'?'active':'' ?>"><i class="fas fa-list-check"></i> Assign Subjek ke Pelajar</a>
        <a href="?tab=assign_lecturer"         class="nav-item <?= $tab==='assign_lecturer'?'active':'' ?>"><i class="fas fa-chalkboard-user"></i> Assign Pensyarah ke Kelas</a>
        <a href="?tab=assign_lect_subject"     class="nav-item <?= $tab==='assign_lect_subject'?'active':'' ?>"><i class="fas fa-link"></i> Assign Subjek ke Pensyarah</a>
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
                $titles = ['overview'=>'Gambaran Keseluruhan','profile'=>'Profil Saya','courses'=>'Pengurusan Kursus','subjects'=>'Pengurusan Subjek','classes'=>'Pengurusan Kelas','course_requests'=>'Permohonan Kursus','senarai_pelajar'=>'Senarai Pelajar','senarai_pensyarah'=>'Senarai Pensyarah','assign_student'=>'Assign Pelajar ke Kelas','assign_subject'=>'Assign Subjek ke Pelajar','assign_lecturer'=>'Assign Pensyarah ke Kelas','assign_lect_subject'=>'Assign Subjek ke Pensyarah'];
                echo $titles[$tab] ?? 'Dashboard';
            ?></h1>
            <p>Semester 2024/2025-1 &middot; <?= date('d M Y') ?></p>
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
            <div class="stat-card"><div class="stat-icon si-blue">📚</div><div><div class="stat-val"><?= $total_courses ?></div><div class="stat-lbl">Jumlah Kursus</div></div></div>
            <div class="stat-card"><div class="stat-icon si-purple">📖</div><div><div class="stat-val"><?= $total_subjects ?></div><div class="stat-lbl">Jumlah Subjek</div></div></div>
            <div class="stat-card"><div class="stat-icon si-green">🏫</div><div><div class="stat-val"><?= $total_classes ?></div><div class="stat-lbl">Jumlah Kelas</div></div></div>
            <div class="stat-card"><div class="stat-icon si-yellow">🎓</div><div><div class="stat-val"><?= $total_students ?></div><div class="stat-lbl">Jumlah Pelajar Aktif</div></div></div>
        </div>
        <!-- Asasi vs Diploma breakdown -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px">
            <div class="stat-card" style="border-left:4px solid #eab308">
                <div class="stat-icon si-yellow">🏅</div>
                <div><div class="stat-val"><?= $total_asasi ?></div><div class="stat-lbl">Pelajar Asasi <span style="font-size:10px;color:var(--gray-300)">(1 Tahun / 2 Sem)</span></div></div>
            </div>
            <div class="stat-card" style="border-left:4px solid #2563eb">
                <div class="stat-icon si-blue">🎓</div>
                <div><div class="stat-val"><?= $total_diploma ?></div><div class="stat-lbl">Pelajar Diploma <span style="font-size:10px;color:var(--gray-300)">(2 Tahun / 4 Sem)</span></div></div>
            </div>
        </div>

        <div class="two-col">
            <!-- Recent Classes -->
            <div class="card">
                <div class="card-header"><div><h3>🏫 Kelas Terkini</h3><p>Kelas yang baru ditambah</p></div><a href="?tab=classes" class="btn btn-primary btn-sm"><i class="fas fa-arrow-right"></i> Lihat Semua</a></div>
                <?php $recent_cls = array_slice(array_reverse($classes), 0, 5); ?>
                <?php if ($recent_cls): ?>
                <table><thead><tr><th>Kod</th><th>Nama</th><th>Kursus</th></tr></thead><tbody>
                <?php foreach ($recent_cls as $cl): ?>
                <tr><td><span class="badge badge-blue"><?= htmlspecialchars($cl['class_code']) ?></span></td><td><?= htmlspecialchars($cl['class_name']) ?></td><td><span class="badge badge-gray"><?= htmlspecialchars($cl['course_code']) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else: ?><div class="empty"><div class="icon">🏫</div><p>Tiada kelas lagi</p></div><?php endif; ?>
            </div>
            <!-- Quick actions -->
            <div class="card">
                <div class="card-header"><div><h3>⚡ Tindakan Cepat</h3><p>Shortcut ke fungsi utama</p></div></div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
                    <a href="?tab=courses" class="btn btn-primary" style="justify-content:center"><i class="fas fa-plus"></i> Tambah Kursus Baru</a>
                    <a href="?tab=subjects" class="btn btn-primary" style="justify-content:center"><i class="fas fa-plus"></i> Tambah Subjek Baru</a>
                    <a href="?tab=classes" class="btn btn-primary" style="justify-content:center"><i class="fas fa-plus"></i> Tambah Kelas Baru</a>
                    <a href="?tab=assign_student" class="btn btn-success" style="justify-content:center"><i class="fas fa-user-plus"></i> Assign Pelajar ke Kelas</a>
                    <a href="?tab=assign_lecturer" class="btn btn-success" style="justify-content:center"><i class="fas fa-chalkboard-user"></i> Assign Pensyarah</a>
                </div>
            </div>
        </div>

        <!-- ══ PROFILE ══ -->
        <?php elseif ($tab === 'profile'): ?>
        <div class="two-col">
            <!-- Update Profile -->
            <div class="card">
                <div class="card-header"><div><h3>👤 Maklumat Peribadi</h3><p>Kemaskini maklumat profil anda</p></div></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-grid-2">
                            <div class="form-group full"><label>Nama Penuh *</label><input type="text" name="full_name" value="<?= htmlspecialchars($staff['full_name']) ?>" required></div>
                            <div class="form-group"><label>Username</label><input type="text" value="<?= htmlspecialchars($staff['username']) ?>" disabled style="background:var(--gray-100);color:var(--gray-300)"></div>
                            <div class="form-group"><label>E-mel</label><input type="email" value="<?= htmlspecialchars($staff['email']) ?>" disabled style="background:var(--gray-100);color:var(--gray-300)"></div>
                            <div class="form-group"><label>No. Telefon</label><input type="tel" name="phone" value="<?= htmlspecialchars($staff['phone'] ?? '') ?>" placeholder="Cth: 0123456789"></div>
                            <div class="form-group"><label>Tarikh Lahir</label><input type="date" name="date_of_birth" value="<?= htmlspecialchars($staff['date_of_birth'] ?? '') ?>"></div>
                            <div class="form-group full"><label>Jabatan</label><input type="text" name="department" value="<?= htmlspecialchars($staff['department'] ?? '') ?>" placeholder="Cth: Academic Affairs"></div>
                        </div>
                        <div style="margin-top:16px"><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan Perubahan</button></div>
                    </form>
                </div>
            </div>

            <!-- Account Info + Change Password -->
            <div>
                <div class="card">
                    <div class="card-header"><div><h3>🆔 Maklumat Akaun</h3></div></div>
                    <div class="card-body">
                        <div class="profile-grid">
                            <div class="profile-field"><label>Peranan</label><div class="val"><span class="badge badge-blue">💼 Staf</span></div></div>
                            <div class="profile-field"><label>Status Akaun</label><div class="val"><span class="badge <?= $staff['status']==='active'?'badge-green':'badge-yellow' ?>"><?= ucfirst($staff['status']) ?></span></div></div>
                            <div class="profile-field full"><label>Tarikh Daftar</label><div class="val"><?= date('d M Y, h:i A', strtotime($staff['created_at'])) ?></div></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3>🔒 Tukar Kata Laluan</h3><p>Pastikan kata laluan baharu kukuh</p></div></div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return validatePwForm()">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group" style="margin-bottom:14px">
                                <label>Kata Laluan Semasa *</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <div class="form-group" style="margin-bottom:14px">
                                <label>Kata Laluan Baharu *</label>
                                <input type="password" name="new_password" id="staffNewPw" minlength="8" required>
                            </div>
                            <div class="form-group" style="margin-bottom:16px">
                                <label>Sahkan Kata Laluan Baharu *</label>
                                <input type="password" name="confirm_password" id="staffConfirmPw" minlength="8" required>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-key"></i> Tukar Kata Laluan</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ COURSES ══ -->
        <?php elseif ($tab === 'courses'): ?>
        <!-- Add Course Form -->
        <div class="card">
            <div class="card-header"><div><h3>➕ Tambah Kursus Baru</h3><p>Isi maklumat kursus di bawah</p></div></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_course">
                    <div class="form-grid-3">
                        <div class="form-group"><label>Kod Kursus *</label><input type="text" name="course_code" placeholder="Cth: SWC3533" required></div>
                        <div class="form-group">
                            <label>Taraf Pendidikan *</label>
                            <select name="education_level" required>
                                <option value="both">Asasi & Diploma</option>
                                <option value="asasi">Asasi sahaja (1 Tahun / 2 Sem)</option>
                                <option value="diploma">Diploma sahaja (2 Tahun / 4 Sem)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Maks. Pelajar</label><input type="number" name="max_students" value="40" min="1"></div>
                        <div class="form-group full"><label>Nama Kursus *</label><input type="text" name="course_name" placeholder="Cth: System Design" required></div>
                        <div class="form-group full"><label>Fakulti</label><input type="text" name="faculty" placeholder="Cth: Faculty of Computing"></div>
                        <div class="form-group full"><label>Penerangan</label><textarea name="description" placeholder="Penerangan ringkas tentang kursus ini..."></textarea></div>
                    </div>
                    <div style="margin-top:16px"><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Kursus</button></div>
                </form>
            </div>
        </div>
        <!-- Courses List -->
        <div class="card">
            <div class="card-header"><div><h3>📚 Senarai Kursus</h3><p>Semua kursus dalam sistem</p></div></div>
            <div class="table-wrap">
                <?php if ($courses): ?>
                <table><thead><tr><th>#</th><th>Kod</th><th>Nama Kursus</th><th>Taraf Pendidikan</th><th>Fakulti</th><th>Maks</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
                <?php foreach ($courses as $i => $c):
                    $edu = $c['education_level'] ?? 'both';
                    $edu_label = match($edu) { 'asasi'=>'Asasi', 'diploma'=>'Diploma', default=>'Asasi & Diploma' };
                    $edu_badge = match($edu) { 'asasi'=>'badge-yellow', 'diploma'=>'badge-blue', default=>'badge-purple' };
                ?>
                <tr>
                    <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($c['course_code']) ?></span></td>
                    <td><strong><?= htmlspecialchars($c['course_name']) ?></strong></td>
                    <td><span class="badge <?= $edu_badge ?>"><?= $edu_label ?></span></td>
                    <td><?= htmlspecialchars($c['faculty'] ?? '—') ?></td>
                    <td style="text-align:center"><?= $c['max_students'] ?></td>
                    <td><span class="badge <?= $c['status']==='open'?'badge-green':'badge-red' ?>"><?= $c['status']==='open'?'Dibuka':'Ditutup' ?></span></td>
                    <td style="display:flex;gap:6px">
                        <button type="button" class="btn btn-sm btn-primary" onclick='openEditCourse(<?= json_encode($c) ?>)'><i class="fas fa-pen"></i></button>
                        <form method="POST" onsubmit="return confirm('Padam kursus <?= htmlspecialchars($c['course_code'],ENT_QUOTES) ?>? Subjek dan kelas yang berkaitan mungkin turut terjejas.')">
                            <input type="hidden" name="action" value="delete_course">
                            <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else: ?><div class="empty"><div class="icon">📚</div><p>Tiada kursus lagi. Tambah kursus di atas.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- Edit Course Modal -->
        <div class="modal-overlay" id="editCourseModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><i class="fas fa-pen"></i> Edit Kursus</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editCourseModal')"><i class="fas fa-xmark"></i></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_course">
                    <input type="hidden" name="course_id" id="ec_course_id">
                    <div class="modal-body">
                        <div class="form-grid-2">
                            <div class="form-group"><label>Kod Kursus *</label><input type="text" name="course_code" id="ec_code" required></div>
                            <div class="form-group">
                                <label>Taraf Pendidikan *</label>
                                <select name="education_level" id="ec_edu" required>
                                    <option value="both">Asasi & Diploma</option>
                                    <option value="asasi">Asasi sahaja</option>
                                    <option value="diploma">Diploma sahaja</option>
                                </select>
                            </div>
                            <div class="form-group full"><label>Nama Kursus *</label><input type="text" name="course_name" id="ec_name" required></div>
                            <div class="form-group"><label>Fakulti</label><input type="text" name="faculty" id="ec_faculty"></div>
                            <div class="form-group"><label>Maks. Pelajar</label><input type="number" name="max_students" id="ec_max" min="1"></div>
                            <div class="form-group"><label>Status</label>
                                <select name="status" id="ec_status">
                                    <option value="open">Dibuka</option>
                                    <option value="closed">Ditutup</option>
                                </select>
                            </div>
                            <div class="form-group full"><label>Penerangan</label><textarea name="description" id="ec_desc"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-gray" onclick="closeModal('editCourseModal')">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ SUBJECTS ══ -->
        <?php elseif ($tab === 'subjects'): ?>
        <div class="card">
            <div class="card-header"><div><h3>➕ Tambah Subjek Baru</h3><p>Tambah subjek — boleh pilih lebih dari satu kursus untuk subjek yang sama</p></div></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_subject">
                    <div class="form-grid-3">
                        <div class="form-group"><label>Kod Subjek *</label><input type="text" name="subject_code" placeholder="Cth: DBS101" required></div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester_no" required>
                                <option value="">-- Pilih Semester --</option>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                                <option value="3">Semester 3 (Diploma sahaja)</option>
                                <option value="4">Semester 4 (Diploma sahaja)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Jam Kredit</label><input type="number" name="credit_hours" value="3" min="1" max="6"></div>
                        <div class="form-group full"><label>Nama Subjek *</label><input type="text" name="subject_name" placeholder="Cth: Database Design & Implementation" required></div>
                        <div class="form-group full">
                            <label>Kursus * <span style="font-weight:400;text-transform:none;color:var(--gray-300);letter-spacing:0">(boleh pilih lebih dari satu)</span></label>
                            <div id="courseCheckboxList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;padding:12px;border:1.5px solid var(--gray-200);border-radius:9px;background:var(--gray-50);max-height:200px;overflow-y:auto">
                                <?php foreach ($courses as $c): ?>
                                <div class="course-check-item" onclick="toggleCourse(this)">
                                    <input type="checkbox" name="course_ids[]" value="<?= $c['course_id'] ?>" id="course_<?= $c['course_id'] ?>">
                                    <span class="course-check-code"><?= htmlspecialchars($c['course_code']) ?></span>
                                    <span class="course-check-name"><?= htmlspecialchars($c['course_name']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:6px;font-size:11px;color:var(--gray-300)">
                                <i class="fas fa-circle-info" style="margin-right:4px"></i>Klik pada kad kursus untuk pilih/nyahpilih
                            </div>
                        </div>
                        <div class="form-group full"><label>Penerangan</label><textarea name="description" placeholder="Penerangan ringkas subjek ini..."></textarea></div>
                    </div>
                    <div style="margin-top:16px"><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Subjek</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div><h3>📖 Senarai Subjek</h3><p>Semua subjek mengikut kursus dan semester</p></div></div>
            <div class="table-wrap">
                <?php if ($subjects): ?>
                <table><thead><tr><th>#</th><th>Kod Subjek</th><th>Nama Subjek</th><th>Kursus</th><th>Semester</th><th>Kredit</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
                <?php foreach ($subjects as $i => $s): ?>
                <tr>
                    <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                    <td><span class="badge badge-purple"><?= htmlspecialchars($s['subject_code']) ?></span></td>
                    <td><?= htmlspecialchars($s['subject_name']) ?></td>
                    <td><span class="badge badge-gray"><?= htmlspecialchars($s['course_code']) ?></span></td>
                    <td><?= $s['semester_no'] ? '<span class="badge badge-blue">Sem '.$s['semester_no'].'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                    <td style="text-align:center"><?= $s['credit_hours'] ?></td>
                    <td><span class="badge <?= $s['status']==='active'?'badge-green':'badge-gray' ?>"><?= $s['status']==='active'?'Aktif':'Tidak Aktif' ?></span></td>
                    <td style="display:flex;gap:6px">
                        <button type="button" class="btn btn-sm btn-primary" onclick='openEditSubject(<?= json_encode($s) ?>)'><i class="fas fa-pen"></i></button>
                        <form method="POST" onsubmit="return confirm('Padam subjek <?= htmlspecialchars($s['subject_code'],ENT_QUOTES) ?>?')">
                            <input type="hidden" name="action" value="delete_subject">
                            <input type="hidden" name="subject_id" value="<?= $s['subject_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else: ?><div class="empty"><div class="icon">📖</div><p>Tiada subjek lagi. Tambah subjek di atas.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- Edit Subject Modal -->
        <div class="modal-overlay" id="editSubjectModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><i class="fas fa-pen"></i> Edit Subjek</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editSubjectModal')"><i class="fas fa-xmark"></i></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_subject">
                    <input type="hidden" name="subject_id" id="es_subject_id">
                    <div class="modal-body">
                        <div class="form-grid-2">
                            <div class="form-group"><label>Kod Subjek *</label><input type="text" name="subject_code" id="es_code" required></div>
                            <div class="form-group"><label>Kursus *</label>
                                <select name="course_id" id="es_course" required>
                                    <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['course_code'].' - '.$c['course_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group full"><label>Nama Subjek *</label><input type="text" name="subject_name" id="es_name" required></div>
                            <div class="form-group">
                                <label>Semester *</label>
                                <select name="semester_no" id="es_sem" required>
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                    <option value="3">Semester 3</option>
                                    <option value="4">Semester 4</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Jam Kredit</label><input type="number" name="credit_hours" id="es_credit" min="1" max="6"></div>
                            <div class="form-group"><label>Status</label>
                                <select name="status" id="es_status">
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Tidak Aktif</option>
                                </select>
                            </div>
                            <div class="form-group full"><label>Penerangan</label><textarea name="description" id="es_desc"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-gray" onclick="closeModal('editSubjectModal')">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ CLASSES ══ -->
        <?php elseif ($tab === 'classes'): ?>
        <div class="card">
            <div class="card-header"><div><h3>➕ Tambah Section Baharu</h3><p>Kod section dijana automatik — contoh: <strong>CT204_01</strong>, <strong>CT206_02</strong></p></div></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_class">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Kursus *</label>
                            <select name="course_id" id="add_course_sel" required onchange="previewCode()">
                                <option value="">-- Pilih Kursus --</option>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['course_id'] ?>" data-code="<?= htmlspecialchars($c['course_code']) ?>"><?= htmlspecialchars($c['course_code'].' - '.$c['course_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>No. Section *</label>
                            <select name="section_num" id="add_section_num" required onchange="previewCode()">
                                <option value="">-- Pilih Section --</option>
                                <?php for ($n=1;$n<=10;$n++): ?>
                                <option value="<?= $n ?>">Section <?= str_pad($n,2,'0',STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Taraf Pendidikan *</label>
                            <select name="education_level" required>
                                <option value="">-- Pilih --</option>
                                <option value="asasi">Asasi (1 Tahun / 2 Sem)</option>
                                <option value="diploma">Diploma (2 Tahun / 4 Sem)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester_no" required>
                                <option value="">-- Pilih Semester --</option>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                                <option value="3">Semester 3 (Diploma)</option>
                                <option value="4">Semester 4 (Diploma)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Maks. Pelajar</label>
                            <input type="number" name="max_students" value="40" min="1">
                        </div>
                        <div class="form-group">
                            <label>Kod Section (Preview)</label>
                            <input type="text" id="code_preview" placeholder="Pilih kursus & section..." readonly
                                style="background:var(--gray-100);color:var(--blue-bright);font-weight:700;letter-spacing:1px">
                        </div>
                    </div>
                    <div style="margin-top:16px"><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Section</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3>🏫 Senarai Section / Kelas</h3><p>Semua section yang telah didaftarkan</p></div></div>
            <div class="table-wrap">
                <?php if ($classes): ?>
                <table>
                    <thead><tr><th>#</th><th>Kod Section</th><th>No. Section</th><th>Nama Kelas</th><th>Kursus</th><th>Taraf</th><th>Semester</th><th>Maks</th><th>Status</th><th>Tindakan</th></tr></thead>
                    <tbody>
                    <?php foreach ($classes as $i => $cl):
                        $edu = $cl['education_level'] ?? '';
                        $edu_label = match($edu) { 'asasi'=>'Asasi','diploma'=>'Diploma',default=>'—' };
                        $edu_badge = match($edu) { 'asasi'=>'badge-yellow','diploma'=>'badge-blue',default=>'badge-gray' };
                    ?>
                    <tr>
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td><span class="badge badge-blue" style="font-family:monospace;letter-spacing:1px"><?= htmlspecialchars($cl['class_code']) ?></span></td>
                        <td style="text-align:center"><?= $cl['section_num'] ? '<span class="badge badge-gray">Section '.str_pad($cl['section_num'],2,'0',STR_PAD_LEFT).'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td style="max-width:160px;font-size:12px"><?= htmlspecialchars($cl['class_name']) ?></td>
                        <td><span class="badge badge-gray"><?= htmlspecialchars($cl['course_code']) ?></span></td>
                        <td><span class="badge <?= $edu_badge ?>"><?= $edu_label ?></span></td>
                        <td><?= $cl['semester_no'] ? '<span class="badge badge-purple">Sem '.$cl['semester_no'].'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td style="text-align:center"><?= $cl['max_students'] ?></td>
                        <td><span class="badge <?= $cl['status']==='active'?'badge-green':'badge-gray' ?>"><?= $cl['status']==='active'?'Aktif':'Tidak Aktif' ?></span></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap">
                            <button type="button" class="btn btn-sm btn-primary" onclick='openEditClass(<?= json_encode($cl) ?>)'><i class="fas fa-pen"></i></button>
                            <form method="POST" onsubmit="return confirm('Padam section <?= htmlspecialchars($cl['class_code'],ENT_QUOTES) ?>? Semua assignment pelajar dalam section ini akan turut dipadam.')">
                                <input type="hidden" name="action" value="delete_class">
                                <input type="hidden" name="class_id" value="<?= $cl['class_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><div class="empty"><div class="icon">🏫</div><p>Tiada section lagi. Tambah section di atas.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- Edit Section Modal -->
        <div class="modal-overlay" id="editClassModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><i class="fas fa-pen"></i> Edit Section</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editClassModal')"><i class="fas fa-xmark"></i></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_class">
                    <input type="hidden" name="class_id" id="ecl_class_id">
                    <div class="modal-body">
                        <div style="background:var(--blue-pale);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--blue-mid)">
                            <i class="fas fa-circle-info" style="margin-right:6px"></i>Kod section akan dijana semula apabila kursus atau nombor section diubah.
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Kursus *</label>
                                <select name="course_id" id="ecl_course" required onchange="previewEditCode()">
                                    <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['course_id'] ?>" data-code="<?= htmlspecialchars($c['course_code']) ?>"><?= htmlspecialchars($c['course_code'].' - '.$c['course_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>No. Section *</label>
                                <select name="section_num" id="ecl_section_num" required onchange="previewEditCode()">
                                    <?php for ($n=1;$n<=10;$n++): ?>
                                    <option value="<?= $n ?>">Section <?= str_pad($n,2,'0',STR_PAD_LEFT) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Taraf Pendidikan *</label>
                                <select name="education_level" id="ecl_edu" required>
                                    <option value="asasi">Asasi</option>
                                    <option value="diploma">Diploma</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Semester *</label>
                                <select name="semester_no" id="ecl_sem" required>
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                    <option value="3">Semester 3</option>
                                    <option value="4">Semester 4</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Maks. Pelajar</label><input type="number" name="max_students" id="ecl_max" min="1"></div>
                            <div class="form-group"><label>Status</label>
                                <select name="status" id="ecl_status">
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Tidak Aktif</option>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label>Kod Section (Preview)</label>
                                <input type="text" id="edit_code_preview" readonly style="background:var(--gray-100);color:var(--blue-bright);font-weight:700;font-family:monospace">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-gray" onclick="closeModal('editClassModal')">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ PERMOHONAN KURSUS ══ -->
        <?php elseif ($tab === 'course_requests'): ?>
        <?php
        // Fetch all registrations with student + course info
        $all_regs = $conn->query("
            SELECT cr.reg_id, cr.status, cr.applied_at, cr.semester,
                   u.user_id, u.full_name, u.student_no, u.username, u.email,
                   u.education_level, u.current_semester,
                   c.course_id, c.course_code, c.course_name, c.education_level AS course_edu
            FROM course_registrations cr
            JOIN users   u ON cr.user_id   = u.user_id
            JOIN courses c ON cr.course_id = c.course_id
            ORDER BY FIELD(cr.status,'pending','approved','rejected','dropped'), cr.applied_at DESC
        ")->fetch_all(MYSQLI_ASSOC);

        $pending_list  = array_filter($all_regs, fn($r) => $r['status'] === 'pending');
        $approved_list = array_filter($all_regs, fn($r) => $r['status'] === 'approved');
        $rejected_list = array_filter($all_regs, fn($r) => $r['status'] === 'rejected');
        $total_pending_regs = count($pending_list);
        ?>

        <?php if ($total_pending_regs > 0): ?>
        <div style="background:linear-gradient(135deg,#fef9c3,#fef3c7);border:1px solid #fde68a;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:12px">
                <span style="font-size:26px">⏳</span>
                <div>
                    <div style="font-size:15px;font-weight:700;color:var(--yellow-700)"><?= $total_pending_regs ?> permohonan menunggu kelulusan</div>
                    <div style="font-size:13px;color:#92400e;margin-top:2px">Sila semak dan luluskan atau tolak permohonan pelajar.</div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="approve_all_regs">
                <button type="submit" class="btn btn-success" onclick="return confirm('Luluskan semua <?= $total_pending_regs ?> permohonan pending?')">
                    <i class="fas fa-check-double"></i> Lulus Semua Pending
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Filter tabs -->
        <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap">
            <button class="filter-tab active" id="reqTab_all"     onclick="filterReqs('all',this)">Semua (<?= count($all_regs) ?>)</button>
            <button class="filter-tab"        id="reqTab_pending"  onclick="filterReqs('pending',this)"  style="<?= $total_pending_regs>0?'background:var(--yellow-50);color:var(--yellow-700);border-color:#fde68a':'' ?>">⏳ Pending (<?= $total_pending_regs ?>)</button>
            <button class="filter-tab"        id="reqTab_approved" onclick="filterReqs('approved',this)">✅ Diluluskan (<?= count($approved_list) ?>)</button>
            <button class="filter-tab"        id="reqTab_rejected" onclick="filterReqs('rejected',this)">✗ Ditolak (<?= count($rejected_list) ?>)</button>
        </div>

        <!-- Search -->
        <div class="search-wrap" style="margin-bottom:16px">
            <i class="fas fa-search"></i>
            <input type="text" id="reqSearch" placeholder="Cari nama pelajar atau kod kursus..." oninput="searchReqs()">
        </div>

        <div class="card">
            <div class="card-header">
                <div><h3>📋 Senarai Permohonan Kursus</h3><p>Klik Lulus / Tolak untuk kemaskini status permohonan</p></div>
            </div>
            <div class="table-wrap">
                <?php if ($all_regs): ?>
                <table id="reqTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Pelajar</th>
                            <th>E-mel</th>
                            <th>Kursus</th>
                            <th>Taraf</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Tarikh Mohon</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($all_regs as $i => $r): ?>
                    <tr data-status="<?= $r['status'] ?>"
                        data-search="<?= strtolower($r['full_name'].' '.$r['username'].' '.$r['course_code'].' '.$r['course_name']) ?>">
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                            <?php if ($r['student_no']): ?>
                            <br><span class="badge badge-gray" style="margin-top:3px"><?= htmlspecialchars($r['student_no']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px"><?= htmlspecialchars($r['email']) ?></td>
                        <td>
                            <span class="badge badge-blue"><?= htmlspecialchars($r['course_code']) ?></span>
                            <br><small style="color:var(--gray-500);font-size:11px"><?= htmlspecialchars($r['course_name']) ?></small>
                        </td>
                        <td>
                            <?php $edu = $r['course_edu'] ?? 'both'; ?>
                            <span class="badge <?= $edu==='asasi'?'badge-yellow':($edu==='diploma'?'badge-blue':'badge-purple') ?>">
                                <?= match($edu) {'asasi'=>'Asasi','diploma'=>'Diploma',default=>'Kedua-dua'} ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['semester'] ?? '—') ?></td>
                        <td>
                            <?php
                            $status_map = [
                                'pending'  => ['label'=>'Pending',     'style'=>'background:var(--yellow-50);color:var(--yellow-700);border:1px solid #fde68a'],
                                'approved' => ['label'=>'Diluluskan',  'style'=>'background:var(--green-50);color:var(--green-700);border:1px solid #bbf7d0'],
                                'rejected' => ['label'=>'Ditolak',     'style'=>'background:var(--red-50);color:var(--red-700);border:1px solid #fecaca'],
                                'dropped'  => ['label'=>'Dibatalkan',  'style'=>'background:var(--gray-100);color:var(--gray-500)'],
                            ];
                            $sb = $status_map[$r['status']] ?? ['label'=>$r['status'],'style'=>''];
                            ?>
                            <span class="badge" style="<?= $sb['style'] ?>"><?= $sb['label'] ?></span>
                        </td>
                        <td style="font-size:11px;color:var(--gray-300)"><?= date('d M Y, h:i', strtotime($r['applied_at'])) ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                            <div style="display:flex;gap:6px">
                                <form method="POST" onsubmit="return confirm('Luluskan permohonan <?= htmlspecialchars($r['full_name'],ENT_QUOTES) ?> untuk kursus <?= htmlspecialchars($r['course_code'],ENT_QUOTES) ?>?')">
                                    <input type="hidden" name="action" value="approve_reg">
                                    <input type="hidden" name="reg_id" value="<?= $r['reg_id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Lulus
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Tolak permohonan <?= htmlspecialchars($r['full_name'],ENT_QUOTES) ?> untuk kursus <?= htmlspecialchars($r['course_code'],ENT_QUOTES) ?>?')">
                                    <input type="hidden" name="action" value="reject_reg">
                                    <input type="hidden" name="reg_id" value="<?= $r['reg_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-xmark"></i> Tolak
                                    </button>
                                </form>
                            </div>
                            <?php elseif ($r['status'] === 'approved'): ?>
                            <span style="font-size:12px;color:var(--green-700)"><i class="fas fa-circle-check"></i> Diluluskan</span>
                            <?php elseif ($r['status'] === 'rejected'): ?>
                            <span style="font-size:12px;color:var(--red-500)"><i class="fas fa-circle-xmark"></i> Ditolak</span>
                            <?php else: ?>
                            <span style="font-size:12px;color:var(--gray-300)">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty"><div class="icon">📋</div><p>Tiada permohonan kursus lagi.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══ SENARAI PELAJAR ══ -->
        <?php elseif ($tab === 'senarai_pelajar'): ?>
        <!-- Filter bar -->
        <div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center">
            <div class="search-wrap" style="flex:1;min-width:220px;margin-bottom:0">
                <i class="fas fa-search"></i>
                <input type="text" id="studentSearch" placeholder="Cari nama, emel, no. pelajar..." oninput="filterStudents()">
            </div>
            <select id="filterEdu" onchange="filterStudents()" style="padding:9px 13px;border:1.5px solid var(--gray-200);border-radius:9px;font-size:13px;font-family:inherit;color:var(--gray-700);background:var(--gray-50);outline:none;appearance:none;cursor:pointer">
                <option value="">Semua Taraf</option>
                <option value="asasi">Asasi</option>
                <option value="diploma">Diploma</option>
            </select>
            <select id="filterSem" onchange="filterStudents()" style="padding:9px 13px;border:1.5px solid var(--gray-200);border-radius:9px;font-size:13px;font-family:inherit;color:var(--gray-700);background:var(--gray-50);outline:none;appearance:none;cursor:pointer">
                <option value="">Semua Semester</option>
                <option value="1">Semester 1</option>
                <option value="2">Semester 2</option>
                <option value="3">Semester 3</option>
                <option value="4">Semester 4</option>
            </select>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h3>🎓 Senarai Semua Pelajar</h3><p>Maklumat lengkap pelajar berdaftar</p></div>
                <span class="badge badge-blue"><?= count($students_full) ?> pelajar</span>
            </div>
            <div class="table-wrap">
                <?php if ($students_full): ?>
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Penuh</th>
                            <th>Username</th>
                            <th>Tarikh Lahir</th>
                            <th>No. Telefon</th>
                            <th>E-mel</th>
                            <th>Fakulti</th>
                            <th>Kursus / Program</th>
                            <th>Taraf Pendidikan</th>
                            <th>Semester</th>
                            <th>Tahun Pengajian</th>
                            <th>Tahun Kemasukan</th>
                            <th>Kelas</th>
                            <th>Status</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students_full as $i => $s):
                        $edu     = $s['education_level'] ?? '';
                        $sem_no  = $s['current_semester'] ?? '';
                        $edu_label = match($edu) { 'asasi'=>'Asasi', 'diploma'=>'Diploma', default=>'—' };
                        $edu_badge = match($edu) { 'asasi'=>'badge-yellow', 'diploma'=>'badge-blue', default=>'badge-gray' };
                        // Max semester info
                        $sem_max = $edu === 'asasi' ? 2 : ($edu === 'diploma' ? 4 : '—');
                        $sem_display = $sem_no ? "Sem $sem_no" . ($sem_max !== '—' ? " / $sem_max" : '') : '—';
                    ?>
                    <tr data-edu="<?= $edu ?>" data-sem="<?= $sem_no ?>"
                        data-search="<?= strtolower($s['full_name'].' '.$s['email'].' '.($s['student_no']??'').' '.($s['faculty']??'')) ?>">
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($s['full_name']) ?></strong>
                            <?php if ($s['student_no']): ?>
                            <br><span class="badge badge-gray" style="margin-top:3px"><?= htmlspecialchars($s['student_no']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--gray-500)">@<?= htmlspecialchars($s['username']) ?></td>
                        <td><?= $s['date_of_birth'] ? date('d M Y', strtotime($s['date_of_birth'])) : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                        <td style="font-size:12px"><?= htmlspecialchars($s['email']) ?></td>
                        <td><?= htmlspecialchars($s['faculty'] ?? '—') ?></td>
                        <td style="max-width:160px;font-size:12px"><?= htmlspecialchars($s['course_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($edu): ?>
                            <span class="badge <?= $edu_badge ?>"><?= $edu_label ?></span>
                            <br><span style="font-size:10px;color:var(--gray-300)"><?= $edu==='asasi'?'1 Thn / 2 Sem':'2 Thn / 4 Sem' ?></span>
                            <?php else: ?>
                            <span style="color:var(--gray-300)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center">
                            <?php if ($sem_no): ?>
                            <span class="badge badge-purple"><?= $sem_display ?></span>
                            <?php else: ?>
                            <span style="color:var(--gray-300)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center"><?= $s['year_of_study'] ? 'Tahun '.$s['year_of_study'] : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td style="text-align:center"><?= htmlspecialchars($s['intake_year'] ?? '—') ?></td>
                        <td style="font-size:12px"><?= $s['kelas'] ? htmlspecialchars($s['kelas']) : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td><span class="badge <?= $s['status']==='active'?'badge-green':($s['status']==='pending'?'badge-yellow':'badge-red') ?>"><?= ucfirst($s['status']) ?></span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary"
                                onclick="openSetStudentNo(<?= $s['user_id'] ?>, '<?= htmlspecialchars($s['full_name'],ENT_QUOTES) ?>', '<?= htmlspecialchars($s['student_no'] ?? '',ENT_QUOTES) ?>')">
                                <i class="fas fa-id-card"></i> ID Pelajar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty"><div class="icon">🎓</div><p>Tiada pelajar berdaftar lagi.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Set Student ID Modal -->
        <div class="modal-overlay" id="setStudentNoModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><i class="fas fa-id-card"></i> Tetapkan No. ID Pelajar</h3>
                    <button type="button" class="modal-close" onclick="closeModal('setStudentNoModal')"><i class="fas fa-xmark"></i></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="set_student_no">
                    <input type="hidden" name="student_user_id" id="sid_user_id">
                    <div class="modal-body">
                        <div class="form-grid-2">
                            <div class="form-group full">
                                <label>Nama Pelajar</label>
                                <input type="text" id="sid_name" disabled style="background:var(--gray-100);color:var(--gray-500)">
                            </div>
                            <div class="form-group full">
                                <label>No. ID Pelajar *</label>
                                <input type="text" name="student_no" id="sid_input" placeholder="Cth: UPTM2024001" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-gray" onclick="closeModal('setStudentNoModal')">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ SENARAI PENSYARAH ══ -->
        <?php elseif ($tab === 'senarai_pensyarah'): ?>
        <div class="card">
            <div class="card-header">
                <div><h3>👨‍🏫 Senarai Semua Pensyarah</h3><p>Maklumat lengkap pensyarah berdaftar</p></div>
                <span class="badge badge-blue"><?= count($lecturers_full) ?> pensyarah</span>
            </div>
            <div class="table-wrap">
                <?php if ($lecturers_full): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Penuh</th>
                            <th>Username</th>
                            <th>No. Staf</th>
                            <th>Tarikh Lahir</th>
                            <th>No. Telefon</th>
                            <th>E-mel</th>
                            <th>Jabatan</th>
                            <th>Subjek Diajar</th>
                            <th>Jumlah Kelas</th>
                            <th>Status</th>
                            <th>Tarikh Daftar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lecturers_full as $i => $l): ?>
                    <tr>
                        <td style="color:var(--gray-300)"><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($l['full_name']) ?></strong></td>
                        <td style="font-size:12px;color:var(--gray-500)">@<?= htmlspecialchars($l['username']) ?></td>
                        <td><?= $l['staff_no'] ? '<span class="badge badge-gray">'.htmlspecialchars($l['staff_no']).'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td><?= $l['date_of_birth'] ? date('d M Y', strtotime($l['date_of_birth'])) : '<span style="color:var(--gray-300)">—</span>' ?></td>
                        <td><?= htmlspecialchars($l['phone'] ?? '—') ?></td>
                        <td style="font-size:12px"><?= htmlspecialchars($l['email']) ?></td>
                        <td><?= htmlspecialchars($l['department'] ?? '—') ?></td>
                        <td style="font-size:12px"><?= $l['subject_codes'] ? htmlspecialchars($l['subject_codes']) : '<span style="color:var(--gray-300)">Belum ditetapkan</span>' ?></td>
                        <td style="text-align:center"><span class="badge badge-purple"><?= $l['total_classes'] ?></span></td>
                        <td><span class="badge <?= $l['status']==='active'?'badge-green':($l['status']==='pending'?'badge-yellow':'badge-red') ?>"><?= ucfirst($l['status']) ?></span></td>
                        <td style="font-size:11px;color:var(--gray-300)"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty"><div class="icon">👨‍🏫</div><p>Tiada pensyarah berdaftar lagi.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══ ASSIGN STUDENT TO CLASS ══ -->
        <?php elseif ($tab === 'assign_student'): ?>
        <div class="two-col">
            <!-- Form -->
            <div class="card">
                <div class="card-header"><div><h3>👤 Masukkan Pelajar ke Kelas</h3><p>Pilih pelajar dan kelas yang sesuai</p></div></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="assign_student">
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Pilih Kelas *</label>
                            <select name="class_id" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($classes as $cl): ?>
                                <option value="<?= $cl['class_id'] ?>"><?= htmlspecialchars($cl['class_code'].' — '.$cl['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:16px">
                            <label>Pilih Pelajar *</label>
                            <select name="student_id" required>
                                <option value="">-- Pilih Pelajar --</option>
                                <?php foreach ($students as $s): ?>
                                <option value="<?= $s['user_id'] ?>"><?= htmlspecialchars($s['full_name'].' ('.($s['student_no'] ?: $s['username']).')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-user-plus"></i> Masukkan ke Kelas</button>
                    </form>
                </div>
            </div>
            <!-- Current assignments -->
            <div class="card">
                <div class="card-header"><div><h3>📋 Senarai Pelajar dalam Kelas</h3><p>Semua assignment pelajar</p></div></div>
                <?php
                $cs_list = $conn->query("
                    SELECT cs.cs_id, cs.assigned_at,
                           u.full_name, u.student_no, u.username,
                           cl.class_code, cl.class_name
                    FROM class_students cs
                    JOIN users u   ON cs.user_id  = u.user_id
                    JOIN classes cl ON cs.class_id = cl.class_id
                    ORDER BY cl.class_code, u.full_name
                ")->fetch_all(MYSQLI_ASSOC);
                ?>
                <?php if ($cs_list): ?>
                <div class="table-wrap"><table><thead><tr><th>Pelajar</th><th>No. Pelajar</th><th>Kelas</th><th>Tindakan</th></tr></thead><tbody>
                <?php foreach ($cs_list as $cs): ?>
                <tr>
                    <td><?= htmlspecialchars($cs['full_name']) ?></td>
                    <td><span class="badge badge-gray"><?= htmlspecialchars($cs['student_no'] ?: $cs['username']) ?></span></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($cs['class_code']) ?></span></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Keluarkan pelajar dari kelas ini?')">
                            <input type="hidden" name="action" value="remove_student">
                            <input type="hidden" name="cs_id" value="<?= $cs['cs_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-xmark"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php else: ?><div class="empty"><div class="icon">👥</div><p>Tiada pelajar diassign lagi.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- ══ ASSIGN SUBJECT TO STUDENT ══ -->
        <?php elseif ($tab === 'assign_subject'): ?>
        <div class="two-col">
            <div class="card">
                <div class="card-header"><div><h3>📖 Assign Subjek kepada Pelajar</h3><p>Tambah subjek untuk pelajar tertentu</p></div></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="assign_subject_student">
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Pilih Pelajar *</label>
                            <select name="student_id" required>
                                <option value="">-- Pilih Pelajar --</option>
                                <?php foreach ($students as $s): ?>
                                <option value="<?= $s['user_id'] ?>"><?= htmlspecialchars($s['full_name'].' ('.($s['student_no'] ?: $s['username']).')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Pilih Subjek *</label>
                            <select name="subject_id" required>
                                <option value="">-- Pilih Subjek --</option>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['subject_id'] ?>"><?= htmlspecialchars($s['subject_code'].' — '.$s['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:16px">
                            <label>Kelas (Optional)</label>
                            <select name="class_id">
                                <option value="">-- Pilih Kelas (jika ada) --</option>
                                <?php foreach ($classes as $cl): ?>
                                <option value="<?= $cl['class_id'] ?>"><?= htmlspecialchars($cl['class_code'].' — '.$cl['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> Assign Subjek</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div><h3>📋 Senarai Subjek Pelajar</h3><p>Semua subjek yang telah diassign</p></div></div>
                <?php
                $ss_list = $conn->query("
                    SELECT ss.ss_id, ss.assigned_at, ss.status,
                           u.full_name, u.student_no, u.username,
                           s.subject_code, s.subject_name,
                           cl.class_code
                    FROM student_subjects ss
                    JOIN users u    ON ss.user_id    = u.user_id
                    JOIN subjects s ON ss.subject_id = s.subject_id
                    LEFT JOIN classes cl ON ss.class_id = cl.class_id
                    ORDER BY u.full_name, s.subject_code
                ")->fetch_all(MYSQLI_ASSOC);
                ?>
                <?php if ($ss_list): ?>
                <div class="table-wrap"><table><thead><tr><th>Pelajar</th><th>Subjek</th><th>Kelas</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
                <?php foreach ($ss_list as $ss): ?>
                <tr>
                    <td><?= htmlspecialchars($ss['full_name']) ?><br><small style="color:var(--gray-300)"><?= htmlspecialchars($ss['student_no'] ?: $ss['username']) ?></small></td>
                    <td><span class="badge badge-purple"><?= htmlspecialchars($ss['subject_code']) ?></span><br><small><?= htmlspecialchars($ss['subject_name']) ?></small></td>
                    <td><?= $ss['class_code'] ? '<span class="badge badge-blue">'.htmlspecialchars($ss['class_code']).'</span>' : '—' ?></td>
                    <td><span class="badge <?= $ss['status']==='active'?'badge-green':'badge-red' ?>"><?= $ss['status']==='active'?'Aktif':'Dropped' ?></span></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Buang subjek pelajar ini?')">
                            <input type="hidden" name="action" value="remove_student_subject">
                            <input type="hidden" name="ss_id" value="<?= $ss['ss_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-xmark"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php else: ?><div class="empty"><div class="icon">📖</div><p>Tiada subjek diassign lagi.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- ══ ASSIGN LECTURER ══ -->
        <?php elseif ($tab === 'assign_lecturer'): ?>
        <div class="two-col">
            <div class="card">
                <div class="card-header"><div><h3>👨‍🏫 Assign Pensyarah ke Kelas</h3><p>Pilih pensyarah, kelas dan subjek yang diajar</p></div></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="assign_lecturer">
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Pilih Pensyarah *</label>
                            <select name="lecturer_id" required>
                                <option value="">-- Pilih Pensyarah --</option>
                                <?php foreach ($lecturers as $l): ?>
                                <option value="<?= $l['user_id'] ?>"><?= htmlspecialchars($l['full_name'].' ('.$l['username'].')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Pilih Kelas *</label>
                            <select name="class_id" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($classes as $cl): ?>
                                <option value="<?= $cl['class_id'] ?>"><?= htmlspecialchars($cl['class_code'].' — '.$cl['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:16px">
                            <label>Subjek yang Diajar (Optional)</label>
                            <select name="subject_id">
                                <option value="">-- Pilih Subjek --</option>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['subject_id'] ?>"><?= htmlspecialchars($s['subject_code'].' — '.$s['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-chalkboard-user"></i> Assign Pensyarah</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div><h3>📋 Senarai Pensyarah dalam Kelas</h3><p>Semua assignment pensyarah</p></div></div>
                <?php
                $cl_list = $conn->query("
                    SELECT cl.cl_id, cl.assigned_at,
                           u.full_name AS lecturer_name, u.username,
                           cls.class_code, cls.class_name,
                           s.subject_code, s.subject_name
                    FROM class_lecturers cl
                    JOIN users u    ON cl.lecturer_id = u.user_id
                    JOIN classes cls ON cl.class_id   = cls.class_id
                    LEFT JOIN subjects s ON cl.subject_id = s.subject_id
                    ORDER BY cls.class_code, u.full_name
                ")->fetch_all(MYSQLI_ASSOC);
                ?>
                <?php if ($cl_list): ?>
                <div class="table-wrap"><table><thead><tr><th>Pensyarah</th><th>Kelas</th><th>Subjek</th><th>Tindakan</th></tr></thead><tbody>
                <?php foreach ($cl_list as $cl): ?>
                <tr>
                    <td><?= htmlspecialchars($cl['lecturer_name']) ?><br><small style="color:var(--gray-300)"><?= htmlspecialchars($cl['username']) ?></small></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($cl['class_code']) ?></span><br><small><?= htmlspecialchars($cl['class_name']) ?></small></td>
                    <td><?= $cl['subject_code'] ? '<span class="badge badge-purple">'.htmlspecialchars($cl['subject_code']).'</span>' : '<span style="color:var(--gray-300)">—</span>' ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Keluarkan pensyarah dari kelas ini?')">
                            <input type="hidden" name="action" value="remove_lecturer">
                            <input type="hidden" name="cl_id" value="<?= $cl['cl_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-xmark"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php else: ?><div class="empty"><div class="icon">👨‍🏫</div><p>Tiada pensyarah diassign lagi.</p></div><?php endif; ?>
            </div>
        </div>

        <?php elseif ($tab === 'assign_lect_subject'): ?>
        <div class="two-col">
            <!-- Form -->
            <div class="card">
                <div class="card-header"><div><h3>🔗 Assign Subjek kepada Pensyarah</h3><p>Tetapkan subjek yang akan diajar oleh pensyarah</p></div></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="assign_lect_subject">
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Pilih Pensyarah *</label>
                            <select name="lecturer_id" required>
                                <option value="">-- Pilih Pensyarah --</option>
                                <?php foreach ($lecturers as $l): ?>
                                <option value="<?= $l['user_id'] ?>"><?= htmlspecialchars($l['full_name'].' ('.$l['username'].')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Pilih Subjek *</label>
                            <select name="subject_id" required>
                                <option value="">-- Pilih Subjek --</option>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['subject_id'] ?>"><?= htmlspecialchars($s['subject_code'].' — '.$s['subject_name'].' ('.$s['course_code'].')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Kelas / Section (Optional)</label>
                            <select name="class_id">
                                <option value="">-- Semua Kelas Berkaitan --</option>
                                <?php foreach ($classes as $cl): ?>
                                <option value="<?= $cl['class_id'] ?>"><?= htmlspecialchars($cl['class_code'].' — '.$cl['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="background:var(--blue-pale);border-radius:9px;padding:11px 14px;font-size:12px;color:var(--blue-mid);margin-bottom:16px">
                            <i class="fas fa-circle-info" style="margin-right:6px"></i>
                            Jika kelas tidak dipilih, subjek akan diassign kepada pensyarah untuk semua kelas yang berkaitan dengan subjek tersebut.
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                            <i class="fas fa-link"></i> Assign Subjek ke Pensyarah
                        </button>
                    </form>
                </div>
            </div>

            <!-- Senarai semasa -->
            <div class="card">
                <div class="card-header"><div><h3>📋 Senarai Assignment Pensyarah–Subjek</h3><p>Semua subjek yang telah diassign kepada pensyarah</p></div></div>
                <?php
                $lect_subj_list = $conn->query("
                    SELECT cl.cl_id,
                           u.full_name AS lecturer_name, u.username,
                           s.subject_code, s.subject_name,
                           c.course_code,
                           cls.class_code
                    FROM class_lecturers cl
                    JOIN users    u   ON cl.lecturer_id = u.user_id
                    JOIN subjects s   ON cl.subject_id  = s.subject_id
                    JOIN courses  c   ON s.course_id    = c.course_id
                    LEFT JOIN classes cls ON cl.class_id = cls.class_id
                    WHERE cl.subject_id IS NOT NULL
                    ORDER BY u.full_name, s.subject_code
                ")->fetch_all(MYSQLI_ASSOC);
                ?>
                <?php if ($lect_subj_list): ?>
                <div class="table-wrap">
                    <table><thead><tr><th>Pensyarah</th><th>Subjek</th><th>Kursus</th><th>Kelas</th><th>Tindakan</th></tr></thead><tbody>
                    <?php foreach ($lect_subj_list as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['lecturer_name']) ?></strong><br><small style="color:var(--gray-300)">@<?= htmlspecialchars($row['username']) ?></small></td>
                        <td><span class="badge badge-purple"><?= htmlspecialchars($row['subject_code']) ?></span><br><small style="font-size:11px;color:var(--gray-500)"><?= htmlspecialchars($row['subject_name']) ?></small></td>
                        <td><span class="badge badge-gray"><?= htmlspecialchars($row['course_code']) ?></span></td>
                        <td><?= $row['class_code'] ? '<span class="badge badge-blue" style="font-family:monospace">'.htmlspecialchars($row['class_code']).'</span>' : '<span style="color:var(--gray-300)">Semua Kelas</span>' ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Buang assignment subjek ini?')">
                                <input type="hidden" name="action" value="remove_lect_subject">
                                <input type="hidden" name="cl_id" value="<?= $row['cl_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-xmark"></i> Buang</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
                <?php else: ?><div class="empty"><div class="icon">🔗</div><p>Tiada assignment lagi.</p></div><?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
// ── Filter course requests ──────────────────────────
function filterReqs(status, el) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('#reqTable tbody tr').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}
function searchReqs() {
    const q = document.getElementById('reqSearch')?.value.toLowerCase() || '';
    document.querySelectorAll('#reqTable tbody tr').forEach(row => {
        row.style.display = row.dataset.search?.includes(q) ? '' : 'none';
    });
}

function filterStudents() {
    const q   = (document.getElementById('studentSearch')?.value || '').toLowerCase();
    const edu = document.getElementById('filterEdu')?.value || '';
    const sem = document.getElementById('filterSem')?.value || '';
    document.querySelectorAll('#studentTable tbody tr').forEach(row => {
        const matchQ   = !q   || row.dataset.search.includes(q);
        const matchEdu = !edu || row.dataset.edu === edu;
        const matchSem = !sem || row.dataset.sem === sem;
        row.style.display = (matchQ && matchEdu && matchSem) ? '' : 'none';
    });
}

// ── Toggle course checkbox card ────────────────────
function toggleCourse(el) {
    const cb = el.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
    el.classList.toggle('selected', cb.checked);
}

// ── Modal helpers ──────────────────────────────────
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

// Close modal when clicking outside the box
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('show');
    });
});

// ── Edit Course ────────────────────────────────────
function openEditCourse(c) {
    document.getElementById('ec_course_id').value = c.course_id;
    document.getElementById('ec_code').value       = c.course_code;
    document.getElementById('ec_name').value       = c.course_name;
    document.getElementById('ec_edu').value        = c.education_level || 'both';
    document.getElementById('ec_faculty').value    = c.faculty || '';
    document.getElementById('ec_max').value        = c.max_students;
    document.getElementById('ec_status').value     = c.status;
    document.getElementById('ec_desc').value       = c.description || '';
    openModal('editCourseModal');
}

// ── Edit Subject ───────────────────────────────────
function openEditSubject(s) {
    document.getElementById('es_subject_id').value = s.subject_id;
    document.getElementById('es_code').value        = s.subject_code;
    document.getElementById('es_name').value        = s.subject_name;
    document.getElementById('es_course').value      = s.course_id;
    document.getElementById('es_sem').value         = s.semester_no || '1';
    document.getElementById('es_credit').value      = s.credit_hours;
    document.getElementById('es_status').value      = s.status;
    document.getElementById('es_desc').value        = s.description || '';
    openModal('editSubjectModal');
}

// ── Edit Class / Section ───────────────────────────
function openEditClass(cl) {
    document.getElementById('ecl_class_id').value    = cl.class_id;
    document.getElementById('ecl_course').value      = cl.course_id;
    document.getElementById('ecl_edu').value         = cl.education_level || 'asasi';
    document.getElementById('ecl_sem').value         = cl.semester_no || '1';
    document.getElementById('ecl_max').value         = cl.max_students;
    document.getElementById('ecl_status').value      = cl.status;
    // Guna section_num dari DB terus
    document.getElementById('ecl_section_num').value = cl.section_num || 1;
    previewEditCode();
    openModal('editClassModal');
}

// ── Preview code for Add Section form ─────────────
function previewCode() {
    const sel = document.getElementById('add_course_sel');
    const secSel = document.getElementById('add_section_num');
    const preview = document.getElementById('code_preview');
    const courseCode = sel.options[sel.selectedIndex]?.dataset?.code || '';
    const secNum = parseInt(secSel.value) || 0;
    if (courseCode && secNum) {
        preview.value = courseCode + '_' + String(secNum).padStart(2,'0');
    } else {
        preview.value = '';
        preview.placeholder = 'Pilih kursus & section...';
    }
}

// ── Preview code for Edit Section modal ───────────
function previewEditCode() {
    const sel = document.getElementById('ecl_course');
    const secSel = document.getElementById('ecl_section_num');
    const preview = document.getElementById('edit_code_preview');
    const courseCode = sel.options[sel.selectedIndex]?.dataset?.code || '';
    const secNum = parseInt(secSel.value) || 0;
    if (courseCode && secNum) {
        preview.value = courseCode + '_' + String(secNum).padStart(2,'0');
    } else {
        preview.value = '';
    }
}

// ── Set Student ID ──────────────────────────────────
function openSetStudentNo(userId, name, currentNo) {
    document.getElementById('sid_user_id').value = userId;
    document.getElementById('sid_name').value     = name;
    document.getElementById('sid_input').value    = currentNo || '';
    openModal('setStudentNoModal');
}

// ── Validate Change Password Form ──────────────────
function validatePwForm() {
    const newPw = document.getElementById('staffNewPw').value;
    const confirmPw = document.getElementById('staffConfirmPw').value;
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
</script>
</body>
</html>