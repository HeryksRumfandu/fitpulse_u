<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Link Aktivasi Tidak Valid</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/css/register.css">
    <style>
        .invalid-page{
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#0f172a;
        }
        .invalid-card{
            background:#ffffff;
            border-radius:18px;
            padding:28px 26px 22px;
            max-width:420px;
            width:100%;
            box-shadow:0 18px 45px rgba(15,23,42,.45);
            text-align:center;
        }
        .invalid-icon{
            width:52px;height:52px;
            border-radius:999px;
            margin:0 auto 14px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#fee2e2;
            color:#b91c1c;
            font-size:26px;
            font-weight:700;
        }
        .invalid-title{font-size:20px;font-weight:700;margin-bottom:8px;color:#0f172a;}
        .invalid-text{font-size:14px;color:#6b7280;margin-bottom:18px;line-height:1.5;}
        .btn-row{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
        .btn-main, .btn-outline{
            border-radius:999px;
            padding:8px 18px;
            font-size:14px;
            font-weight:600;
            text-decoration:none;
            display:inline-block;
        }
        .btn-main{
            background:#16a34a;
            color:#fff;
        }
        .btn-main:hover{background:#15803d;}
        .btn-outline{
            border:1px solid #d1d5db;
            color:#111827;
            background:#fff;
        }
        .btn-outline:hover{background:#f3f4f6;}
    </style>
</head>
<body>
<div class="invalid-page">
    <div class="invalid-card">
        <div class="invalid-icon">!</div>
        <div class="invalid-title">Link aktivasi tidak valid</div>
        <p class="invalid-text">
            Maaf, link aktivasi yang kamu buka tidak ditemukan, sudah pernah dipakai,
            atau sudah tidak berlaku lagi.<br>
            Silakan minta link aktivasi baru atau lakukan login jika akunmu sudah aktif.
        </p>
        <div class="btn-row">
            <a href="login.php" class="btn-main">Ke halaman login</a>
            <a href="register.php" class="btn-outline">Daftar ulang</a>
        </div>
    </div>
</div>
</body>
</html>
