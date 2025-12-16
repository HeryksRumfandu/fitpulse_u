<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid      = (int)$_SESSION['user_id'];
$name     = $_SESSION['user_name'] ?? 'User';
$initial  = strtoupper(substr($name, 0, 1));
$activeNav = 'analytics';

/* =========================
   AUTO CREATE TABLE ScheduleEvents (SAFE)
   ========================== */
$conn->query("
CREATE TABLE IF NOT EXISTS ScheduleEvents (
  Id INT AUTO_INCREMENT PRIMARY KEY,
  UserId INT NOT NULL,
  EventDate DATE NOT NULL,
  Title VARCHAR(120) NOT NULL,
  EventTime TIME NOT NULL,
  DurationMinutes INT NOT NULL DEFAULT 0,
  Notes TEXT NULL,
  CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (UserId),
  INDEX (EventDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* =========================
   AJAX API
   ========================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? '';

    function json_fail($msg, $code = 400) {
        http_response_code($code);
        echo json_encode(['ok' => false, 'message' => $msg]);
        exit;
    }
    function json_ok($payload = []) {
        echo json_encode(array_merge(['ok' => true], $payload));
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $time  = trim($_POST['time'] ?? '');
    $dur   = (int)($_POST['dur'] ?? 0);
    $note  = trim($_POST['note'] ?? '');
    $date  = trim($_POST['date'] ?? '');
    $id    = (int)($_POST['id'] ?? 0);

    $is_date = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    $is_time = fn($t) =>
        (bool)preg_match('/^\d{2}:\d{2}$/', $t) ||
        (bool)preg_match('/^\d{2}:\d{2}:\d{2}$/', $t);

    /* ---- list event per bulan ---- */
    if ($action === 'list_month') {
        $year  = (int)($_POST['year'] ?? date('Y'));
        $month = (int)($_POST['month'] ?? date('n'));
        if ($month < 1 || $month > 12) json_fail("Bulan tidak valid.");

        $start = sprintf("%04d-%02d-01", $year, $month);
        $endTs = strtotime($start . " +1 month");
        $end   = date("Y-m-d", $endTs);

        $stmt = $conn->prepare("
            SELECT Id, EventDate, Title, EventTime, DurationMinutes, COALESCE(Notes,'') AS Notes
            FROM ScheduleEvents
            WHERE UserId = ? AND EventDate >= ? AND EventDate < ?
            ORDER BY EventDate ASC, EventTime ASC, Id ASC
        ");
        $stmt->bind_param("iss", $uid, $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();

        $events = [];
        while ($r = $res->fetch_assoc()) {
            $d = $r['EventDate'];
            $events[$d][] = [
                'id'    => (int)$r['Id'],
                'date'  => $r['EventDate'],
                'title' => $r['Title'],
                'time'  => substr($r['EventTime'], 0, 5),
                'dur'   => (int)$r['DurationMinutes'],
                'note'  => $r['Notes'],
            ];
        }
        $stmt->close();

        $activityCounts = [];
        $stmt2 = $conn->prepare("
            SELECT ActivityDate, COUNT(*) AS Cnt
            FROM Activities
            WHERE UserId = ? AND ActivityDate >= ? AND ActivityDate < ?
            GROUP BY ActivityDate
        ");
        $stmt2->bind_param("iss", $uid, $start, $end);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($r2 = $res2->fetch_assoc()) {
            $activityCounts[$r2['ActivityDate']] = (int)$r2['Cnt'];
        }
        $stmt2->close();

        json_ok([
            'events'         => $events,
            'activityCounts' => $activityCounts,
        ]);
    }

    /* ---- list event per hari (untuk modal) ---- */
    if ($action === 'list_day') {
        if (!$is_date($date)) json_fail("Tanggal tidak valid.");
        $stmt = $conn->prepare("
            SELECT Id, EventDate, Title, EventTime, DurationMinutes, COALESCE(Notes,'') AS Notes
            FROM ScheduleEvents
            WHERE UserId = ? AND EventDate = ?
            ORDER BY EventTime ASC, Id ASC
        ");
        $stmt->bind_param("is", $uid, $date);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $out[] = [
                'id'    => (int)$r['Id'],
                'date'  => $r['EventDate'],
                'title' => $r['Title'],
                'time'  => substr($r['EventTime'], 0, 5),
                'dur'   => (int)$r['DurationMinutes'],
                'note'  => $r['Notes'],
            ];
        }
        $stmt->close();
        json_ok(['events' => $out]);
    }

    /* ---- tambah event (opsional) ---- */
    if ($action === 'add') {
        if (!$is_date($date))  json_fail("Tanggal tidak valid.");
        if ($title === '')     json_fail("Judul wajib diisi.");
        if (!$is_time($time))  json_fail("Waktu tidak valid.");
        if ($dur < 0) $dur = 0;
        if (preg_match('/^\d{2}:\d{2}$/', $time)) $time .= ":00";

        $stmt = $conn->prepare("
            INSERT INTO ScheduleEvents
                (UserId, EventDate, Title, EventTime, DurationMinutes, Notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssis", $uid, $date, $title, $time, $dur, $note);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) json_fail("Gagal menambah event.", 500);
        json_ok();
    }

    /* ---- update event ---- */
    if ($action === 'update') {
        if ($id <= 0)         json_fail("ID event tidak valid.");
        if (!$is_date($date)) json_fail("Tanggal tidak valid.");
        if ($title === '')    json_fail("Judul wajib diisi.");
        if (!$is_time($time)) json_fail("Waktu tidak valid.");
        if ($dur < 0) $dur = 0;
        if (preg_match('/^\d{2}:\d{2}$/', $time)) $time .= ":00";

        $stmt = $conn->prepare("
            UPDATE ScheduleEvents
            SET EventDate = ?, Title = ?, EventTime = ?, DurationMinutes = ?, Notes = ?
            WHERE Id = ? AND UserId = ?
            LIMIT 1
        ");
        $stmt->bind_param("sssisii", $date, $title, $time, $dur, $note, $id, $uid);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) json_fail("Gagal mengubah event.", 500);
        json_ok();
    }

    /* ---- delete event dari modal ---- */
    if ($action === 'delete') {
        if ($id <= 0) json_fail("ID event tidak valid.");
        $stmt = $conn->prepare("DELETE FROM ScheduleEvents WHERE Id = ? AND UserId = ? LIMIT 1");
        $stmt->bind_param("ii", $id, $uid);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) json_fail("Gagal menghapus event.", 500);
        json_ok();
    }

    /* ---- delete aktivitas + update total poin ---- */
    if ($action === 'delete_activity') {
        $aid = (int)($_POST['aid'] ?? 0);
        if ($aid <= 0) json_fail("ID aktivitas tidak valid.");

        $stmt = $conn->prepare("DELETE FROM Activities WHERE Id = ? AND UserId = ? LIMIT 1");
        $stmt->bind_param("ii", $aid, $uid);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) json_fail("Gagal menghapus aktivitas.", 500);

        $stmt2 = $conn->prepare("SELECT COALESCE(SUM(Points),0) AS TotalPoints FROM Activities WHERE UserId = ?");
        $stmt2->bind_param("i", $uid);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        $totalPoints = (int)($row['TotalPoints'] ?? 0);

        json_ok(['totalPoints' => $totalPoints]);
    }

    json_fail("Action tidak dikenal.");
}

/* =========================
   RIWAYAT AKTIVITAS + WEEKLY DATA
   ========================== */
$aktivitas   = [];
$totalPoints = 0;

$stmtA = $conn->prepare("
    SELECT Id, ActivityDate, SportType, DurationMinutes, Points, COALESCE(Notes,'') AS Notes
    FROM Activities
    WHERE UserId = ?
    ORDER BY ActivityDate DESC, Id DESC
");
$stmtA->bind_param("i", $uid);
$stmtA->execute();
$resA = $stmtA->get_result();
while ($r = $resA->fetch_assoc()) {
    $totalPoints += (int)$r['Points'];
    $aktivitas[] = [
        'id'      => (int)$r['Id'],
        'tanggal' => $r['ActivityDate'],
        'jenis'   => $r['SportType'],
        'durasi'  => (int)$r['DurationMinutes'],
        'poin'    => (int)$r['Points'],
        'catatan' => $r['Notes'],
    ];
}
$stmtA->close();

$weeklyLabels = [];
$weeklyPoints = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $weeklyLabels[]   = date('D', strtotime($d));
    $weeklyPoints[$d] = 0;
}

$stmtW = $conn->prepare("
    SELECT ActivityDate, COALESCE(SUM(Points),0) AS P
    FROM Activities
    WHERE UserId = ? AND ActivityDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY ActivityDate
");
$stmtW->bind_param("i", $uid);
$stmtW->execute();
$resW = $stmtW->get_result();
while ($rw = $resW->fetch_assoc()) {
    $ad = $rw['ActivityDate'];
    if (isset($weeklyPoints[$ad])) {
        $weeklyPoints[$ad] = (int)$rw['P'];
    }
}
$stmtW->close();
$weeklyValues = array_values($weeklyPoints);

/* =========================
   LOAD WORKOUT PLAN DARI scheduleplan UNTUK TABEL BAWAH
   ========================== */
$plans = [];
if ($stmtP = $conn->prepare("
    SELECT Id, PlanName, PlanType, TargetText, DurationText, LevelText, CreatedAt
    FROM scheduleplan
    WHERE UserId = ?
    ORDER BY CreatedAt ASC
")) {
    $stmtP->bind_param("i", $uid);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    while ($r = $resP->fetch_assoc()) {
        $plans[] = $r;
    }
    $stmtP->close();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Riwayat Aktivitas & Workout Plan - FitPulse U</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-solid-rounded/css/uicons-solid-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-bold-rounded/css/uicons-bold-rounded.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/activity_list.css?v=3">

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
    .day-modal-backdrop{
      position:fixed;inset:0;background:rgba(15,23,42,.45);
      display:none;align-items:center;justify-content:center;z-index:40;
    }
    .day-modal{
      background:#fff;border-radius:16px;max-width:420px;width:100%;
      padding:18px 18px 16px;box-shadow:0 20px 60px rgba(15,23,42,.35);
    }
    .day-modal header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
    .day-modal-list{max-height:260px;overflow-y:auto;margin-top:6px;}
    .day-modal-item{padding:8px 4px;border-bottom:1px solid rgba(15,23,42,.06);display:flex;justify-content:space-between;gap:8px;}
    .day-modal-item strong{display:block;}
    .day-modal-meta{font-size:12px;color:#6b7280;margin-top:2px;}
    .btn-xs-delete{border:none;background:#fee2e2;color:#b91c1c;font-size:11px;border-radius:999px;padding:4px 10px;cursor:pointer;}
    .btn-xs-delete:hover{background:#fecaca;}
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
        <h1>Riwayat Aktivitas</h1>
        <p>Catatan olahraga, jadwal, dan progres kesehatan kamu.</p>

        <div class="user-pill">
          <div class="user-avatar">
            <?= strtoupper(substr($name,0,1)); ?>
          </div>
          <div class="user-text">
            <strong><?= htmlspecialchars($name); ?></strong>
            <span class="dot">·</span>
            <span><span id="totalPointText"><?= (int)$totalPoints; ?></span> poin total</span>
          </div>
        </div>

        <div class="steps">
          <span class="steps-dot"></span>
          <span class="steps-label">Schedule • Statistik • Riwayat • Workout Plan</span>
        </div>
      </header>

      <!-- STATISTIK 7 HARI -->
      <section class="card card-stat">
        <div class="card-stat-left">
          <h2 class="card-title">Statistik 7 Hari Terakhir</h2>
          <p class="card-sub">
            Total poin aktivitas olahraga kamu selama satu minggu terakhir.
          </p>

          <div class="canvas-box">
            <canvas id="weeklyChart"></canvas>
          </div>

          <div class="legend">
            <div class="legend-pill">
              <span class="legend-dot"></span>
              <span>Poin Harian</span>
            </div>
          </div>
        </div>

        <aside class="card-stat-aside">
          <div class="kpi-main">
            <small>Total Mingguan</small>
            <p class="big-num"><?= (int)array_sum($weeklyValues); ?></p>
          </div>
          <div class="kpi-row">
            <div>
              <small>Rata-rata / hari</small>
              <span><?= count($weeklyValues) ? round(array_sum($weeklyValues)/count($weeklyValues)) : 0; ?></span>
            </div>
            <div>
              <small>Total Poin</small>
              <span><?= (int)$totalPoints; ?></span>
            </div>
          </div>
          <p class="card-footnote">
            Insight mingguan membantu kamu melihat konsistensi latihan dan progres poin.
          </p>
        </aside>
      </section>

      <!-- SCHEDULE & KALENDER -->
      <section class="card card-schedule">
        <header class="card-head">
          <div>
            <h2 class="card-title">Schedule Aktivitas</h2>
            <p class="card-sub">Atur dan pantau jadwal olahraga kamu dalam satu tampilan kalender.</p>
          </div>
          <div class="badge-soft">
            <span>⏰</span>
            <span>Notifikasi pengingat 5 menit sebelum jadwal</span>
          </div>
        </header>

        <div class="schedule-shell">
          <div class="cal-head">
            <button class="cal-navbtn" type="button" id="prevMonthBtn">‹</button>
            <div class="cal-title" id="monthLabel"></div>
            <button class="cal-navbtn" type="button" id="nextMonthBtn">›</button>
          </div>

          <div class="cal-week">
            <span>Mon</span><span>Tue</span><span>Wed</span>
            <span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
          </div>

          <div class="cal-grid" id="calGrid"></div>
        </div>
      </section>

      <!-- RIWAYAT AKTIVITAS -->
      <section class="card card-history">
        <header class="card-head">
          <div>
            <h2 class="card-title">Riwayat Aktivitas</h2>
            <p class="card-sub">Detail semua sesi olahraga yang sudah kamu catat.</p>
          </div>
          <div class="badge-soft">
            <span>🏅</span>
            <span>Semua poin tersinkron ke dashboard</span>
          </div>
        </header>

        <div class="table-shell">
          <table>
            <thead>
            <tr>
              <th>Tanggal</th>
              <th>Jenis Aktivitas</th>
              <th>Durasi</th>
              <th>Poin</th>
              <th>Catatan</th>
              <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$aktivitas): ?>
              <tr>
                <td colspan="6" class="empty-row">
                  Belum ada aktivitas. Mulai catat dari halaman Tambah Aktivitas.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($aktivitas as $row): ?>
                <tr id="row-<?= (int)$row['id']; ?>">
                  <td><?= htmlspecialchars($row['tanggal']); ?></td>
                  <td><?= htmlspecialchars($row['jenis']); ?></td>
                  <td><?= (int)$row['durasi']; ?> menit</td>
                  <td>
                    <span class="badge-point"><?= (int)$row['poin']; ?> pts</span>
                  </td>
                  <td><?= nl2br(htmlspecialchars($row['catatan'])); ?></td>
                  <td>
                    <button
                      class="btn-pill btn-edit"
                      type="button"
                      onclick="window.location.href='activity_edit.php?id=<?= (int)$row['id']; ?>'">
                      Edit
                    </button>
                    <button
                      class="btn-pill btn-delete"
                      type="button"
                      onclick="deleteActivityAjax(<?= (int)$row['id']; ?>)">
                      Hapus
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="actions">
          <a href="activity_add.php" class="btn btn-primary">
            <span>+ Tambah Aktivitas</span>
          </a>

          <!-- TOMBOL EXPORT PDF RIWAYAT AKTIVITAS -->
          <a href="export_activities_pdf.php" target="_blank" class="btn btn-outline">
            📄 Export PDF Riwayat
          </a>

          <a href="dashboard.php" class="btn btn-outline">
            ← Kembali ke Dashboard
          </a>
        </div>
      </section>

      <!-- WORKOUT PLAN (DATA scheduleplan) -->
      <section class="card card-plan">
        <header class="card-head">
          <div>
            <h2 class="card-title">Daftar Workout Plan</h2>
            <p class="card-sub">
              Kombinasi preset resmi dan rencana custom milikmu.
            </p>
          </div>
          <div class="badge-soft">
            <span>📅</span>
            <span>Apply rencana agar muncul di kalender & My Schedule</span>
          </div>
        </header>

        <div class="plan-grid">
          <div class="plan-table-wrap">
            <table id="planTable">
              <thead>
              <tr>
                <th>Nama Rencana</th>
                <th>Jenis</th>
                <th>Target</th>
                <th>Durasi</th>
                <th>Level</th>
                <th>Aksi</th>
              </tr>
              </thead>
              <tbody>
              <?php if (empty($plans)): ?>
                <tr>
                  <td colspan="6" class="empty-row">
                    Belum ada workout plan. Buat dulu dari halaman Workout Plan.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($plans as $p): ?>
                  <tr>
                    <td><?= htmlspecialchars($p['PlanName']); ?></td>
                    <td><?= htmlspecialchars($p['PlanType']); ?></td>
                    <td><?= htmlspecialchars($p['TargetText']); ?></td>
                    <td><?= htmlspecialchars($p['DurationText']); ?></td>
                    <td><?= htmlspecialchars($p['LevelText']); ?></td>
                    <td>
                      <a href="activity_plan.php?plan_id=<?= (int)$p['Id']; ?>" class="btn btn-primary btn-xs">
                        Apply
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="plan-intro">
            <p>
              Pilih salah satu rencana di tabel, lalu gunakan tombol <strong>Apply</strong> untuk
              menerapkan ke jadwal. Sistem akan menyimpan ke kalender (ScheduleEvents) melalui
              halaman Workout Plan dan menampilkannya di Schedule Aktivitas.
            </p>
            <div class="actions" style="margin-top:12px;">
              <a href="activity_plan.php" class="btn btn-outline">
                Kelola Workout Plan
              </a>
            </div>
          </div>
        </div>
      </section>

    </div><!-- /.content-inner -->
  </main>
</div><!-- /.app -->

<!-- MODAL EVENT PER HARI -->
<div class="day-modal-backdrop" id="dayModal">
  <div class="day-modal">
    <header>
      <div>
        <strong id="dayModalTitle">Jadwal</strong>
        <div class="day-modal-meta" id="dayModalSub"></div>
      </div>
      <button type="button" onclick="closeDayModal()" style="border:none;background:transparent;font-size:18px;">✕</button>
    </header>
    <div id="dayModalList" class="day-modal-list"></div>
  </div>
</div>

<!-- TOAST -->
<div class="toast-float" id="toastFloat">
  <div class="ticon">✓</div>
  <div class="tmsg" id="toastMsg">Berhasil</div>
  <button class="tclose" type="button" onclick="hideToast()">✕</button>
</div>

<script>
const weeklyLabels = <?= json_encode($weeklyLabels); ?>;
const weeklyValues = <?= json_encode($weeklyValues); ?>;

function escapeHtml(s){
  return String(s ?? "")
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}

const toastFloat = document.getElementById("toastFloat");
const toastMsg   = document.getElementById("toastMsg");

function showToast(msg, icon="✓"){
  toastMsg.innerText = msg;
  toastFloat.querySelector(".ticon").innerText = icon;
  toastFloat.classList.add("show");
  clearTimeout(showToast._t);
  showToast._t = setTimeout(hideToast, 2600);
}
function hideToast(){
  toastFloat.classList.remove("show");
}

/* NAV SIDEBAR */
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

/* MINI CHART 7 HARI */
const canvas = document.getElementById("weeklyChart");
const ctx    = canvas.getContext("2d");

function resizeCanvas(){
  canvas.width  = canvas.parentElement.clientWidth - 20;
  canvas.height = 200;
}
window.addEventListener("resize", resizeCanvas);
resizeCanvas();

function drawChart(){
  ctx.clearRect(0,0,canvas.width,canvas.height);

  const pad   = 30;
  const maxVal= Math.max(...weeklyValues, 10);
  const stepX = (canvas.width - pad*2) / weeklyValues.length;

  ctx.strokeStyle = "#e5e7eb";
  ctx.beginPath();
  ctx.moveTo(pad, pad);
  ctx.lineTo(pad, canvas.height - pad);
  ctx.lineTo(canvas.width - pad, canvas.height - pad);
  ctx.stroke();

  weeklyValues.forEach((val,i)=>{
    const x = pad + stepX*i + stepX/2;
    const h = (val/maxVal) * (canvas.height - pad*2);
    const y = canvas.height - pad - h;

    ctx.fillStyle = "#22c55e";
    ctx.shadowColor = "rgba(34,197,94,.35)";
    ctx.shadowBlur  = 14;
    ctx.fillRect(x - 12, y, 24, h);
    ctx.shadowBlur  = 0;

    ctx.fillStyle = "#6b7280";
    ctx.font = "12px system-ui";
    ctx.textAlign = "center";
    ctx.fillText(weeklyLabels[i], x, canvas.height - pad + 14);
  });
}
drawChart();

/* SCHEDULE (Calendar + Activity highlight) */
let current        = new Date();
let monthEventsMap = {};
let activityCounts = {};
let selectedDate   = "";

const grid  = document.getElementById("calGrid");
const label = document.getElementById("monthLabel");

document.getElementById("prevMonthBtn").onclick =()=>{
  current.setMonth(current.getMonth()-1);
  loadMonth();
};
document.getElementById("nextMonthBtn").onclick =()=>{
  current.setMonth(current.getMonth()+1);
  loadMonth();
};

async function api(action, payload={}){
  const form = new FormData();
  form.append("action", action);
  Object.keys(payload).forEach(k=> form.append(k, payload[k]));
  const res  = await fetch("activity_list.php?ajax=1", { method:"POST", body: form });
  const data = await res.json();
  if (!data.ok) throw new Error(data.message || "Gagal");
  return data;
}

async function loadMonth(){
  const y = current.getFullYear();
  const m = current.getMonth()+1;
  const data = await api("list_month", {year:y, month:m});
  monthEventsMap = data.events || {};
  activityCounts = data.activityCounts || {};
  renderCalendar();
}

function renderCalendar(){
  grid.innerHTML = "";
  const y = current.getFullYear();
  const m = current.getMonth();

  label.textContent = current.toLocaleDateString("id-ID",{month:"long",year:"numeric"});

  const first = new Date(y,m,1);
  let startDay = first.getDay();
  startDay = (startDay===0)?7:startDay;

  const daysInMonth = new Date(y,m+1,0).getDate();
  for (let i=1;i<startDay;i++){
    const div = document.createElement("div");
    div.className = "day empty";
    grid.appendChild(div);
  }

  const todayStr = new Date().toISOString().slice(0,10);

  for (let d=1; d<=daysInMonth; d++){
    const dateStr = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const evCount = (monthEventsMap[dateStr]?.length)||0;
    const actCount= activityCounts[dateStr] || 0;

    const div = document.createElement("div");
    div.className = "day" +
      (dateStr===todayStr ? " today" : "") +
      (actCount ? " has-activity" : "");

    div.innerHTML = `
      <strong>${d}</strong>
      ${dateStr===todayStr ? `<div class="hint"></div>` : ``}
      ${actCount ? `<div class="activity-dot"></div>
                    <div class="activity-pill">🔥 ${actCount} aktivitas</div>` : ``}
      ${evCount ? `<div class="event-badge">📌 ${evCount} event</div>` : ``}
    `;
    div.onclick = ()=> openModal(dateStr);
    grid.appendChild(div);
  }
}

/* MODAL EVENT PER TANGGAL */
const dayModal      = document.getElementById('dayModal');
const dayModalTitle = document.getElementById('dayModalTitle');
const dayModalSub   = document.getElementById('dayModalSub');
const dayModalList  = document.getElementById('dayModalList');

async function openModal(dateStr){
  selectedDate = dateStr;
  const data = await api("list_day",{date:dateStr});
  const evs  = data.events || [];
  dayModalTitle.textContent = "Jadwal " + new Date(dateStr).toLocaleDateString("id-ID",{weekday:"long"});
  dayModalSub.textContent   = new Date(dateStr).toLocaleDateString("id-ID",{day:"numeric",month:"long",year:"numeric"});
  dayModalList.innerHTML    = "";
  if (!evs.length){
    dayModalList.innerHTML = '<div class="day-modal-meta">Belum ada jadwal untuk tanggal ini.</div>';
  } else {
    evs.forEach(ev=>{
      const row = document.createElement('div');
      row.className = 'day-modal-item';
      row.innerHTML = `
        <div>
          <strong>${escapeHtml(ev.title)}</strong>
          <div class="day-modal-meta">${ev.time} • ${ev.dur} mnt</div>
          ${ev.note ? `<div class="day-modal-meta">${escapeHtml(ev.note)}</div>` : ``}
        </div>
        <div>
          <button class="btn-xs-delete" onclick="deleteEvent(${ev.id})">Hapus</button>
        </div>
      `;
      dayModalList.appendChild(row);
    });
  }
  dayModal.style.display = "flex";
}
function closeDayModal(){
  dayModal.style.display = "none";
}

async function deleteEvent(id){
  if(!confirm("Hapus jadwal ini?")) return;
  try{
    await api("delete",{id:id});
    showToast("Jadwal dihapus");
    closeDayModal();
    loadMonth();
  }catch(e){
    showToast("Gagal menghapus jadwal","✖");
  }
}

/* DELETE AKTIVITAS */
async function deleteActivityAjax(id){
  if(!confirm("Hapus aktivitas ini?")) return;
  const row = document.getElementById("row-"+id);
  row.classList.add("row-remove");

  try{
    const data = await api("delete_activity",{aid:id});
    setTimeout(()=> row.remove(), 180);
    document.getElementById("totalPointText").innerText = data.totalPoints;
    showToast("Aktivitas dihapus ✓");
    drawChart();
    loadMonth();
  }catch(e){
    row.classList.remove("row-remove");
    showToast("Gagal menghapus", "✖");
  }
}

/* INIT */
loadMonth().catch(()=>{
  showToast("Gagal memuat kalender", "✖");
});

window.addEventListener("load", () => {
  requestAnimationFrame(() => {
    document.querySelector(".app")?.classList.add("page-ready");
  });
});
</script>
</body>
</html>
