<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$UserId  = (int) $_SESSION["user_id"];
$name    = $_SESSION["user_name"] ?? "User";
$initial = strtoupper(substr($name,0,1));
$activeNav = 'workout';

/* =========================
   PROSES FORM TAMBAH AKTIVITAS
   ========================== */
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date     = trim($_POST['date'] ?? '');
    $sport    = trim($_POST['sport'] ?? '');
    $duration = (int)($_POST['duration'] ?? 0);
    $note     = trim($_POST['note'] ?? '');

    if ($date === '')     $errors[] = 'Tanggal wajib diisi.';
    if ($sport === '')    $errors[] = 'Jenis olahraga wajib dipilih.';
    if ($duration <= 0)   $errors[] = 'Durasi harus lebih dari 0 menit.';

    if (!$errors) {
        // 1 poin per 10 menit, minimal 1
        $points = max(1, (int)floor($duration / 10));

        $stmt = $conn->prepare("
            INSERT INTO Activities (UserId, ActivityDate, SportType, DurationMinutes, Points, Notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssis", $UserId, $date, $sport, $duration, $points, $note);
        if ($stmt->execute()) {
            $success = true;
            $_POST = [];
        } else {
            $errors[] = 'Gagal menyimpan aktivitas.';
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tambah Aktivitas - FitPulse U</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-solid-rounded/css/uicons-solid-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-bold-rounded/css/uicons-bold-rounded.css">

    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/activity_list.css?v=3">

    <style>
    /* Hilangkan garis bawah ikon sidebar di halaman ini */
    .sidebar .nav-ico {
      text-decoration: none !important;
      border: none;
      outline: none;
    }
    .sidebar .nav-ico:focus,
    .sidebar .nav-ico:hover,
    .sidebar .nav-ico:active {
      text-decoration: none !important;
      outline: none;
    }
    </style>
</head>
<body>
<div class="app">
  <!-- SIDEBAR -->
  <aside class="sidebar" aria-label="Main nav">
    <div class="logo"><?php echo htmlspecialchars($initial); ?></div>

    <nav class="top-nav">
      <a class="nav-ico<?php echo $activeNav==='home'?' active':'';?>"
         href="dashboard.php" title="Dashboard" data-key="home">
        <i class="fi fi-sr-home"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='analytics'?' active':'';?>"
         href="activity_list.php" title="Analytics" data-key="analytics">
        <i class="fi fi-sr-stats"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='workout'?' active':'';?>"
         href="activity_add.php" title="Tambah Aktivitas" data-key="workout">
        <i class="fi fi-br-gym"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='schedule'?' active':'';?>"
         href="activity_plan.php" title="Workout Plan" data-key="schedule">
        <i class="fi fi-rr-calendar"></i>
      </a>
    </nav>

    <nav class="bottom-nav">
      <a class="nav-ico<?php echo $activeNav==='settings'?' active':'';?>"
         href="profile.php#settings" title="Settings" data-key="settings">
        <i class="fi fi-br-sun"></i>
      </a>
    </nav>
  </aside>

  <!-- KONTEN UTAMA -->
  <main class="content-activity">
    <div class="bubble-bg"></div>
    <div class="bg-ornament"></div>
    <div class="bg-pattern"></div>

    <div class="content-inner">
      <!-- HEADER -->
      <header class="content-header">
        <h1>TAMBAH AKTIVITAS</h1>
        <p>Catat aktivitas fisikmu dan kumpulkan poin kesehatan mahasiswa.</p>

        <div class="user-pill">
          <div class="user-avatar">
            <?= strtoupper(substr($name,0,1)); ?>
          </div>
          <div class="user-text">
            <strong><?= htmlspecialchars($name); ?></strong>
            <span class="dot">·</span>
            <span>FitPulse U · Stay Active on Campus</span>
          </div>
        </div>

        <div class="steps">
          <span class="steps-dot"></span>
          <span class="steps-label">Form Pencatatan Aktivitas</span>
        </div>
      </header>

      <!-- NOTIF -->
      <?php if ($success): ?>
        <div class="badge-soft" style="margin-top:16px;background:#ecfdf3;border-color:#bbf7d0;color:#166534;">
          Aktivitas berhasil disimpan.
        </div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="badge-soft" style="margin-top:16px;background:#fee2e2;border-color:#fecaca;color:#991b1b;">
          <?= htmlspecialchars(implode(' · ', $errors)); ?>
        </div>
      <?php endif; ?>

      <!-- INTRO -->
      <section class="card">
        <div class="card-head">
          <div>
            <h2 class="card-title">Fokus Mahasiswa Sehat</h2>
            <p class="card-sub">
              Bangun kebiasaan sehat di tengah jadwal kuliah yang padat.
            </p>
          </div>
        </div>
      </section>

      <!-- FORM -->
      <section class="card" style="margin-top:18px;">
        <div class="card-head">
          <div>
            <h2 class="card-title">Form Tambah Aktivitas</h2>
            <p class="card-sub">Isi data aktivitas harianmu dengan lengkap.</p>
          </div>
        </div>

        <form action="activity_add.php" method="post">
          <div class="field-row" style="margin-top:14px;">
            <div class="field-col">
              <label class="field-label">Tanggal Aktivitas</label>
              <input
                type="date"
                name="date"
                class="input"
                value="<?= htmlspecialchars($_POST['date'] ?? ''); ?>"
                required>
            </div>
            <div class="field-col">
              <label class="field-label">Jenis Olahraga</label>
              <select name="sport" class="input" required>
                <option value="">Pilih jenis olahraga</option>
                <?php
                $opts = ['Lari','Bersepeda','Futsal','Basket','Renang','Workout Gym','Jalan Cepat'];
                $cur  = $_POST['sport'] ?? '';
                foreach ($opts as $o):
                    $sel = $cur === $o ? 'selected' : '';
                ?>
                  <option value="<?= htmlspecialchars($o); ?>" <?= $sel; ?>>
                    <?= htmlspecialchars($o); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="field-row">
            <div class="field-col">
              <label class="field-label">Durasi (menit)</label>
              <input
                type="number"
                name="duration"
                class="input"
                min="1"
                placeholder="30"
                value="<?= htmlspecialchars($_POST['duration'] ?? ''); ?>"
                required>
            </div>
          </div>

          <label class="field-label" style="margin-top:10px;">Catatan</label>
          <textarea
            name="note"
            class="input"
            rows="3"
            placeholder="Contoh: Lari keliling kampus 3 putaran bersama teman kelas."><?= htmlspecialchars($_POST['note'] ?? ''); ?></textarea>

          <div class="actions">
            <a href="activity_list.php" class="btn btn-outline">Lihat Riwayat</a>
            <button type="submit" class="btn btn-primary">Simpan Aktivitas</button>
          </div>
        </form>
      </section>
    </div><!-- /.content-inner -->
  </main>
</div><!-- /.app -->

<script>
(function(){
  const mapping = {
    home:      'dashboard.php',
    analytics: 'activity_list.php',
    workout:   'activity_add.php',
    schedule:  'activity_plan.php',
    settings:  'profile.php#settings'
  };
  document.querySelectorAll('.sidebar .nav-ico').forEach(btn=>{
    btn.addEventListener('click', function(e){
      const key = this.dataset.key;
      if (key && mapping[key]) {
        this.href = mapping[key];
      }
    });
  });
})();

window.addEventListener("load", () => {
  requestAnimationFrame(() => {
    document.querySelector(".app")?.classList.add("page-ready");
  });
});
</script>
</body>
</html>
