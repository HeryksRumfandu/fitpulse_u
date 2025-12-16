<?php
require 'config.php';
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$UserId = (int)$_SESSION["user_id"];

// Ambil data user + profil tambahan
$stmt = $conn->prepare("
    SELECT Name, Npm, Email, PhotoPath, Goal, PreferredSport, Bio
    FROM Users
    WHERE Id = ?
");
$stmt->bind_param("i", $UserId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$success = "";
$error   = "";

// siapkan URL foto awal
$photoUrl = !empty($user['PhotoPath'])
    ? htmlspecialchars($user['PhotoPath'])
    : 'assets/img/default-avatar.png';

// UPDATE PROFIL + FOTO
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newName           = trim($_POST["Name"] ?? "");
    $newNpm            = trim($_POST["Npm"] ?? "");
    $newGoal           = trim($_POST["Goal"] ?? "");
    $newPreferredSport = trim($_POST["PreferredSport"] ?? "");
    $newBio            = trim($_POST["Bio"] ?? "");

    if ($newName === "" || $newNpm === "") {
        $error = "Nama dan NPM wajib diisi.";
    }

    // proses upload foto (opsional)
    $photoPath = $user['PhotoPath'] ?? null;

    if ($error === "" && !empty($_FILES['Photo']['name'])) {
        $uploadDir = __DIR__ . '/profile/';
        $ext       = pathinfo($_FILES['Photo']['name'], PATHINFO_EXTENSION);
        $extLower  = strtolower($ext);
        $allowed   = ['jpg','jpeg','png','webp'];

        if (!in_array($extLower, $allowed)) {
            $error = "Format foto tidak didukung. Gunakan JPG, JPEG, PNG, atau WEBP.";
        } elseif ($_FILES['Photo']['size'] > 2 * 1024 * 1024) {
            $error = "Ukuran foto maksimal 2MB.";
        } elseif (!is_dir($uploadDir)) {
            $error = "Folder upload belum tersedia.";
        } else {
            $newFileName = 'user_' . time() . '_' . mt_rand(1000,9999) . '.' . $extLower;
            $targetFile  = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['Photo']['tmp_name'], $targetFile)) {
                $photoPath = 'profile/' . $newFileName;
            } else {
                $error = "Gagal mengupload foto profil.";
            }
        }
    }

    if ($error === "") {
        $up = $conn->prepare("
            UPDATE Users
            SET Name = ?, Npm = ?, Goal = ?, PreferredSport = ?, Bio = ?, PhotoPath = ?
            WHERE Id = ?
        ");
        $up->bind_param(
            "ssssssi",
            $newName,
            $newNpm,
            $newGoal,
            $newPreferredSport,
            $newBio,
            $photoPath,
            $UserId
        );

        if ($up->execute()) {
            $success                = "Profil berhasil diperbarui.";
            $user["Name"]           = $newName;
            $user["Npm"]            = $newNpm;
            $user["Goal"]           = $newGoal;
            $user["PreferredSport"] = $newPreferredSport;
            $user["Bio"]            = $newBio;
            $user["PhotoPath"]      = $photoPath;
            $_SESSION["user_name"]  = $newName;

            $photoUrl = !empty($photoPath)
                ? htmlspecialchars($photoPath)
                : 'assets/img/default-avatar.png';
        } else {
            $error = "Terjadi kesalahan saat menyimpan profil.";
        }
    }
}

// Ringkasan 30 hari terakhir
$sum = $conn->prepare(
    "SELECT
        COALESCE(SUM(Points),0) AS TotalPoints,
        COALESCE(SUM(DurationMinutes),0) AS TotalMinutes,
        COUNT(*) AS TotalSessions
     FROM Activities
     WHERE UserId = ? AND ActivityDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
);
$sum->bind_param("i", $UserId);
$sum->execute();
$summary = $sum->get_result()->fetch_assoc();

// Data grafik 7 hari terakhir
$chart = $conn->prepare(
    "SELECT ActivityDate, COALESCE(SUM(DurationMinutes),0) AS TotalMinutes
     FROM Activities
     WHERE UserId = ? AND ActivityDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY ActivityDate
     ORDER BY ActivityDate"
);
$chart->bind_param("i", $UserId);
$chart->execute();
$chartRes = $chart->get_result();

$labels  = [];
$minutes = [];
while ($row = $chartRes->fetch_assoc()) {
    $labels[]  = $row["ActivityDate"];
    $minutes[] = (int)$row["TotalMinutes"];
}

// siapkan URL foto lagi untuk tampilan awal (kalau belum POST)
if (empty($photoUrl)) {
    $photoUrl = !empty($user['PhotoPath'])
        ? htmlspecialchars($user['PhotoPath'])
        : 'assets/img/default-avatar.png';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Pengguna - FITPULSE_U</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>

    <link rel="stylesheet" href="assets/css/profile.css">
    <style>
        .profile-photo-mini{
            width:56px;
            height:56px;
            border-radius:999px;
            overflow:hidden;
            background:#e5f9f0;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .profile-photo-mini-img{
            width:100%;
            height:100%;
            object-fit:cover;
        }
    </style>
</head>
<body>
<div class="page-shell">

    <!-- NAVBAR -->
    <header class="top-nav">
        <div class="nav-inner">
            <div class="nav-left">
                <a href="dashboard.php" class="brand-logo-top">
                    <div class="brand-circle">
                        <div class="pulse-line"></div>
                    </div>
                    <div class="brand-text">
                        <div class="brand-main">FITPULSE_U</div>
                        <div class="brand-sub">CAMPUS WELLNESS</div>
                    </div>
                </a>
            </div>
            <div class="nav-right">
                <div class="user-pill">
                    <span class="user-avatar">
                        <?php if (!empty($user['PhotoPath'])): ?>
                            <img src="<?= $photoUrl; ?>" alt="Avatar"
                                 class="user-avatar-img">
                        <?php else: ?>
                            <?= strtoupper(substr($user['Name'] ?? 'U',0,1)); ?>
                        <?php endif; ?>
                    </span>
                    <span class="user-name">
                        <?= htmlspecialchars($user['Name'] ?? 'Pengguna'); ?>
                    </span>
                </div>
                <a href="logout.php" class="btn btn-ghost-small">Logout</a>
            </div>
        </div>
    </header>

    <!-- KONTEN PROFIL -->
    <main class="content-wrapper">
        <div class="page-heading">
            <p class="page-kicker">Profil & Kesehatanmu</p>
            <h1 class="page-title">Halo, <?= htmlspecialchars($user['Name'] ?? ''); ?> 👋</h1>
            <p class="page-subtitle">
                Kelola identitas akun dan pantau ringkasan aktivitas olahraga 30 hari terakhir.
            </p>
        </div>

        <div class="grid-layout">
            <!-- Kiri: Form profil + tujuan -->
            <section class="glass-card layout-left">
                <div class="section-header">
                    <h2 class="card-title">Data Akun</h2>
                    <p class="card-caption">Perbarui identitas serta preferensi aktivitasmu.</p>
                </div>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger mb-2">
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success !== ""): ?>
                    <div class="alert alert-success mb-2">
                        <?= htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="profile.php" enctype="multipart/form-data" class="profile-form">

                    <div class="mb-3">
                        <label class="form-label">Foto profil</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="profile-photo-mini">
                                <img src="<?= $photoUrl; ?>" alt="Foto profil" class="profile-photo-mini-img">
                            </div>
                            <div>
                                <input type="file" name="Photo" accept="image/*" class="form-control">
                                <small class="text-muted">Format: JPG, JPEG, PNG, WEBP (maks. 2MB).</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="Name" class="form-control"
                               value="<?= htmlspecialchars($user['Name'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">NPM</label>
                        <input type="text" name="Npm" class="form-control"
                               value="<?= htmlspecialchars($user['Npm'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Email (tidak dapat diubah)</label>
                        <input type="email" class="form-control"
                               value="<?= htmlspecialchars($user['Email'] ?? ''); ?>" readonly>
                    </div>

                    <div class="divider-label">Tujuan & preferensi aktivitas</div>

                    <div class="mb-3">
                        <label class="form-label">Tujuan utama kamu?</label>
                        <div class="goal-grid">
                            <?php
                            $goalValue = $user['Goal'] ?? '';
                            $goals = [
                                "Turunkan berat badan"         => "Fokus kalori terbakar & cardio.",
                                "Tingkatkan performa olahraga" => "Latihan kekuatan & daya tahan.",
                                "Kesehatan & rutin bergerak"   => "Tetap aktif setiap hari."
                            ];
                            foreach ($goals as $goalText => $desc) :
                                $checked = ($goalValue === $goalText) ? 'checked' : '';
                            ?>
                            <label class="goal-card">
                                <input type="radio" name="Goal"
                                       value="<?= htmlspecialchars($goalText); ?>"
                                       <?= $checked; ?> hidden>
                                <div class="goal-title"><?= htmlspecialchars($goalText); ?></div>
                                <div class="goal-desc"><?= htmlspecialchars($desc); ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Aktivitas favorit</label>
                        <select name="PreferredSport" class="form-select select-rounded">
                            <option value="">Pilih salah satu</option>
                            <?php
                            $sports = [
                                "Jogging",
                                "Futsal / Sepak bola",
                                "Gym / Weight training",
                                "Basket",
                                "Yoga / Stretching"
                            ];
                            $selectedSport = $user['PreferredSport'] ?? "";
                            foreach ($sports as $s) {
                                $sel = ($s === $selectedSport) ? "selected" : "";
                                echo "<option value=\"".htmlspecialchars($s)."\" $sel>"
                                     .htmlspecialchars($s)."</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Bio singkat</label>
                        <textarea name="Bio" rows="2"
                                  class="form-control textarea-soft"
                                  placeholder="Contoh: Mahasiswa aktif suka futsal dan lari pagi."><?= htmlspecialchars($user['Bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex flex-column flex-md-row gap-2">
                        <button type="submit" class="btn btn-green flex-fill py-2">
                            Simpan Perubahan
                        </button>
                        <a href="dashboard.php" class="btn btn-ghost flex-fill py-2 text-center">
                            Kembali ke Dashboard
                        </a>
                    </div>
                </form>
            </section>

            <!-- Kanan: Ringkasan + grafik + foto profil -->
            <section class="glass-card layout-right">
                <div class="section-header mb-3">
                    <h2 class="card-title">Ringkasan Kesehatan 30 Hari</h2>
                    <p class="card-caption">
                        Lihat total poin, durasi, dan jumlah sesi aktivitas fisik yang sudah kamu catat.
                    </p>
                </div>

                <div class="profile-photo-block">
                    <div class="profile-photo-frame">
                        <img src="<?= $photoUrl; ?>" alt="Foto profil" class="profile-photo-img">
                    </div>
                    <div class="profile-photo-meta">
                        <div class="profile-photo-name"><?= htmlspecialchars($user['Name'] ?? ''); ?></div>
                        <div class="profile-photo-email"><?= htmlspecialchars($user['Email'] ?? ''); ?></div>
                    </div>
                </div>

                <div class="mini-profile-row">
                    <div class="mini-pill">
                        <span class="mini-label">Goal aktif</span>
                        <span class="mini-value">
                            <?= htmlspecialchars($user['Goal'] ?: 'Belum diatur'); ?>
                        </span>
                    </div>
                    <div class="mini-pill">
                        <span class="mini-label">Aktivitas favorit</span>
                        <span class="mini-value">
                            <?= htmlspecialchars($user['PreferredSport'] ?: 'Belum diatur'); ?>
                        </span>
                    </div>
                </div>

                <div class="stats-grid mt-2">
                    <div class="stat-chip primary">
                        <p class="chip-label">Total Poin</p>
                        <p class="chip-value"><?= (int)$summary["TotalPoints"]; ?></p>
                        <p class="chip-note">Semakin aktif, semakin tinggi poin kebugaranmu.</p>
                    </div>
                    <div class="stat-chip accent">
                        <p class="chip-label">Total Durasi</p>
                        <p class="chip-value"><?= (int)$summary["TotalMinutes"]; ?> mnt</p>
                        <p class="chip-note">Akumulasi semua sesi olahraga yang tercatat.</p>
                    </div>
                    <div class="stat-chip soft">
                        <p class="chip-label">Jumlah Sesi</p>
                        <p class="chip-value"><?= (int)$summary["TotalSessions"]; ?></p>
                        <p class="chip-note">Banyaknya log aktivitas dalam 30 hari terakhir.</p>
                    </div>
                </div>

                <div class="chart-block mt-3">
                    <div class="chart-header">
                        <h3 class="chart-title">Grafik Durasi (7 Hari)</h3>
                        <span class="chart-badge">Live activity</span>
                    </div>
                    <canvas id="activityChart" height="150"></canvas>
                </div>

                <div class="tips-block mt-3">
                    <h3 class="tips-title">Saran Singkat</h3>
                    <ul class="tips-list">
                        <li>Target minimal 150 menit aktivitas intensitas sedang setiap minggu.</li>
                        <li>Variasikan jenis olahraga untuk menjaga motivasi dan mencegah cedera.</li>
                        <li>Isi hari kosong dengan sesi ringan seperti stretching atau jalan santai.</li>
                    </ul>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
const labels  = <?= json_encode($labels); ?>;
const minutes = <?= json_encode($minutes); ?>;

const ctx = document.getElementById('activityChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Durasi (menit)',
            data: minutes,
            backgroundColor: 'rgba(45,190,138,0.65)',
            borderColor: 'rgba(34,197,94,1)',
            borderWidth: 1.5,
            borderRadius: 8,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: {
                ticks: { color: '#6b7280', font: { size: 11 } },
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                ticks: { color: '#9ca3af', stepSize: 10 },
                grid: { color: 'rgba(148,163,184,0.35)' }
            }
        }
    }
});
</script>
</body>
</html>
