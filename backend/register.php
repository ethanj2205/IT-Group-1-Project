<?php
require_once __DIR__ . '/auth_common.php';
if (current_patient()) auth_redirect('patient.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $dob = trim($_POST['date_of_birth'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Enter your full name, a valid email and a password of at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$email]);
            if ($check->fetch()) throw new RuntimeException('An account with that email already exists. Please log in.');

            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'patient')");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO patients (user_id, full_name, date_of_birth, contact_number) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $fullName, ($dob !== '' ? $dob : null), ($contact !== '' ? $contact : null)]);
            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = 'patient';
            auth_redirect('patient.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Registration could not be completed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>MediQueue SA - Patient Registration</title><link rel="stylesheet" href="style.css"></head>
<body>
<header class="app-header"><div class="header-inner"><div class="brand"><div class="brand-mark">M</div><div><h1>MediQueue SA</h1><p>Patient Portal · Durban</p></div></div><span class="live-pill">● SECURE</span></div></header>
<main class="landing"><section class="card auth-card"><div class="eyebrow">Patient portal</div><h2>Create Patient Account</h2><p>Register once. Your records, prescriptions, queue activity and location will be linked to your account.</p>
<?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on"><label>Full Name</label><input type="text" name="full_name" autocomplete="name" required><label>Email</label><input type="email" name="email" autocomplete="email" required><label>Contact Number</label><input type="tel" name="contact_number" autocomplete="tel"><label>Date of Birth</label><input type="date" name="date_of_birth" autocomplete="bday"><label>Password</label><input type="password" name="password" autocomplete="new-password" required minlength="6"><label>Confirm Password</label><input type="password" name="confirm_password" autocomplete="new-password" required minlength="6"><button class="primary" type="submit">Continue</button></form>
<p class="auth-links">Already registered? <a href="login.php">Log in</a></p><p class="auth-links"><a href="indexmedi.php">← Back to MediQueue SA</a></p>
</section></main></body></html>