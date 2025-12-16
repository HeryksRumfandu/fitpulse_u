<?php
require 'config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$UserId  = (int) $_SESSION['user_id'];
$name    = $_SESSION['user_name'] ?? 'User';
$initial = strtoupper(substr($name,0,1));
$activeNav = 'schedule';

/* ========= PASTIKAN TABEL scheduleplan ADA ========= */
$conn->query("
CREATE TABLE IF NOT EXISTS scheduleplan (
  Id INT AUTO_INCREMENT PRIMARY KEY,
  UserId INT NOT NULL,
  PlanName VARCHAR(150) NOT NULL,
  PlanType VARCHAR(30) NOT NULL DEFAULT 'Custom',
  TargetText VARCHAR(120) NOT NULL,
  DurationText VARCHAR(120) NOT NULL,
  LevelText VARCHAR(60) NOT NULL,
  CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (UserId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ========= HAPUS WORKOUT PLAN (dan event terkait) ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan_id'])) {
    $planId = (int)$_POST['delete_plan_id'];

    // Ambil nama plan dulu untuk hapus event
    $planNameForDelete = null;
    if ($sel = $conn->prepare("SELECT PlanName FROM scheduleplan WHERE Id = ? AND UserId = ? LIMIT 1")) {
        $sel->bind_param("ii", $planId, $UserId);
        $sel->execute();
        $r = $sel->get_result()->fetch_assoc();
        $sel->close();
        if ($r && !empty($r['PlanName'])) {
            $planNameForDelete = $r['PlanName'];
        }
    }

    // Hapus plan
    if ($del = $conn->prepare("DELETE FROM scheduleplan WHERE Id = ? AND UserId = ? LIMIT 1")) {
        $del->bind_param("ii", $planId, $UserId);
        $del->execute();
        $del->close();
    }

    // Opsional: hapus event yang judulnya diawali nama plan
    if ($planNameForDelete !== null) {
        $titleLike = $planNameForDelete.'%';
        if ($delEv = $conn->prepare("
            DELETE FROM scheduleevents
            WHERE UserId = ? AND Title LIKE ?
        ")) {
            $delEv->bind_param("is", $UserId, $titleLike);
            $delEv->execute();
            $delEv->close();
        }
    }

    header('Location: activity_plan.php');
    exit;
}

/* ========= PROCESS SAVE / APPLY WORKOUT PLAN → scheduleplan + scheduleevents ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['PlanName']) && !isset($_POST['delete_plan_id'])) {
    $planName  = trim($_POST['PlanName'] ?? '');
    $sportType = trim($_POST['SportType'] ?? '');
    $days      = $_POST['Days'] ?? [];                 // Sen, Sel, ...
    $sessions  = (int)($_POST['SessionsPerWeek'] ?? 0);
    $duration  = (int)($_POST['DurationMinutes'] ?? 0);
    $intensity = trim($_POST['Intensity'] ?? 'Low');
    $notes     = trim($_POST['Notes'] ?? '');

    if ($planName !== '' && $sportType !== '' && $duration > 0 && !empty($days)) {

        // ----- 1. SIMPAN RINGKASAN KE scheduleplan -----
        $targetText   = ($sessions > 0 ? $sessions : count($days)) . 'x/minggu';
        $durationText = $duration . ' mnt/sesi';
        $levelText    = $intensity;

        if ($stmtPlan = $conn->prepare("
            INSERT INTO scheduleplan (UserId, PlanName, PlanType, TargetText, DurationText, LevelText)
            VALUES (?, ?, 'Custom', ?, ?, ?)
        ")) {
            $stmtPlan->bind_param(
                "issss",
                $UserId,
                $planName,
                $targetText,
                $durationText,
                $levelText
            );
            if (!$stmtPlan->execute()) {
                die('ERROR SCHEDULEPLAN: '.$stmtPlan->error);
            }
            $stmtPlan->close();
        } else {
            die('ERROR PREPARE SCHEDULEPLAN: '.$conn->error);
        }

        // ----- 2. GENERATE EVENT KE scheduleevents -----
        $map   = ['Sen'=>1,'Sel'=>2,'Rab'=>3,'Kam'=>4,'Jum'=>5,'Sab'=>6,'Min'=>7];
        $today = new DateTime();

        foreach ($days as $shortDay) {
            if (!isset($map[$shortDay])) continue;
            $targetDow = $map[$shortDay];

            $d = clone $today;
            $maxStep = 7;
            while ((int)$d->format('N') !== $targetDow && $maxStep-- > 0) {
                $d->modify('+1 day');
            }

            $dateStr = $d->format('Y-m-d');
            $timeStr = '07:00:00';
            $title   = $planName . ' - ' . $sportType;

            $noteFull = 'Intensity: '.$intensity;
            if ($notes !== '') $noteFull .= ' | '.$notes;

            if ($stmt = $conn->prepare("
                INSERT INTO scheduleevents
                    (UserId, EventDate, Title, EventTime, DurationMinutes, Notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ")) {
                $stmt->bind_param(
                    "isssis",
                    $UserId,
                    $dateStr,
                    $title,
                    $timeStr,
                    $duration,
                    $noteFull
                );
                $stmt->execute();
                $stmt->close();
            }
        }

        header('Location: dashboard.php');
        exit;
    }
}

/* ========= LOAD WORKOUT PLAN DARI scheduleplan ========= */
$presetPlans = [];
$customPlans = [];

if ($stmt = $conn->prepare("
    SELECT Id, PlanName, PlanType, TargetText, DurationText, LevelText, CreatedAt
    FROM scheduleplan
    WHERE UserId = ?
    ORDER BY CreatedAt ASC
")) {
    $stmt->bind_param("i", $UserId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if ($row['PlanType'] === 'Preset') {
            $presetPlans[] = $row;
        } else {
            $customPlans[] = $row;
        }
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Workout Plan - FitPulse U</title>

<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-bold-rounded/css/uicons-bold-rounded.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/activity_plan.css">

<style>
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
      <a class="nav-ico<?php echo $activeNav==='home'?' active':'';?>" href="dashboard.php">
        <i class="fi fi-sr-home"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='analytics'?' active':'';?>" href="activity_list.php">
        <i class="fi fi-sr-stats"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='workout'?' active':'';?>" href="activity_add.php">
        <i class="fi fi-br-gym"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='schedule'?' active':'';?>" href="activity_plan.php">
        <i class="fi fi-rr-calendar"></i>
      </a>
    </nav>
    <nav class="bottom-nav">
      <a class="nav-ico<?php echo $activeNav==='settings'?' active':'';?>" href="profile.php#settings">
        <i class="fi fi-br-sun"></i>
      </a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="header">
      <div>
        <h3 class="title">Workout Plan</h3>
        <div class="stat-muted">
          Atur rencana latihanmu: kombinasi preset & rencana custom, lalu apply ke jadwal.
        </div>
      </div>
      <div class="controls">
        <button class="btn btn-sm btn-outline-secondary filter-btn" type="button">Mingguan</button>
        <button class="btn btn-sm btn-outline-secondary filter-btn" type="button">Bulanan</button>
      </div>
    </div>

    <section class="section-grid">
      <!-- LIST PLAN -->
      <div class="card plan-list-card">
        <div class="card-head-flex">
          <div>
            <strong>Daftar Workout Plan</strong>
            <div class="stat-muted">Kombinasi preset resmi dan rencana custom milikmu.</div>
          </div>
          <button class="btn btn-sm btn-success btn-create" type="button" onclick="scrollToForm()">
            + Buat rencana baru
          </button>
        </div>
        <div class="plan-table">
          <div class="plan-table-header">
            <span>Nama Rencana</span>
            <span>Jenis</span>
            <span>Target</span>
            <span>Durasi</span>
            <span>Level</span>
            <span class="text-end">Aksi</span>
          </div>

          <?php foreach ($presetPlans as $p): ?>
          <div class="plan-row preset">
            <span class="plan-name"><?php echo htmlspecialchars($p['PlanName']); ?></span>
            <span><span class="badge badge-preset"><?php echo htmlspecialchars($p['PlanType']); ?></span></span>
            <span><?php echo htmlspecialchars($p['TargetText']); ?></span>
            <span><?php echo htmlspecialchars($p['DurationText']); ?></span>
            <span><?php echo htmlspecialchars($p['LevelText']); ?></span>
            <span class="text-end">
              <button type="button" class="btn btn-xs btn-outline-primary">Preview</button>
              <button type="button" class="btn btn-xs btn-outline-success" onclick="scrollToForm()">Apply</button>
            </span>
          </div>
          <?php endforeach; ?>

          <?php foreach ($customPlans as $p): ?>
          <div class="plan-row custom">
            <span class="plan-name"><?php echo htmlspecialchars($p['PlanName']); ?></span>
            <span><span class="badge badge-custom"><?php echo htmlspecialchars($p['PlanType']); ?></span></span>
            <span><?php echo htmlspecialchars($p['TargetText']); ?></span>
            <span><?php echo htmlspecialchars($p['DurationText']); ?></span>
            <span><?php echo htmlspecialchars($p['LevelText']); ?></span>
            <span class="text-end">
              <!-- Edit: belum diimplementasikan penuh -->
              <button type="button" class="btn btn-xs btn-outline-primary" disabled>Edit</button>

              <!-- Hapus plan -->
              <form method="post" style="display:inline;"
                    onsubmit="return confirm('Hapus workout plan ini? Event jadwal dengan nama yang sama juga ikut dihapus.');">
                <input type="hidden" name="delete_plan_id" value="<?php echo (int)$p['Id']; ?>">
                <button type="submit" class="btn btn-xs btn-outline-danger">Hapus</button>
              </form>
            </span>
          </div>
          <?php endforeach; ?>

          <?php if (empty($presetPlans) && empty($customPlans)): ?>
          <div class="empty-state">
            Belum ada rencana latihan. Mulai dengan membuat workout plan pertamamu.
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- FORM -->
      <div class="card plan-form-card" id="plan-form-card">
        <div class="card-head-flex">
          <div>
            <strong>Form Workout Plan</strong>
            <div class="stat-muted">Atur hari, sesi, dan durasi untuk rencana latihanmu.</div>
          </div>
        </div>
        <form method="post" class="plan-form">
          <div class="mb-3">
            <label class="form-label">Nama Rencana</label>
            <input type="text" name="PlanName" class="form-control"
                   placeholder="Contoh: Morning Campus Run" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Jenis Aktivitas</label>
            <select name="SportType" class="form-select select-rounded" required>
              <option value="">Pilih salah satu</option>
              <option value="Jogging">Jogging</option>
              <option value="Gym / Weight">Gym / Weight</option>
              <option value="Futsal">Futsal</option>
              <option value="Yoga / Stretching">Yoga / Stretching</option>
              <option value="Cardio Mix">Cardio Mix</option>
            </select>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label">Hari Latihan</label>
              <div class="day-chip-grid">
                <?php
                  $days = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];
                  foreach ($days as $d):
                ?>
                <label class="day-chip">
                  <input type="checkbox" name="Days[]" value="<?php echo $d; ?>" hidden>
                  <span><?php echo $d; ?></span>
                </label>
                <?php endforeach; ?>
              </div>
              <small class="stat-muted">Pilih 2–5 hari yang ingin kamu jadikan jadwal latihan.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Jumlah sesi & durasi</label>
              <div class="row g-2">
                <div class="col-6">
                  <input type="number" name="SessionsPerWeek" min="1" max="14"
                         class="form-control" placeholder="Sesi/minggu" required>
                </div>
                <div class="col-6">
                  <input type="number" name="DurationMinutes" min="10" max="180"
                         class="form-control" placeholder="Durasi (mnt)" required>
                </div>
              </div>
              <small class="stat-muted">Rekomendasi: 3–5 sesi per minggu, 30–60 mnt per sesi.</small>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Intensitas</label>
            <div class="intensity-grid">
              <label class="intensity-pill active">
                <input type="radio" name="Intensity" value="Low" hidden checked>
                <span>Low</span>
              </label>
              <label class="intensity-pill">
                <input type="radio" name="Intensity" value="Medium" hidden>
                <span>Medium</span>
              </label>
              <label class="intensity-pill">
                <input type="radio" name="Intensity" value="High" hidden>
                <span>High</span>
              </label>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Catatan / Fokus Latihan</label>
            <textarea name="Notes" rows="2" class="form-control textarea-soft"
                      placeholder="Contoh: Fokus cardio ringan, pace santai, tingkatkan konsistensi."></textarea>
          </div>

          <div class="d-flex flex-column flex-md-row gap-2 mt-2">
            <button type="submit" class="btn btn-green flex-fill">
              Simpan Workout Plan & Apply ke Jadwal
            </button>
          </div>
        </form>
      </div>
    </section>
  </main>

  <!-- RIGHT SIDEBAR -->
  <aside class="right">
    <div class="card tips-card">
      <div class="card-head-flex">
        <strong>Tips Penyusunan Plan</strong>
      </div>
      <ul class="tips-list">
        <li>Pastikan ada minimal 1 hari rest setiap minggu.</li>
        <li>Campur sesi cardio dan strength untuk hasil optimal.</li>
        <li>Durasi lebih konsisten lebih penting daripada terlalu berat.</li>
      </ul>
    </div>
    <div class="card summary-card">
      <div class="card-head-flex">
        <strong>Rangkuman Mingguan</strong>
      </div>
      <div class="summary-row">
        <span>Rencana sesi</span><strong>5x / minggu</strong>
      </div>
      <div class="summary-row">
        <span>Total durasi</span><strong>210 mnt</strong>
      </div>
      <div class="summary-row">
        <span>Level fokus</span><strong>Active</strong>
      </div>
    </div>
  </aside>
</div>

<script>
// chip hari
document.querySelectorAll('.day-chip').forEach(label=>{
  const input = label.querySelector('input');
  label.addEventListener('click', e=>{
    e.preventDefault();
    input.checked = !input.checked;
    label.classList.toggle('active', input.checked);
  });
});
// intensity radio
document.querySelectorAll('.intensity-pill').forEach(label=>{
  const input = label.querySelector('input');
  label.addEventListener('click', e=>{
    e.preventDefault();
    document.querySelectorAll('.intensity-pill').forEach(l=>l.classList.remove('active'));
    input.checked = true;
    label.classList.add('active');
  });
});
function scrollToForm(){
  const card = document.getElementById('plan-form-card');
  if (card) card.scrollIntoView({behavior:'smooth', block:'start'});
}
</script>
</body>
</html>
