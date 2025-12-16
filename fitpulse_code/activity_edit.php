<?php
require 'config.php';
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$UserId = $_SESSION["user_id"];
$name   = $_SESSION["user_name"] ?? "User";

if (!isset($_GET["id"])) {
    header("Location: activity_list.php");
    exit;
}
$activityId = (int)$_GET["id"];

$stmt = $conn->prepare(
    "SELECT Id, ActivityDate, SportType, DurationMinutes, Notes
     FROM Activities
     WHERE Id = ? AND UserId = ?"
);
$stmt->bind_param("ii", $activityId, $UserId);
$stmt->execute();
$result   = $stmt->get_result();
$activity = $result->fetch_assoc();

if (!$activity) {
    header("Location: activity_list.php");
    exit;
}

$success = "";
$error   = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date     = $_POST["ActivityDate"] ?? "";
    $sport    = trim($_POST["SportType"] ?? "");
    $duration = (int)($_POST["DurationMinutes"] ?? 0);
    $notes    = trim($_POST["Notes"] ?? "");

    if ($date === "" || $sport === "" || $duration <= 0) {
        $error = "Tanggal, jenis olahraga, dan durasi wajib diisi dengan benar.";
    } else {
        // contoh aturan: 1 menit = 1 poin
        $points = $duration;

        $up = $conn->prepare(
            "UPDATE Activities
             SET ActivityDate = ?, SportType = ?, DurationMinutes = ?, Notes = ?, Points = ?
             WHERE Id = ? AND UserId = ?"
        );
        $up->bind_param("ssissii", $date, $sport, $duration, $notes, $points, $activityId, $UserId);

        if ($up->execute()) {
            $success = "Perubahan aktivitas berhasil disimpan.";
            $activity["ActivityDate"]     = $date;
            $activity["SportType"]        = $sport;
            $activity["DurationMinutes"]  = $duration;
            $activity["Notes"]            = $notes;
        } else {
            $error = "Terjadi kesalahan saat menyimpan perubahan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Aktivitas - FitPulse U</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        * , *::before, *::after { box-sizing:border-box; }

        /* BACKGROUND SAMA LOGIN */
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #062821;
            background:
                radial-gradient(circle at 0% 0%,   #d1f4e8 0%, transparent 55%),
                radial-gradient(circle at 100% 100%, #a7f3d0 0%, transparent 55%),
                linear-gradient(145deg, #f9fffb, #e6f7f0);
        }

        .page-shell{
            width:100%;
            max-width:1080px;
            padding:20px;
            animation:soft-in .35s ease-out;
        }
        @keyframes soft-in{
            from{opacity:0;transform:translateY(8px);}
            to{opacity:1;transform:translateY(0);}
        }

        /* LOGO FITPULSE_U */
        .brand-logo-top {
            display:flex;
            align-items:center;
            justify-content:center;
            gap:0.9rem;
            margin-bottom:18px;
            padding:0.8rem 1.6rem;
            border-radius:999px;
            background:rgba(209,244,232,0.96);
            box-shadow:0 14px 40px rgba(46,190,138,0.25);
        }
        .brand-circle{
            position:relative;
            width:52px;
            height:52px;
            border-radius:999px;
            background:radial-gradient(circle at 30% 20%, #d1f4e8, #5dd9b5 35%, #2ebe8a 70%, #1da876);
            box-shadow:0 0 18px rgba(45,190,138,0.7);
            flex-shrink:0;
            overflow:hidden;
        }
        .pulse-line{
            position:absolute;
            top:50%;
            left:50%;
            width:40px;
            height:30px;
            transform:translate(-50%, -50%);
        }
        .pulse-line::before{
            content:"";
            position:absolute;
            inset:0;
            background-image:linear-gradient(to right,#ffffff,#ffffff);
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
            0%{transform:scale(0.7);opacity:0;}
            25%{transform:scale(1.15);opacity:1;}
            60%{transform:scale(1);opacity:0.9;}
            100%{transform:scale(0.8);opacity:0;}
        }
        .brand-text{
            display:flex;
            flex-direction:column;
            align-items:flex-start;
        }
        .brand-main{
            font-size:14px;
            font-weight:800;
            letter-spacing:0.08em;
            color:#047857;
        }
        .brand-sub{
            font-size:9px;
            font-weight:600;
            letter-spacing:0.12em;
            text-transform:uppercase;
            color:#2ebe8a;
            opacity:0.9;
        }

        /* LAYOUT DUA KOLOM */
        .layout-row{
            display:flex;
            gap:20px;
            align-items:stretch;
            flex-wrap:wrap;
        }
        .col-form{
            flex:1 1 360px;
        }
        .col-video{
            flex:1 1 360px;
        }

        /* CARD FORM KIRI */
        .card-glass {
            position:relative;
            border-radius:26px;
            padding:24px 24px 26px;
            background:rgba(255,255,255,0.96);
            backdrop-filter:blur(14px);
            -webkit-backdrop-filter:blur(14px);
            box-shadow:
                0 24px 80px rgba(15,118,110,0.24),
                0 0 0 1px rgba(148,163,184,0.18);
        }
        .card-glass::after{
            content:"";
            position:absolute;
            left:16%;
            right:16%;
            bottom:0;
            height:4px;
            border-radius:999px;
            background:linear-gradient(90deg,#6ee7b7,#22c55e,#a7f3d0);
            opacity:.55;
            filter:blur(2px);
        }

        .title {
            font-size:22px;
            font-weight:800;
            margin-bottom:4px;
            color:#022c22;
            text-align:center;
        }
        .subtitle {
            font-size:13px;
            color:#6b7280;
            margin-bottom:16px;
            text-align:center;
        }

        .form-label {
            font-size:13px;
            color:#374151;
        }
        .form-control, .form-select {
            background-color:#f9fafb;
            border-color:#d1d5db;
            color:#111827;
            font-size:14px;
            border-radius:999px;
            padding-inline:1rem;
            transition:border-color .18s ease, box-shadow .18s ease,
                     background-color .18s ease, transform .12s ease;
        }
        textarea.form-control{
            border-radius:18px;
            min-height:90px;
        }
        .form-control:focus, .form-select:focus {
            background-color:#ffffff;
            border-color:#2ebe8a;
            box-shadow:0 0 0 0.14rem rgba(46,190,138,0.35);
            color:#111827;
            transform:translateY(-1px);
        }

        .btn-green{
            border:none;
            border-radius:999px;
            background-image:linear-gradient(135deg,#2ebe8a,#5dd9b5);
            color:#f9fafb;
            font-weight:600;
            font-size:0.95rem;
            transition:transform .12s ease, box-shadow .12s ease, filter .12s ease;
            box-shadow:0 12px 32px rgba(46,190,138,0.55);
        }
        .btn-green:hover{
            transform:translateY(-1px);
            filter:brightness(1.04);
            box-shadow:0 16px 40px rgba(45,190,138,0.65);
        }
        .btn-green:active{
            transform:translateY(0);
            box-shadow:0 8px 24px rgba(45,190,138,0.55);
        }

        .btn-ghost {
            border-radius:999px;
            border:1px solid rgba(148,163,184,0.6);
            background:rgba(248,250,252,0.96);
            color:#1f2937;
            font-size:14px;
        }
        .btn-ghost:hover { background:rgba(229,231,235,0.96); }

        .alert {
            font-size:13px;
            border-radius:10px;
            padding:8px 10px;
        }

        /* PANEL VIDEO KANAN */
        .video-panel{
            border-radius:26px;
            background:#ffffff;
            box-shadow:
                0 20px 60px rgba(15,23,42,0.18),
                0 0 0 1px rgba(148,163,184,0.18);
            padding:18px 18px 16px;
        }
        .video-title{
            font-size:15px;
            font-weight:700;
            color:#022c22;
            margin-bottom:6px;
        }
        .video-sub{
            font-size:12px;
            color:#6b7280;
            margin-bottom:10px;
        }
        .video-grid{
            display:grid;
            grid-template-columns:repeat(2,1fr);
            grid-auto-rows:120px;
            gap:8px;
        }
        .video-box{
            position:relative;
            border-radius:14px;
            overflow:hidden;
            background:#000;
        }
        .video-box video{
            width:100%;
            height:100%;
            object-fit:cover;
        }
        .video-label{
            position:absolute;
            left:8px;
            bottom:6px;
            padding:2px 8px;
            border-radius:999px;
            background:rgba(15,23,42,0.7);
            color:#f9fafb;
            font-size:11px;
        }

        @media (max-width: 900px){
            .layout-row{flex-direction:column;}
        }
    </style>
</head>
<body>
<div class="page-shell">
    <!-- LOGO -->
    <div class="brand-logo-top">
        <div class="brand-circle">
            <div class="pulse-line"></div>
        </div>
        <div class="brand-text">
            <div class="brand-main">FITPULSE_U</div>
            <div class="brand-sub">CAMPUS WELLNESS</div>
        </div>
    </div>

    <div class="layout-row">
        <!-- KOLOM FORM -->
        <div class="col-form">
            <div class="card-glass">
                <h1 class="title">Edit Aktivitas</h1>
                <p class="subtitle">Perbarui data aktivitas olahraga Anda.</p>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger mb-2">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success !== ""): ?>
                    <div class="alert alert-success mb-2">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="activity_edit.php?id=<?php echo $activityId; ?>">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Aktivitas</label>
                        <input type="date" name="ActivityDate" class="form-control"
                               value="<?php echo htmlspecialchars($activity['ActivityDate']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Olahraga</label>
                        <input type="text" name="SportType" class="form-control"
                               value="<?php echo htmlspecialchars($activity['SportType']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Durasi (menit)</label>
                        <input type="number" name="DurationMinutes" class="form-control" min="1"
                               value="<?php echo (int)$activity['DurationMinutes']; ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Catatan</label>
                        <textarea name="Notes" class="form-control"
                                  placeholder="Misalnya: lari di lapangan kampus, pace santai."><?php
                            echo htmlspecialchars($activity['Notes']);
                        ?></textarea>
                    </div>

                    <div class="d-flex flex-column flex-md-row gap-2">
                        <button type="submit" class="btn btn-green flex-fill py-2">
                            Simpan Perubahan
                        </button>
                        <a href="activity_list.php" class="btn btn-ghost flex-fill py-2 text-center">
                            Kembali ke Riwayat
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- KOLOM VIDEO -->
        <div class="col-video">
            <div class="video-panel">
                <div class="video-title">Workout Inspiration</div>
                <div class="video-sub">Empat cuplikan latihan untuk memotivasi sesi olahragamu.</div>

                <div class="video-grid">
                    <div class="video-box">
                        <video src="project/intro3.mp4" muted autoplay loop playsinline controls></video>
                        <div class="video-label">Running</div>
                    </div>
                    <div class="video-box">
                        <video src="project/intro4.mp4" muted autoplay loop playsinline controls></video>
                        <div class="video-label">Track</div>
                    </div>
                    <div class="video-box">
                        <video src="project/intro5.mp4" muted autoplay loop playsinline controls></video>
                        <div class="video-label">Workout</div>
                    </div>
                    <div class="video-box">
                        <video src="project/intro6.mp4" muted autoplay loop playsinline controls></video>
                        <div class="video-label">Outdoor</div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.layout-row -->
</div>
</body>
</html>
