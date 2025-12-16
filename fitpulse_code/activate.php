<?php
require 'config.php';

$token = $_GET['token'] ?? '';

if ($token === '') {
    die('Token tidak ditemukan.');
}

$stmt = $conn->prepare(
    "SELECT Id, IsActive 
     FROM users 
     WHERE ActivationToken = ? 
     LIMIT 1"
);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die('Token tidak valid atau akun sudah aktif.');
}

if ((int)$user['IsActive'] === 1) {
    die('Akun sudah aktif. Silakan login.');
}

$stmt = $conn->prepare(
    "UPDATE users 
     SET IsActive = 1, ActivationToken = NULL 
     WHERE Id = ?"
);
$stmt->bind_param("i", $user['Id']);
$stmt->execute();
$stmt->close();

header('Location: login.php');
exit;
