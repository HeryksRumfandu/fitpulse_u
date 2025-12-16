<?php
// activation_pending.php
// Halaman informasi setelah registrasi. 
// User diminta melakukan aktivasi akun via link yang dikirim ke email.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Aktivasi Akun - FITPULSE_U</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap (opsional, sama seperti login) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        *,*::before,*::after{box-sizing:border-box;}

        body{
            margin:0;
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            color:#022c22;
            background:
                radial-gradient(circle at 0% 0%,   #d1f4e8 0%, transparent 55%),
                radial-gradient(circle at 100% 100%, #a7f3d0 0%, transparent 55%),
                linear-gradient(145deg,#f9fffb,#e6f7f0);
        }

        .shell{
            width:100%;
            max-width:520px;
            padding:20px;
            animation:soft-in .35s ease-out;
        }
        @keyframes soft-in{
            from{opacity:0;transform:translateY(8px);}
            to{opacity:1;transform:translateY(0);}
        }

        /* Logo atas – konsisten dengan login */
        .brand-logo-top{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:.9rem;
            margin-bottom:22px;
            padding:.8rem 1.6rem;
            border-radius:999px;
            background:rgba(209,244,232,.96);
            box-shadow:0 14px 40px rgba(46,190,138,.25);
        }
        .brand-circle{
            position:relative;
            width:56px;
            height:56px;
            border-radius:999px;
            background:radial-gradient(circle at 30% 20%,#d1f4e8,#5dd9b5 35%,#2ebe8a 70%,#1da876);
            box-shadow:0 0 22px rgba(45,190,138,.7);
            flex-shrink:0;
            overflow:hidden;
        }
        .pulse-line{
            position:absolute;
            inset:0;
        }
        .pulse-line::before{
            content:"";
            position:absolute;
            top:50%;
            left:50%;
            width:40px;
            height:30px;
            transform:translate(-50%,-50%);
            background-image:linear-gradient(to right,#fff,#fff);
            background-size:100% 3px;
            background-repeat:no-repeat;
            background-position:left center;
            clip-path:polygon(
                0% 50%,10% 50%,20% 30%,28% 75%,36% 20%,
                45% 80%,54% 35%,64% 50%,74% 25%,82% 70%,
                90% 40%,100% 50%
            );
            animation:pulse-beat 1.3s ease-out infinite;
        }
        @keyframes pulse-beat{
            0%{transform:translate(-50%,-50%) scale(.7);opacity:0;}
            25%{transform:translate(-50%,-50%) scale(1.15);opacity:1;}
            60%{transform:translate(-50%,-50%) scale(1);opacity:.9;}
            100%{transform:translate(-50%,-50%) scale(.8);opacity:0;}
        }
        .brand-text{
            display:flex;
            flex-direction:column;
            align-items:flex-start;
        }
        .brand-main{
            font-size:14px;
            font-weight:800;
            letter-spacing:.08em;
            color:#047857;
        }
        .brand-sub{
            font-size:9px;
            font-weight:600;
            letter-spacing:.12em;
            text-transform:uppercase;
            color:#2ebe8a;
            opacity:.9;
        }

        /* Card utama */
        .card-pending{
            position:relative;
            border-radius:26px;
            padding:26px 26px 22px;
            background:rgba(255,255,255,.94);
            backdrop-filter:blur(14px);
            -webkit-backdrop-filter:blur(14px);
            box-shadow:
                0 24px 80px rgba(15,118,110,.24),
                0 0 0 1px rgba(148,163,184,.20);
        }
        .card-pending::after{
            content:"";
            position:absolute;
            left:18%;
            right:18%;
            bottom:0;
            height:4px;
            border-radius:999px;
            background:linear-gradient(90deg,#6ee7b7,#22c55e,#a7f3d0);
            opacity:.55;
            filter:blur(2px);
        }

        .title{
            font-size:22px;
            font-weight:800;
            margin:0 0 6px;
            color:#022c22;
            text-align:center;
        }
        .subtitle{
            margin:0 0 14px;
            font-size:13px;
            color:#6b7280;
            text-align:center;
        }

        .info-box{
            margin-top:10px;
            padding:12px 14px;
            border-radius:16px;
            background:#ecfdf5;
            border:1px dashed rgba(16,185,129,0.35);
            font-size:13px;
            color:#065f46;
        }

        .btn-main{
            border:none;
            border-radius:999px;
            background-image:linear-gradient(135deg,#22c55e,#4ade80);
            color:#f9fafb;
            font-weight:600;
            font-size:.9rem;
            padding:9px 16px;
            width:100%;
            box-shadow:0 12px 32px rgba(34,197,94,.55);
            text-decoration:none;
            display:inline-block;
            text-align:center;
            transition:transform .12s ease,box-shadow .12s ease,filter .12s ease;
        }
        .btn-main:hover{
            filter:brightness(1.03);
            transform:translateY(-1px);
            box-shadow:0 16px 40px rgba(34,197,94,.65);
        }
        .btn-main:active{
            transform:translateY(0);
            box-shadow:0 8px 24px rgba(34,197,94,.55);
        }

        .back-link{
            margin-top:12px;
            font-size:13px;
            text-align:center;
            color:#4b5563;
        }
        .back-link a{
            color:#0f766e;
            font-weight:600;
            text-decoration:none;
        }
        .back-link a:hover{
            text-decoration:underline;
        }
    </style>
</head>
<body>

<div class="shell">
    <div class="brand-logo-top">
        <div class="brand-circle">
            <div class="pulse-line"></div>
        </div>
        <div class="brand-text">
            <div class="brand-main">FITPULSE_U</div>
            <div class="brand-sub">CAMPUS WELLNESS</div>
        </div>
    </div>

    <div class="card-pending">
        <h1 class="title">Aktivasi akun dikirim</h1>
        <p class="subtitle">
            Pendaftaran berhasil. Sebelum bisa masuk ke FITPULSE_U, silakan cek email kamu
            dan klik link aktivasi yang sudah dikirim.
        </p>

        <div class="info-box">
            Jika email tidak muncul di Inbox, coba periksa folder Spam atau Promotions.
            Setelah akun aktif, kamu bisa login menggunakan email dan password yang sudah didaftarkan.
        </div>

        <div class="mt-3">
            <a href="login.php" class="btn-main">Kembali ke halaman login</a>
        </div>

        <p class="back-link mb-0">
            Salah memasukkan email? <a href="register.php">Daftar ulang dengan email yang benar</a>
        </p>
    </div>
</div>

</body>
</html>
