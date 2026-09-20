<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/db.php';

function auth_redirect($path) {
    header('Location: ' . $path);
    exit;
}

function current_patient() {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') return null;
    global $pdo;
    $stmt = $pdo->prepare('SELECT p.id, p.user_id, p.full_name, p.date_of_birth, p.contact_number, u.email FROM patients p INNER JOIN users u ON u.id = p.user_id WHERE p.user_id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_patient_page() {
    $patient = current_patient();
    if (!$patient) auth_redirect('login.php');
    return $patient;
}
?>
