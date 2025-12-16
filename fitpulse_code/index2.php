<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Welcome - FitPulse_U</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Font Antonio -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&display=swap">

    <!-- CSS Intro -->
    <link rel="stylesheet" href="assets/css/intro.css?v=60">
</head>
<body>

<div class="bg-animated">
    <!-- layer animasi gradient hijau -->
    <div class="bg-overlay"></div>

    <!-- KONTEN UTAMA -->
    <div class="welcome-shell">

        <!-- TEKS WELCOME BESAR -->
        <div class="welcome-title">
            WELCOME
        </div>

        <!-- LOGO BULAT DENGAN PULSE PUTIH -->
        <div class="welcome-logo">
            <div class="welcome-logo-circle">
                <div class="pulse-line"></div>
            </div>
            <div class="welcome-logo-text">
                <div class="welcome-logo-main">FITPULSE_U</div>
                <div class="welcome-logo-sub">CAMPUS WELLNESS</div>
            </div>
        </div>

        <!-- TOMBOL MASUK -->
        <div class="welcome-actions">
            <a href="login.php" class="welcome-btn-primary">Masuk Sekarang</a>
        </div>

    </div><!-- /.welcome-shell -->
</div><!-- /.bg-animated -->

</body>
</html>
