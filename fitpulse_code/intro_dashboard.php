<?php
require 'config.php';
session_start();

// pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ambil nama depan dari sesi
$fullName  = $_SESSION['user_name'] ?? 'User';
$parts     = preg_split('/\s+/', trim($fullName));
$firstName = strtoupper($parts[0] ?? 'USER');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Intro FitPulse U</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;800&family=Poppins:wght@600;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/intro_dashboard.css">
</head>
<body>

<div id="intro-overlay">
    <!-- 1 video background -->
    <div class="intro-video-wrapper">
        <video src="project/intro5.mp4" class="intro-video" autoplay muted loop playsinline></video>
    </div>

    <!-- layer putih gelap -->
    <div class="intro-dim"></div>

    <!-- konten teks + tombol -->
    <div class="intro-content">
        <div class="intro-hello">HALO <?php echo htmlspecialchars($firstName); ?></div>

        <div class="intro-main">
            <span class="intro-big intro-big-main">LETS START</span>
            <span class="intro-big intro-big-sub">YOUR</span>
        </div>

        <div class="intro-journey">journey</div>

        <div class="intro-start-wrapper">
            <a href="dashboard.php" class="intro-start-btn">
                START
            </a>
        </div>
    </div>
</div>

</body>
</html>
