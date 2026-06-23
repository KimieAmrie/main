<?php
// =====================================================
// forgot_password.php — Tukar Kata Laluan
// Student Registration System — UPTM
// =====================================================
session_start();
require_once 'db_connect.php';

// Already logged in? Redirect
if (isLoggedIn()) { redirectIfLoggedIn(); }

$step  = 1;    // 1=cari akaun, 2=tetapkan password baru
$error = '';
$success = '';
$found_user = null;

// ── STEP 1: Verify identity ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === '1') {
    $username = trim($_POST['username']);
    $dob      = $_POST['date_of_birth'] ?? '';

    if (empty($username) || empty($dob)) {
        $error = 'Sila isi username/e-mel dan tarikh lahir.';
        $step  = 1;
    } else {
        $stmt = $conn->prepare("SELECT user_id, full_name, username, email, date_of_birth, role FROM users WHERE (username=? OR email=?) AND status='active'");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $error = 'Akaun tidak dijumpai atau akaun belum aktif.';
            $step  = 1;
        } elseif (!$user['date_of_birth']) {
            $error = 'Tarikh lahir belum didaftarkan untuk akaun ini. Sila hubungi pentadbir.';
            $step  = 1;
        } elseif ($user['date_of_birth'] !== $dob) {
            $error = 'Tarikh lahir tidak sepadan dengan rekod kami.';
            $step  = 1;
        } else {
            // Identity verified — move to step 2
            $_SESSION['reset_user_id'] = $user['user_id'];
            $_SESSION['reset_name']    = $user['full_name'];
            $step = 2;
            $found_user = $user;
        }
    }
}

// ── STEP 2: Set new password ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === '2') {
    $new_pw  = $_POST['new_password']     ?? '';
    $conf_pw = $_POST['confirm_password'] ?? '';
    $uid     = intval($_SESSION['reset_user_id'] ?? 0);

    if (!$uid) {
        $error = 'Sesi tamat. Sila mula semula.'; $step = 1;
    } elseif (strlen($new_pw) < 8) {
        $error = 'Kata laluan mesti sekurang-kurangnya 8 aksara.'; $step = 2;
    } elseif ($new_pw !== $conf_pw) {
        $error = 'Kata laluan tidak sepadan.'; $step = 2;
    } else {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
        $upd->bind_param("si", $hashed, $uid);
        if ($upd->execute()) {
            unset($_SESSION['reset_user_id'], $_SESSION['reset_name']);
            $success = 'Kata laluan berjaya ditukar! Anda boleh log masuk sekarang.';
            $step = 3;
        } else {
            $error = 'Ralat semasa menukar kata laluan. Sila cuba lagi.'; $step = 2;
        }
    }
}

// If back to step 2 after form submit
if ($step === 2 && isset($_SESSION['reset_user_id'])) {
    $uid = intval($_SESSION['reset_user_id']);
    $s = $conn->prepare("SELECT full_name, username FROM users WHERE user_id=?");
    $s->bind_param("i", $uid);
    $s->execute();
    $found_user = $s->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tukar Kata Laluan — UPTM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
        :root {
            --blue-deep:#0f2d6e; --blue-mid:#1a4db8; --blue-bright:#2563eb;
            --blue-light:#3b82f6; --blue-pale:#dbeafe;
            --white:#ffffff; --gray-50:#f8fafc; --gray-100:#f1f5f9;
            --gray-200:#e2e8f0; --gray-300:#cbd5e1; --gray-500:#64748b;
            --gray-700:#334155; --gray-900:#0f172a;
            --green-50:#f0fdf4; --green-500:#22c55e; --green-700:#15803d;
            --red-500:#ef4444; --red-700:#b91c1c;
        }
        body { font-family:'Inter',sans-serif; min-height:100vh; display:flex; background:var(--gray-50); }

        .left-panel {
            flex:1; background:linear-gradient(135deg,var(--blue-deep) 0%,var(--blue-mid) 50%,#1e40af 100%);
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            padding:60px 50px; position:relative; overflow:hidden;
        }
        .left-panel::before { content:''; position:absolute; width:380px; height:380px; border-radius:50%; background:rgba(255,255,255,0.05); top:-100px; right:-100px; }
        .left-panel::after  { content:''; position:absolute; width:260px; height:260px; border-radius:50%; background:rgba(255,255,255,0.04); bottom:-70px; left:-70px; }
        .left-panel h1 { color:var(--white); font-size:28px; font-weight:700; text-align:center; margin-bottom:12px; line-height:1.3; }
        .left-panel p  { color:rgba(255,255,255,0.68); font-size:14px; text-align:center; line-height:1.65; max-width:300px; }

        /* Steps indicator */
        .steps-bar { display:flex; align-items:center; gap:0; margin-top:40px; width:100%; max-width:280px; }
        .step-dot { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
        .step-dot.done    { background:var(--green-500); color:var(--white); }
        .step-dot.current { background:var(--white); color:var(--blue-bright); }
        .step-dot.pending { background:rgba(255,255,255,0.2); color:rgba(255,255,255,0.5); border:1px solid rgba(255,255,255,0.25); }
        .step-line { flex:1; height:2px; background:rgba(255,255,255,0.2); }
        .step-line.done { background:var(--green-500); }
        .steps-labels { display:flex; justify-content:space-between; width:100%; max-width:280px; margin-top:8px; }
        .steps-labels span { font-size:11px; color:rgba(255,255,255,0.6); font-weight:500; text-align:center; flex:1; }

        /* Right panel */
        .right-panel { width:470px; display:flex; flex-direction:column; justify-content:center; padding:60px 50px; background:var(--white); overflow-y:auto; }

        .portal-tag { display:inline-flex; align-items:center; gap:8px; background:var(--blue-pale); border:1px solid #bfdbfe; border-radius:8px; padding:7px 14px; margin-bottom:30px; font-size:11px; font-weight:700; color:var(--blue-bright); text-transform:uppercase; letter-spacing:0.6px; }

        h2 { font-size:24px; font-weight:700; color:var(--gray-900); margin-bottom:5px; }
        .subtitle { color:var(--gray-500); font-size:14px; margin-bottom:28px; line-height:1.5; }

        .alert { display:flex; align-items:flex-start; gap:10px; padding:13px 16px; border-radius:10px; font-size:14px; margin-bottom:20px; font-weight:500; line-height:1.5; }
        .alert-error   { background:#fef2f2; color:var(--red-700);   border:1px solid #fecaca; }
        .alert-success { background:var(--green-50); color:var(--green-700); border:1px solid #bbf7d0; }

        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--gray-500); margin-bottom:7px; }
        .input-wrap { position:relative; display:flex; align-items:center; }
        .input-wrap .ico { position:absolute; left:13px; color:var(--gray-300); font-size:14px; pointer-events:none; }
        .input-wrap input {
            width:100%; padding:12px 13px 12px 40px;
            border:1.5px solid var(--gray-200); border-radius:10px;
            font-size:14px; font-family:'Inter',sans-serif; color:var(--gray-700);
            background:var(--gray-50); outline:none; transition:border-color .2s, box-shadow .2s;
        }
        .input-wrap input:focus { border-color:var(--blue-bright); background:var(--white); box-shadow:0 0 0 3px rgba(37,99,235,0.1); }
        .toggle-pw { position:absolute; right:13px; background:none; border:none; color:var(--gray-300); cursor:pointer; font-size:14px; transition:color .2s; }
        .toggle-pw:hover { color:var(--blue-bright); }

        /* Password strength */
        .pw-bar-wrap { margin-top:6px; }
        .pw-bar { height:4px; border-radius:2px; background:var(--gray-100); overflow:hidden; margin-bottom:4px; }
        .pw-fill { height:100%; width:0%; transition:width .3s, background .3s; border-radius:2px; }
        .pw-lbl  { font-size:11px; color:var(--gray-500); }

        .btn { display:inline-flex; align-items:center; gap:7px; padding:13px 20px; border-radius:10px; font-size:14px; font-weight:600; font-family:'Inter',sans-serif; cursor:pointer; border:none; transition:all .2s; text-decoration:none; }
        .btn-primary { background:linear-gradient(135deg,var(--blue-bright),var(--blue-light)); color:var(--white); box-shadow:0 4px 14px rgba(37,99,235,0.3); width:100%; justify-content:center; margin-top:6px; }
        .btn-primary:hover { opacity:.92; transform:translateY(-1px); }
        .btn-outline { background:transparent; color:var(--blue-bright); border:1.5px solid var(--blue-pale); width:100%; justify-content:center; margin-top:10px; }
        .btn-outline:hover { background:var(--blue-pale); }

        .hint-box { background:var(--blue-pale); border:1px solid #bfdbfe; border-radius:10px; padding:13px 16px; margin-bottom:20px; font-size:13px; color:var(--blue-mid); line-height:1.6; }

        .user-found-badge { display:flex; align-items:center; gap:12px; background:var(--green-50); border:1px solid #bbf7d0; border-radius:12px; padding:14px 16px; margin-bottom:22px; }
        .user-found-badge .icon { font-size:24px; }
        .user-found-badge .name { font-size:14px; font-weight:700; color:var(--gray-900); }
        .user-found-badge .uname { font-size:12px; color:var(--gray-500); margin-top:2px; }

        /* Success state */
        .success-box { text-align:center; padding:20px 0; }
        .success-icon { font-size:64px; margin-bottom:16px; }

        @media (max-width:900px) { .left-panel { display:none; } .right-panel { width:100%; padding:40px 24px; } }
    </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
    <div style="font-size:48px;margin-bottom:22px">🔐</div>
    <h1>Tukar Kata<br>Laluan</h1>
    <p>Sahkan identiti anda menggunakan tarikh lahir, kemudian tetapkan kata laluan baharu.</p>

    <div class="steps-bar">
        <div class="step-dot <?= $step>=1?($step>1?'done':'current'):'pending' ?>"><?= $step>1?'✓':'1' ?></div>
        <div class="step-line <?= $step>1?'done':'' ?>"></div>
        <div class="step-dot <?= $step>=2?($step>2?'done':'current'):'pending' ?>"><?= $step>2?'✓':'2' ?></div>
        <div class="step-line <?= $step>2?'done':'' ?>"></div>
        <div class="step-dot <?= $step>=3?'done':'pending' ?>"><?= $step>=3?'✓':'3' ?></div>
    </div>
    <div class="steps-labels">
        <span>Sahkan<br>Identiti</span>
        <span>Kata Laluan<br>Baharu</span>
        <span>Selesai</span>
    </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
    <div class="portal-tag">🏛️ UPTM Academic Portal</div>

    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-circle-exclamation" style="flex-shrink:0;margin-top:2px"></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- ── STEP 1: Verify Identity ── -->
    <h2>Sahkan Identiti</h2>
    <p class="subtitle">Masukkan username atau e-mel anda, dan tarikh lahir untuk pengesahan.</p>

    <div class="hint-box">
        <i class="fas fa-circle-info" style="margin-right:7px"></i>
        Pastikan tarikh lahir anda telah didaftarkan dalam sistem. Jika belum, hubungi pentadbir untuk kemaskini profil anda terlebih dahulu.
    </div>

    <form method="POST">
        <input type="hidden" name="step" value="1">
        <div class="form-group">
            <label>Username / E-mel *</label>
            <div class="input-wrap">
                <i class="fas fa-user ico"></i>
                <input type="text" name="username" placeholder="Masukkan username atau e-mel anda" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username">
            </div>
        </div>
        <div class="form-group">
            <label>Tarikh Lahir *</label>
            <div class="input-wrap">
                <i class="fas fa-cake-candles ico"></i>
                <input type="date" name="date_of_birth" required max="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-shield-halved"></i> Sahkan Identiti</button>
    </form>
    <a href="login_page.php" class="btn btn-outline" style="text-decoration:none"><i class="fas fa-arrow-left"></i> Kembali ke Log Masuk</a>

    <?php elseif ($step === 2): ?>
    <!-- ── STEP 2: New Password ── -->
    <h2>Kata Laluan Baharu</h2>
    <p class="subtitle">Identiti anda telah disahkan. Tetapkan kata laluan baharu.</p>

    <?php if ($found_user): ?>
    <div class="user-found-badge">
        <span class="icon">✅</span>
        <div>
            <div class="name"><?= htmlspecialchars($found_user['full_name']) ?></div>
            <div class="uname">@<?= htmlspecialchars($found_user['username']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateNewPw()">
        <input type="hidden" name="step" value="2">
        <div class="form-group">
            <label>Kata Laluan Baharu *</label>
            <div class="input-wrap">
                <i class="fas fa-lock ico"></i>
                <input type="password" name="new_password" id="newPw" placeholder="Min. 8 aksara" required oninput="checkStrength(this.value)">
                <button type="button" class="toggle-pw" onclick="togglePw('newPw','eye1')"><i class="fas fa-eye" id="eye1"></i></button>
            </div>
            <div class="pw-bar-wrap">
                <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
                <span class="pw-lbl" id="pwLbl">Masukkan kata laluan</span>
            </div>
        </div>
        <div class="form-group">
            <label>Sahkan Kata Laluan Baharu *</label>
            <div class="input-wrap">
                <i class="fas fa-lock ico"></i>
                <input type="password" name="confirm_password" id="confirmPw" placeholder="Ulang kata laluan" required>
                <button type="button" class="toggle-pw" onclick="togglePw('confirmPw','eye2')"><i class="fas fa-eye" id="eye2"></i></button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Tukar Kata Laluan</button>
    </form>

    <?php elseif ($step === 3): ?>
    <!-- ── STEP 3: Done ── -->
    <div class="success-box">
        <div class="success-icon">🎉</div>
        <h2 style="margin-bottom:10px">Berjaya!</h2>
        <p class="subtitle"><?= htmlspecialchars($success) ?></p>
        <a href="login_page.php" class="btn btn-primary" style="text-decoration:none;margin-top:10px"><i class="fas fa-arrow-right-to-bracket"></i> Log Masuk Sekarang</a>
    </div>
    <?php endif; ?>
</div>

<script>
function togglePw(id, iconId) {
    const f = document.getElementById(id);
    const i = document.getElementById(iconId);
    f.type = (f.type==='password') ? 'text' : 'password';
    i.className = (f.type==='password') ? 'fas fa-eye' : 'fas fa-eye-slash';
}

function checkStrength(pw) {
    const fill = document.getElementById('pwFill');
    const lbl  = document.getElementById('pwLbl');
    let score = 0;
    if (pw.length >= 8)            score++;
    if (/[A-Z]/.test(pw))          score++;
    if (/[0-9]/.test(pw))          score++;
    if (/[^A-Za-z0-9]/.test(pw))   score++;
    const levels = [
        {pct:'0%',  bg:'#e5e7eb',txt:'Masukkan kata laluan', clr:'#64748b'},
        {pct:'25%', bg:'#ef4444',txt:'Lemah',                 clr:'#ef4444'},
        {pct:'50%', bg:'#f97316',txt:'Sederhana',             clr:'#f97316'},
        {pct:'75%', bg:'#eab308',txt:'Kuat',                  clr:'#eab308'},
        {pct:'100%',bg:'#22c55e',txt:'Sangat Kuat ✓',         clr:'#22c55e'},
    ];
    const lvl = pw.length===0 ? levels[0] : levels[score];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.bg;
    lbl.textContent       = lvl.txt;
    lbl.style.color       = lvl.clr;
}

function validateNewPw() {
    const pw  = document.getElementById('newPw').value;
    const cpw = document.getElementById('confirmPw').value;
    if (pw.length < 8) { alert('Kata laluan mesti sekurang-kurangnya 8 aksara.'); return false; }
    if (pw !== cpw)    { alert('Kata laluan tidak sepadan.'); return false; }
    return true;
}
</script>
</body>
</html>
