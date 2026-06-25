<?php
// =====================================================
// signup_page.php — Daftar Akaun Baharu
// Student Registration System — UPTM
// =====================================================
session_start();
require_once 'db_connect.php';
redirectIfLoggedIn();

$error   = '';
$success = '';
$data    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name'     => trim($_POST['full_name'] ?? ''),
        'username'      => trim($_POST['username']  ?? ''),
        'email'         => trim($_POST['email']     ?? ''),
        'phone'         => trim($_POST['phone']     ?? ''),
        'date_of_birth' => $_POST['date_of_birth']  ?? '',
        'role'          => $_POST['role']           ?? 'student',
        'password'      => $_POST['password']       ?? '',
        'confirm'       => $_POST['confirm']        ?? '',
    ];

    $allowed_roles = ['student', 'staff', 'lecturer'];

    if (empty($data['full_name']) || empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        $error = 'Sila isi semua medan yang wajib diisi (*).';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Format e-mel tidak sah.';
    } elseif (!in_array($data['role'], $allowed_roles)) {
        $error = 'Pilihan peranan tidak sah.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $data['username'])) {
        $error = 'Username mesti 4–30 aksara dan hanya boleh mengandungi huruf, nombor, dan "_".';
    } elseif (strlen($data['password']) < 8) {
        $error = 'Kata laluan mesti sekurang-kurangnya 8 aksara.';
    } elseif ($data['password'] !== $data['confirm']) {
        $error = 'Kata laluan tidak sepadan. Sila cuba semula.';
    } else {
        // Check duplicate username or email
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $data['username'], $data['email']);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Username atau e-mel ini sudah didaftarkan. Sila guna yang lain.';
        } else {
            $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
            $dob = $data['date_of_birth'] ?: null;
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, phone, date_of_birth, role, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssssss", $data['full_name'], $data['username'], $data['email'], $data['phone'], $dob, $data['role'], $hashed);

            if ($stmt->execute()) {
                // Auto login terus selepas sign up
                $new_user_id = $conn->insert_id;
                $_SESSION['user_id']   = $new_user_id;
                $_SESSION['full_name'] = $data['full_name'];
                $_SESSION['role']      = $data['role'];

                // Redirect ke dashboard berdasarkan role
                $dest = match($data['role']) {
                    'student'  => 'student_dashboard.php',
                    'staff'    => 'staff_dashboard.php',
                    'lecturer' => 'lect_dashboard.php',
                    'admin'    => 'admin_dashboard.php',
                    default    => 'login_page.php',
                };
                header("Location: " . $dest);
                exit();
            } else {
                $error = 'Ralat semasa pendaftaran. Sila cuba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akaun — Sistem Pendaftaran Pelajar UPTM</title>
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
            --gray-300:    #cbd5e1;
            --gray-500:    #64748b;
            --gray-700:    #334155;
            --red-500:     #ef4444;
            --green-500:   #22c55e;
        }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; background: var(--gray-50); }

        /* LEFT PANEL */
        .left-panel {
            width: 380px; flex-shrink: 0;
            background: linear-gradient(155deg, var(--blue-deep) 0%, var(--blue-mid) 55%, #1e40af 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 60px 40px; position: relative; overflow: hidden;
        }
        .left-panel::before {
            content: ''; position: absolute;
            width: 350px; height: 350px; border-radius: 50%;
            background: rgba(255,255,255,0.05); top: -100px; right: -100px;
        }
        .left-panel::after {
            content: ''; position: absolute;
            width: 250px; height: 250px; border-radius: 50%;
            background: rgba(255,255,255,0.04); bottom: -60px; left: -60px;
        }
        .brand-logo { font-size: 52px; margin-bottom: 22px; }
        .left-panel h1 { color: var(--white); font-size: 24px; font-weight: 700; text-align: center; line-height: 1.3; margin-bottom: 10px; }
        .left-panel p  { color: rgba(255,255,255,0.65); font-size: 13px; text-align: center; line-height: 1.7; }

        .step-list { width: 100%; margin-top: 36px; }
        .step-item { display: flex; align-items: flex-start; gap: 13px; margin-bottom: 20px; }
        .step-num {
            width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
            background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.28);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: var(--white);
        }
        .step-text { color: rgba(255,255,255,0.78); font-size: 13px; line-height: 1.5; padding-top: 5px; }
        .step-text strong { color: var(--white); display: block; font-weight: 600; margin-bottom: 1px; }

        .info-box {
            margin-top: 32px; width: 100%;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14);
            border-radius: 12px; padding: 16px;
        }
        .info-box p { color: rgba(255,255,255,0.75); font-size: 12px; line-height: 1.7; text-align: left; }
        .info-box strong { color: rgba(255,255,255,0.95); font-size: 12px; }

        /* RIGHT PANEL */
        .right-panel { flex: 1; overflow-y: auto; display: flex; flex-direction: column; justify-content: center; padding: 50px 60px; background: var(--white); }

        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
        .portal-tag {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--blue-pale); border: 1px solid #bfdbfe;
            border-radius: 8px; padding: 7px 14px;
            font-size: 11px; font-weight: 700; color: var(--blue-bright);
            text-transform: uppercase; letter-spacing: 0.6px;
        }
        .login-link { font-size: 13px; color: var(--gray-500); }
        .login-link a { color: var(--blue-bright); font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        h2 { font-size: 26px; font-weight: 700; color: var(--gray-700); margin-bottom: 4px; }
        .subtitle { color: var(--gray-500); font-size: 14px; margin-bottom: 28px; }

        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 13px 16px; border-radius: 10px; font-size: 14px;
            margin-bottom: 22px; font-weight: 500; line-height: 1.5;
        }
        .alert-error   { background: #fef2f2; color: var(--red-500);   border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #15803d;          border: 1px solid #bbf7d0; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 20px; }
        .full { grid-column: 1 / -1; }

        .form-group label {
            display: block; font-size: 11px; font-weight: 700;
            color: var(--gray-500); text-transform: uppercase;
            letter-spacing: 0.7px; margin-bottom: 7px;
        }
        .req { color: var(--red-500); margin-left: 2px; }

        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-wrap .ico { position: absolute; left: 13px; color: var(--gray-300); font-size: 14px; pointer-events: none; }
        .input-wrap input,
        .input-wrap select {
            width: 100%; padding: 12px 13px 12px 40px;
            border: 1.5px solid var(--gray-300); border-radius: 10px;
            font-size: 14px; font-family: 'Inter', sans-serif;
            color: var(--gray-700); background: var(--gray-50);
            transition: border-color .2s, box-shadow .2s, background .2s;
            outline: none; appearance: none; -webkit-appearance: none;
        }
        .input-wrap input:focus,
        .input-wrap select:focus {
            border-color: var(--blue-bright); background: var(--white);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .select-arrow { position: absolute; right: 13px; color: var(--gray-300); font-size: 12px; pointer-events: none; }

        /* Role cards */
        .role-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .role-card { position: relative; }
        .role-card input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .role-card label {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 14px 10px; border: 2px solid var(--gray-300); border-radius: 12px;
            cursor: pointer; transition: all .2s; background: var(--gray-50);
            text-transform: none; letter-spacing: 0; font-size: 13px; font-weight: 600;
            color: var(--gray-500);
        }
        .role-card label .role-icon { font-size: 24px; }
        .role-card input:checked + label {
            border-color: var(--blue-bright); background: var(--blue-pale);
            color: var(--blue-bright); box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .role-card label:hover { border-color: var(--blue-light); background: #eff6ff; }

        /* Toggle PW */
        .toggle-pw {
            position: absolute; right: 13px;
            background: none; border: none;
            color: var(--gray-300); cursor: pointer;
            font-size: 14px; transition: color .2s;
        }
        .toggle-pw:hover { color: var(--blue-bright); }

        /* PW Strength */
        .pw-strength { margin-top: 6px; }
        .pw-bar { height: 4px; border-radius: 2px; background: var(--gray-100); overflow: hidden; margin-bottom: 4px; }
        .pw-fill { height: 100%; width: 0%; transition: width .3s, background .3s; border-radius: 2px; }
        .pw-label { font-size: 11px; color: var(--gray-500); }

        .btn-register {
            width: 100%; padding: 14px; margin-top: 28px;
            background: linear-gradient(135deg, var(--blue-bright), var(--blue-light));
            color: var(--white); border: none; border-radius: 10px;
            font-size: 15px; font-weight: 600; font-family: 'Inter', sans-serif;
            cursor: pointer; transition: opacity .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 15px rgba(37,99,235,0.35);
        }
        .btn-register:hover { opacity: .92; transform: translateY(-1px); }
        .btn-register:active { transform: translateY(0); }

        .terms-note { text-align: center; font-size: 12px; color: var(--gray-500); margin-top: 14px; }
        .terms-note a { color: var(--blue-bright); text-decoration: none; }

        @media (max-width: 900px) {
            .left-panel { display: none; }
            .right-panel { padding: 30px 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .full { grid-column: 1; }
        }
    </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
    <div class="brand-logo">🎓</div>
    <h1>Daftar Akaun<br>Baharu</h1>
    <p>Isi maklumat asas untuk mula menggunakan portal akademik UPTM.</p>

    <div class="step-list">
        <div class="step-item">
            <div class="step-num">1</div>
            <div class="step-text">
                <strong>Maklumat Peribadi</strong>
                Nama, emel & no. telefon
            </div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div class="step-text">
                <strong>Pilih Peranan</strong>
                Student, Staf atau Pensyarah
            </div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div class="step-text">
                <strong>Tetapkan Kata Laluan</strong>
                Sekurang-kurangnya 8 aksara
            </div>
        </div>
    </div>

    <div class="info-box">
        <strong>ℹ️ Info Penting</strong>
        <p>Selepas mendaftar, akaun anda perlu <strong>diluluskan oleh pentadbir</strong> sebelum boleh log masuk. Maklumat akademik seperti No. Pelajar, Fakulti dan Program akan dikemaskini oleh pihak pentadbir.</p>
    </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">

    <div class="top-bar">
        <div class="portal-tag">🏛️ Academic Portal v3.2</div>
        <div class="login-link">Sudah ada akaun? <a href="login_page.php">Log Masuk</a></div>
    </div>

    <h2>Buat Akaun Baharu</h2>
    <p class="subtitle">Daftar untuk mengakses sistem pendaftaran UPTM.</p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-circle-exclamation" style="margin-top:2px;flex-shrink:0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check" style="margin-top:2px;flex-shrink:0"></i>
            <span><?= htmlspecialchars($success) ?> <a href="login_page.php" style="color:#15803d;font-weight:700;">Log masuk →</a></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-grid">

            <!-- Full Name -->
            <div class="form-group full">
                <label>Nama Penuh <span class="req">*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-user ico"></i>
                    <input type="text" name="full_name"
                        placeholder="Contoh: Ahmad Hakimi bin Abdullah"
                        value="<?= htmlspecialchars($data['full_name'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Username -->
            <div class="form-group">
                <label>Username <span class="req">*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-at ico"></i>
                    <input type="text" name="username"
                        placeholder="Contoh: hakimi01"
                        value="<?= htmlspecialchars($data['username'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Phone -->
            <div class="form-group">
                <label>No. Telefon</label>
                <div class="input-wrap">
                    <i class="fas fa-phone ico"></i>
                    <input type="tel" name="phone"
                        placeholder="Contoh: 0123456789"
                        value="<?= htmlspecialchars($data['phone'] ?? '') ?>">
                </div>
            </div>

            <!-- Date of Birth -->
            <div class="form-group">
                <label>Tarikh Lahir</label>
                <div class="input-wrap">
                    <i class="fas fa-cake-candles ico"></i>
                    <input type="date" name="date_of_birth"
                        value="<?= htmlspecialchars($data['date_of_birth'] ?? '') ?>">
                </div>
            </div>

            <!-- Email -->
            <div class="form-group full">
                <label>Alamat E-mel <span class="req">*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-envelope ico"></i>
                    <input type="email" name="email"
                        placeholder="Contoh: hakimi@student.uptm.edu.my"
                        value="<?= htmlspecialchars($data['email'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Role cards -->
            <div class="form-group full">
                <label>Peranan <span class="req">*</span></label>
                <div class="role-cards">
                    <div class="role-card">
                        <input type="radio" name="role" id="role_student" value="student"
                            <?= (!isset($data['role']) || $data['role'] === 'student') ? 'checked' : '' ?>>
                        <label for="role_student">
                            <span class="role-icon">🎓</span>
                            Pelajar
                        </label>
                    </div>
                    <div class="role-card">
                        <input type="radio" name="role" id="role_staff" value="staff"
                            <?= (($data['role'] ?? '') === 'staff') ? 'checked' : '' ?>>
                        <label for="role_staff">
                            <span class="role-icon">💼</span>
                            Staf
                        </label>
                    </div>
                    <div class="role-card">
                        <input type="radio" name="role" id="role_lecturer" value="lecturer"
                            <?= (($data['role'] ?? '') === 'lecturer') ? 'checked' : '' ?>>
                        <label for="role_lecturer">
                            <span class="role-icon">📚</span>
                            Pensyarah
                        </label>
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label>Kata Laluan <span class="req">*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-lock ico"></i>
                    <input type="password" name="password" id="pw1"
                        placeholder="Min. 8 aksara" required
                        oninput="checkStrength(this.value)">
                    <button type="button" class="toggle-pw" onclick="togglePw('pw1','eye1')">
                        <i class="fas fa-eye" id="eye1"></i>
                    </button>
                </div>
                <div class="pw-strength">
                    <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
                    <span class="pw-label" id="pwLabel">Masukkan kata laluan</span>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label>Sahkan Kata Laluan <span class="req">*</span></label>
                <div class="input-wrap">
                    <i class="fas fa-lock ico"></i>
                    <input type="password" name="confirm" id="pw2"
                        placeholder="Ulang kata laluan" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('pw2','eye2')">
                        <i class="fas fa-eye" id="eye2"></i>
                    </button>
                </div>
            </div>

        </div><!-- /form-grid -->

        <button type="submit" class="btn-register">
            <i class="fas fa-user-plus" style="margin-right:8px"></i>
            Daftar Sekarang
        </button>
    </form>

    <p class="terms-note">
        Dengan mendaftar, anda bersetuju dengan <a href="#">Terma Penggunaan</a> dan <a href="#">Dasar Privasi</a> UPTM.
    </p>

</div>

<script>
function togglePw(id, iconId) {
    const f = document.getElementById(id);
    const i = document.getElementById(iconId);
    f.type = (f.type === 'password') ? 'text' : 'password';
    i.className = (f.type === 'password') ? 'fas fa-eye' : 'fas fa-eye-slash';
}

function checkStrength(pw) {
    const fill  = document.getElementById('pwFill');
    const label = document.getElementById('pwLabel');
    let score = 0;
    if (pw.length >= 8)            score++;
    if (/[A-Z]/.test(pw))          score++;
    if (/[0-9]/.test(pw))          score++;
    if (/[^A-Za-z0-9]/.test(pw))   score++;
    const levels = [
        { pct:'0%',   bg:'#e5e7eb', txt:'Masukkan kata laluan',  clr:'#64748b' },
        { pct:'25%',  bg:'#ef4444', txt:'Lemah',                  clr:'#ef4444' },
        { pct:'50%',  bg:'#f97316', txt:'Sederhana',              clr:'#f97316' },
        { pct:'75%',  bg:'#eab308', txt:'Kuat',                   clr:'#eab308' },
        { pct:'100%', bg:'#22c55e', txt:'Sangat Kuat ✓',          clr:'#22c55e' },
    ];
    const lvl = pw.length === 0 ? levels[0] : levels[score];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.bg;
    label.textContent     = lvl.txt;
    label.style.color     = lvl.clr;
}
</script>
</body>
</html>