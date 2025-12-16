<?php
require 'config.php';
session_start();

$login_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $Email    = $_POST["Email"] ?? "";
    $Password = $_POST["Password"] ?? "";

    if ($Email === "" || $Password === "") {
        $login_error = "Email dan password wajib diisi.";
    } else {
        // HAPUS CEK IsActive: cukup ambil user dan cek password
        $stmt = $conn->prepare(
            "SELECT Id, Name, Email, PasswordHash, IsAdmin
             FROM users
             WHERE Email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $Email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $login_error = "Email atau password salah.";
        } elseif (!password_verify($Password, $user["PasswordHash"])) {
            $login_error = "Email atau password salah.";
        } else {
            // SET SESSION pakai data user yang login sekarang
            $_SESSION["user_id"]    = (int)$user["Id"];
            $_SESSION["user_name"]  = $user["Name"];
            $_SESSION["user_email"] = $user["Email"]; // email tetap string
            $_SESSION["is_admin"]   = (int)$user["IsAdmin"];

            // SETELAH LOGIN, MASUK KE INTRO_DASHBOARD DULU
            header("Location: intro_dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - FITPULSE_U</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
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
            color:#062821;
            background:
                radial-gradient(circle at 0% 0%,#d1f4e8 0%,transparent 55%),
                radial-gradient(circle at 100% 100%,#a7f3d0 0%,transparent 55%),
                linear-gradient(145deg,#f9fffb,#e6f7f0);
        }

        .login-shell{
            width:100%;
            max-width:480px;
            padding:20px;
            animation:soft-in .35s ease-out;
        }
        @keyframes soft-in{
            from{opacity:0;transform:translateY(8px);}
            to{opacity:1;transform:translateY(0);}
        }

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
            width:60px;
            height:60px;
            border-radius:999px;
            background:radial-gradient(circle at 30% 20%,#d1f4e8,#5dd9b5 35%,#2ebe8a 70%,#1da876);
            box-shadow:0 0 24px rgba(45,190,138,.7);
            flex-shrink:0;
            overflow:hidden;
        }
        .pulse-line{
            position:absolute;
            top:50%;
            left:50%;
            width:48px;
            height:34px;
            transform:translate(-50%,-50%);
        }
        .pulse-line::before{
            content:"";
            position:absolute;
            inset:0;
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
            0%{transform:scale(.7);opacity:0;}
            25%{transform:scale(1.15);opacity:1;}
            60%{transform:scale(1);opacity:.9;}
            100%{transform:scale(.8);opacity:0;}
        }
        .brand-text{display:flex;flex-direction:column;align-items:flex-start;}
        .brand-main{font-size:14px;font-weight:800;letter-spacing:.08em;color:#047857;}
        .brand-sub{font-size:9px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#2ebe8a;opacity:.9;}

        .login-card{
            position:relative;
            border-radius:26px;
            padding:28px 28px 30px;
            background:rgba(255,255,255,.92);
            backdrop-filter:blur(14px);
            -webkit-backdrop-filter:blur(14px);
            box-shadow:0 24px 80px rgba(15,118,110,.24),
                       0 0 0 1px rgba(148,163,184,.2);
        }
        .login-card::after{
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

        .login-title{
            font-size:28px;
            font-weight:800;
            margin-bottom:10px;
            color:#022c22;
            text-align:center;
            letter-spacing:.02em;
            text-shadow:0 1px 2px rgba(15,23,42,.08);
        }
        .login-title span{color:#059669;position:relative;}
        .login-title span::after{
            content:"";
            position:absolute;
            left:0;right:0;bottom:-6px;
            height:3px;
            border-radius:999px;
            background:linear-gradient(90deg,#6ee7b7,#22c55e,#a7f3d0);
            opacity:.85;
        }

        .login-subtitle{
            font-size:13px;
            color:#6b7280;
            margin-bottom:20px;
            text-align:center;
        }

        .form-label{font-size:13px;color:#374151;}
        .form-control{
            background-color:#f9fafb;
            border-color:#d1d5db;
            color:#111827;
            border-radius:999px;
            padding-inline:1rem;
            font-size:.9rem;
            transition:border-color .18s ease,box-shadow .18s ease,
                     background-color .18s ease,transform .12s ease;
        }
        .form-control:focus{
            background-color:#fff;
            border-color:#2ebe8a;
            box-shadow:0 0 0 .14rem rgba(46,190,138,.35);
            color:#111827;
            transform:translateY(-1px);
        }

        .btn-green{
            border:none;
            border-radius:999px;
            background-image:linear-gradient(135deg,#2ebe8a,#5dd9b5);
            color:#f9fafb;
            font-weight:600;
            font-size:.95rem;
            transition:transform .12s ease,box-shadow .12s ease,filter .12s ease;
            box-shadow:0 12px 32px rgba(46,190,138,.55);
        }
        .btn-green:hover{
            transform:translateY(-1px);
            filter:brightness(1.04);
            box-shadow:0 16px 40px rgba(45,190,138,.65);
        }
        .btn-green:active{
            transform:translateY(0);
            box-shadow:0 8px 24px rgba(45,190,138,.55);
        }

        .link-muted{
            color:#4b5563;
            font-size:13px;
            margin-top:18px;
            padding:10px 12px;
            border-radius:999px;
            background:rgba(236,253,245,.85);
            border:1px dashed rgba(16,185,129,.25);
        }
        .link-muted a{
            color:#0f766e;
            text-decoration:none;
            font-weight:600;
        }
        .link-muted a:hover{text-decoration:underline;}

        .alert{font-size:13px;padding:8px 10px;border-radius:10px;}
    </style>
</head>
<body>

<div class="login-shell">
    <div class="brand-logo-top">
        <div class="brand-circle">
            <div class="pulse-line"></div>
        </div>
        <div class="brand-text">
            <div class="brand-main">FITPULSE_U</div>
            <div class="brand-sub">CAMPUS WELLNESS</div>
        </div>
    </div>

    <div class="login-card">
        <h1 class="login-title">Masuk ke <span>FITPULSE_U</span></h1>
        <p class="login-subtitle">
            Kelola aktivitas olahraga dan pantau progres sehatmu di lingkungan kampus.
        </p>

        <?php if ($login_error !== ""): ?>
            <div class="alert alert-danger mb-3">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="Email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="Password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-green w-100 py-2 mt-2">Login</button>

            <p class="text-center link-muted mb-0">
                Belum punya akun? <a href="register.php">Daftar di sini</a>
            </p>
        </form>
    </div>
</div>

</body>
</html>
