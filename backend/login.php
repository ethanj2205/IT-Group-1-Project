<?php
require_once __DIR__ . '/auth_common.php';
if (current_patient()) auth_redirect('patient.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ? AND role = 'patient' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role'] = 'patient';
            auth_redirect('patient.php');
        }
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>MediQueue SA - Patient Login</title><link rel="stylesheet" href="style.css"></head>
<body>
<header class="app-header"><div class="header-inner"><div class="brand"><div class="brand-mark">M</div><div><h1>MediQueue SA</h1><p>Patient Portal · Durban</p></div></div><span class="live-pill">● SECURE</span></div></header>
<main class="landing"><section class="card auth-card"><div class="eyebrow">Patient portal</div><h2>Patient Login</h2><p>Log in to view your queue, consultation records, prescriptions and live location.</p>
<?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on"><label>Email</label><input type="email" name="email" autocomplete="email" required><label>Password</label><input type="password" name="password" autocomplete="current-password" required minlength="6"><button class="primary" type="submit">Continue</button></form>
<p class="auth-links">Don’t have an account? <a href="register.php">Register here</a></p><p class="auth-links"><a href="indexmedi.php">← Back to MediQueue SA</a></p>
</section></main></body></html>