<?php
// =====================================================
// login_page.php — Log Masuk
// Student Registration System — UPTM
// =====================================================
session_start();
require_once 'db_connect.php';
redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Sila isi semua maklumat yang diperlukan.';
    } else {
        // Single query — find user by username or email
        $stmt = $conn->prepare("SELECT user_id, full_name, password, role, status FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (!password_verify($password, $user['password'])) {
                $error = 'Kata laluan salah. Sila cuba lagi.';
            } elseif ($user['status'] === 'pending') {
                $error = 'Akaun anda sedang menunggu kelulusan pentadbir. Sila tunggu pengesahan melalui e-mel.';
            } elseif ($user['status'] === 'inactive') {
                $error = 'Akaun anda telah dinyahaktifkan. Sila hubungi pihak pentadbir.';
            } else {
                // ✅ Login success — set session
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                // Log it
                $ip  = $_SERVER['REMOTE_ADDR'];
                $log = $conn->prepare("INSERT INTO login_logs (user_id, role, ip_address, status) VALUES (?, ?, ?, 'success')");
                $log->bind_param("iss", $user['user_id'], $user['role'], $ip);
                $log->execute();

                // Redirect based on role
                $destination = match($user['role']) {
                    'student'  => 'student_dashboard.php',
                    'staff'    => 'staff_dashboard.php',
                    'lecturer' => 'lect_dashboard.php',
                    'admin'    => 'admin_dashboard.php',
                    default    => 'login_page.php',
                };
                header("Location: " . $destination);
                exit();
            }
        } else {
            $error = 'Username atau e-mel tidak dijumpai dalam sistem.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk — Sistem Pendaftaran Pelajar UPTM</title>
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
        body { font-family:'Inter',sans-serif; min-height:100vh; display:flex; background:var(--gray-50); overflow:hidden; }

        /* LEFT PANEL */
        .left-panel {
            flex:1;
            background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-mid) 50%, #1e40af 100%);
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            padding:60px 50px; position:relative; overflow:hidden;
        }
        .left-panel::before {
            content:''; position:absolute; width:420px; height:420px; border-radius:50%;
            background:rgba(255,255,255,0.05); top:-110px; right:-110px;
        }
        .left-panel::after {
            content:''; position:absolute; width:280px; height:280px; border-radius:50%;
            background:rgba(255,255,255,0.04); bottom:-70px; left:-70px;
        }

        .brand-badge {
            display:flex; align-items:center; gap:10px;
            background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2);
            border-radius:50px; padding:10px 20px; margin-bottom:44px;
            backdrop-filter:blur(10px);
        }
        .brand-badge span { color:var(--white); font-size:13px; font-weight:500; opacity:.9; }

        .hero-icon {
            width:88px; height:88px; background:rgba(255,255,255,0.14);
            border-radius:22px; display:flex; align-items:center; justify-content:center;
            font-size:40px; margin-bottom:28px; border:1px solid rgba(255,255,255,0.2);
        }
        .left-panel h1 { color:var(--white); font-size:30px; font-weight:700; text-align:center; line-height:1.25; margin-bottom:14px; }
        .left-panel p  { color:rgba(255,255,255,0.7); font-size:14px; text-align:center; line-height:1.65; max-width:320px; }

        /* Role badges on left */
        .role-badges { display:flex; gap:10px; margin-top:36px; flex-wrap:wrap; justify-content:center; }
        .role-badge {
            display:flex; align-items:center; gap:8px;
            background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.18);
            border-radius:10px; padding:10px 16px;
        }
        .role-badge .icon { font-size:18px; }
        .role-badge span { color:rgba(255,255,255,0.85); font-size:13px; font-weight:500; }

        /* Campus SVG */
        .campus { margin-top:40px; opacity:.55; position:relative; z-index:1; }

        /* RIGHT PANEL */
        .right-panel {
            width:470px; display:flex; flex-direction:column;
            justify-content:center; padding:60px 50px;
            background:var(--white); overflow-y:auto;
        }

        .portal-tag {
            display:inline-flex; align-items:center; gap:8px;
            background:var(--blue-pale); border:1px solid #bfdbfe;
            border-radius:8px; padding:7px 14px; margin-bottom:30px;
            font-size:11px; font-weight:700; color:var(--blue-bright);
            text-transform:uppercase; letter-spacing:0.6px;
        }
        .right-panel h2 { font-size:27px; font-weight:700; color:var(--gray-700); margin-bottom:5px; }
        .subtitle { color:var(--gray-500); font-size:14px; margin-bottom:30px; }

        .alert {
            display:flex; align-items:flex-start; gap:10px;
            padding:13px 16px; border-radius:10px; font-size:14px;
            margin-bottom:20px; font-weight:500; line-height:1.5;
        }
        .alert-error { background:#fef2f2; color:var(--red-500); border:1px solid #fecaca; }

        .form-group { margin-bottom:18px; }
        .form-group label {
            display:block; font-size:11px; font-weight:700;
            color:var(--gray-500); text-transform:uppercase;
            letter-spacing:0.7px; margin-bottom:7px;
        }
        .input-wrap { position:relative; display:flex; align-items:center; }
        .input-wrap .ico { position:absolute; left:13px; color:var(--gray-300); font-size:14px; pointer-events:none; }
        .input-wrap input {
            width:100%; padding:13px 13px 13px 40px;
            border:1.5px solid var(--gray-300); border-radius:10px;
            font-size:15px; font-family:'Inter',sans-serif;
            color:var(--gray-700); background:var(--gray-50);
            transition:border-color .2s,box-shadow .2s,background .2s;
            outline:none;
        }
        .input-wrap input:focus {
            border-color:var(--blue-bright); background:var(--white);
            box-shadow:0 0 0 3px rgba(37,99,235,0.1);
        }
        .toggle-pw {
            position:absolute; right:13px; background:none; border:none;
            color:var(--gray-300); cursor:pointer; font-size:14px; transition:color .2s;
        }
        .toggle-pw:hover { color:var(--blue-bright); }

        .btn-login {
            width:100%; padding:14px; margin-top:8px;
            background:linear-gradient(135deg, var(--blue-bright), var(--blue-light));
            color:var(--white); border:none; border-radius:10px;
            font-size:15px; font-weight:600; font-family:'Inter',sans-serif;
            cursor:pointer; transition:opacity .2s,transform .1s,box-shadow .2s;
            box-shadow:0 4px 15px rgba(37,99,235,0.35);
        }
        .btn-login:hover { opacity:.92; transform:translateY(-1px); }
        .btn-login:active { transform:translateY(0); }

        .form-footer { text-align:center; margin-top:18px; }
        .form-footer a { color:var(--blue-bright); text-decoration:none; font-size:13px; font-weight:500; }
        .form-footer a:hover { text-decoration:underline; }

        .divider { display:flex; align-items:center; gap:12px; margin:22px 0; color:var(--gray-300); font-size:13px; }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--gray-100); }

        .btn-signup {
            width:100%; padding:13px; background:transparent;
            color:var(--blue-bright); border:1.5px solid var(--blue-pale);
            border-radius:10px; font-size:14px; font-weight:600;
            font-family:'Inter',sans-serif; cursor:pointer;
            transition:background .2s,border-color .2s;
            text-decoration:none; display:block; text-align:center;
        }
        .btn-signup:hover { background:var(--blue-pale); border-color:#bfdbfe; }

        @media (max-width:900px) {
            .left-panel { display:none; }
            .right-panel { width:100%; padding:40px 24px; }
        }
    </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
    <div class="brand-badge">
        <span>🏛️</span>
        <span>Universiti Poly-Tech Malaysia (UPTM)</span>
    </div>

    <div class="hero-icon">🎓</div>
    <h1>Student Registration<br>System</h1>
    <p>Uruskan perjalanan akademik anda dengan mudah melalui portal universiti bersepadu kami.</p>

    <div class="role-badges">
        <div class="role-badge"><span class="icon">🎓</span><span>Pelajar</span></div>
        <div class="role-badge"><span class="icon">💼</span><span>Staf</span></div>
        <div class="role-badge"><span class="icon">📚</span><span>Pensyarah</span></div>
    </div>

    <div class="campus">
        <svg width="190" height="110" viewBox="0 0 190 110" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="55" y="28" width="80" height="70" rx="3" fill="rgba(255,255,255,0.15)"/>
            <rect x="67" y="40" width="15" height="15" rx="2" fill="rgba(255,255,255,0.25)"/>
            <rect x="90" y="40" width="15" height="15" rx="2" fill="rgba(255,255,255,0.25)"/>
            <rect x="113" y="40" width="15" height="15" rx="2" fill="rgba(255,255,255,0.25)"/>
            <rect x="67" y="63" width="15" height="15" rx="2" fill="rgba(255,255,255,0.25)"/>
            <rect x="90" y="63" width="15" height="15" rx="2" fill="rgba(255,255,255,0.25)"/>
            <rect x="113" y="63" width="15" height="15" rx="2" fill="rgba(255,255,255,0.25)"/>
            <rect x="84" y="87" width="22" height="11" rx="2" fill="rgba(255,255,255,0.2)"/>
            <rect x="88" y="10" width="3" height="20" fill="rgba(255,255,255,0.4)"/>
            <polygon points="86,10 92,10 89,4" fill="rgba(255,255,255,0.5)"/>
            <line x1="10" y1="98" x2="180" y2="98" stroke="rgba(255,255,255,0.2)" stroke-width="2"/>
        </svg>
    </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">

    <div class="portal-tag">🏛️ Academic Portal v3.2</div>

    <h2>Selamat kembali!</h2>
    <p class="subtitle">Log masuk untuk meneruskan ke portal anda.</p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-circle-exclamation" style="margin-top:2px;flex-shrink:0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="form-group">
            <label>Username / E-mel</label>
            <div class="input-wrap">
                <i class="fas fa-user ico"></i>
                <input type="text" name="username"
                    placeholder="Masukkan username atau e-mel anda"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    autocomplete="username" required>
            </div>
        </div>

        <div class="form-group">
            <label>Kata Laluan</label>
            <div class="input-wrap">
                <i class="fas fa-lock ico"></i>
                <input type="password" name="password" id="pwField"
                    placeholder="Masukkan kata laluan anda"
                    autocomplete="current-password" required>
                <button type="button" class="toggle-pw" onclick="togglePw()">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="fas fa-arrow-right-to-bracket" style="margin-right:8px"></i>
            Log Masuk ke Portal
        </button>

    </form>

    <div class="form-footer">
        <a href="forgot_password.php">Lupa kata laluan?</a>
    </div>

    <div class="divider">atau</div>

    <a href="signup_page.php" class="btn-signup">
        <i class="fas fa-user-plus" style="margin-right:8px"></i>
        Daftar Akaun Baharu
    </a>

</div>

<script>
function togglePw() {
    const f = document.getElementById('pwField');
    const i = document.getElementById('eyeIcon');
    f.type = (f.type === 'password') ? 'text' : 'password';
    i.className = (f.type === 'password') ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>