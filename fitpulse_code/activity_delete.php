<?php
require 'config.php';
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$activity_id = (int)($_GET["id"] ?? 0);

if ($activity_id > 0) {
    // Hanya hapus aktivitas yang benar-benar milik user ini
    $delete = $conn->prepare("DELETE FROM Activities WHERE Id = ? AND UserId = ?");
    $delete->bind_param("ii", $activity_id, $user_id);
    $delete->execute();
}

// Setelah hapus (atau jika id tidak valid), kembali ke daftar aktivitas
header("Location: activity_list.php");
exit;
