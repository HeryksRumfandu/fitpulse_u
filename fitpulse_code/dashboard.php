<?php
require 'config.php';
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$UserId  = (int) $_SESSION["user_id"];
$name    = $_SESSION["user_name"] ?? "User";
$initial = strtoupper(substr($name,0,1));
$activeNav = 'home';

/* ========= PROFILE UPDATE ========= */
$profileSaveError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_update'])) {
    $weight    = trim($_POST['weight'] ?? '');
    $height    = trim($_POST['height'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');

    if ($weight === '' || $height === '' || $birthdate === '') {
        $profileSaveError = 'Semua field profil harus diisi.';
    } else {
        $dbOk = false;
        if ($upd = @$conn->prepare("UPDATE Users SET weight = ?, height = ?, birthdate = ? WHERE Id = ?")) {
            $upd->bind_param("sssi", $weight, $height, $birthdate, $UserId);
            if ($upd->execute() && $upd->affected_rows > 0) $dbOk = true;
            $upd->close();
        }
        if (!$dbOk) {
            if ($ins = @$conn->prepare("INSERT INTO Users (Id, weight, height, birthdate) VALUES (?, ?, ?, ?)")) {
                $ins->bind_param("isss", $UserId, $weight, $height, $birthdate);
                if ($ins->execute()) $dbOk = true;
                $ins->close();
            }
        }
        if (!$dbOk) {
            $_SESSION['profile'] = ['weight'=>$weight,'height'=>$height,'birthdate'=>$birthdate];
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

/* ========= LOAD PROFILE ========= */
$userWeight    = '75 kg';
$userHeight    = '175 cm';
$userBirthdate = null;

if ($sel = @$conn->prepare("SELECT weight, height, birthdate FROM Users WHERE Id = ? LIMIT 1")) {
    $sel->bind_param("i", $UserId);
    if ($sel->execute()) {
        $res = $sel->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['weight']))    $userWeight    = $row['weight'];
            if (!empty($row['height']))    $userHeight    = $row['height'];
            if (!empty($row['birthdate']) && $row['birthdate'] != '0000-00-00')
                $userBirthdate = $row['birthdate'];
        }
    }
    $sel->close();
}

if (empty($userBirthdate) && isset($_SESSION['profile'])) {
    $p = $_SESSION['profile'];
    if (!empty($p['weight']))    $userWeight    = $p['weight'];
    if (!empty($p['height']))    $userHeight    = $p['height'];
    if (!empty($p['birthdate'])) $userBirthdate = $p['birthdate'];
}
if (empty($userBirthdate)) $userBirthdate = date('Y-m-d', strtotime('-29 years'));

function compute_age_from_birthdate($bd) {
    if (!$bd) return '';
    try {
        $dob  = new DateTime($bd);
        $now  = new DateTime();
        $diff = $now->diff($dob);
        return $diff->y . ' yrs';
    } catch (Exception $e) { return ''; }
}
$userAge = compute_age_from_birthdate($userBirthdate);

/* ========= TOTALS & LATEST ========= */
$totalPoints = 0; $totalActivities = 0;
if ($stmt = $conn->prepare(
    "SELECT COALESCE(SUM(Points),0) AS TotalPoints, COUNT(*) AS TotalActivities
     FROM Activities
     WHERE UserId = ?"
)) {
    $stmt->bind_param("i", $UserId);
    $stmt->execute();
    $sumResult       = $stmt->get_result()->fetch_assoc();
    $totalPoints     = (int)($sumResult["TotalPoints"] ?? 0);
    $totalActivities = (int)($sumResult["TotalActivities"] ?? 0);
    $stmt->close();
}

$latest = null;
if ($stmt2 = $conn->prepare(
    "SELECT ActivityDate, SportType, DurationMinutes, Points
     FROM Activities
     WHERE UserId = ?
     ORDER BY ActivityDate DESC, Id DESC
     LIMIT 5"
)) {
    $stmt2->bind_param("i", $UserId);
    $stmt2->execute();
    $latest = $stmt2->get_result();
    $stmt2->close();
}

/* ========= CHART FILTER MODE ========= */
$validModes = ['daily','weekly','monthly','yearly'];
$chartMode  = isset($_GET['mode']) ? strtolower($_GET['mode']) : 'weekly';
if (!in_array($chartMode,$validModes)) $chartMode = 'weekly';

$modeLabelText = [
  'daily'   => 'Daily',
  'weekly'  => 'Weekly',
  'monthly' => 'Monthly',
  'yearly'  => 'Yearly'
];
$modeLabel = $modeLabelText[$chartMode];

/* ========= CHART DATA ========= */
$chartLabels  = [];
$chartMinutes = [];

if ($chartMode === 'daily') {
    $today = date('Y-m-d');
    $map = [];

    if ($q = $conn->prepare(
        "SELECT HOUR(ActivityDate) AS h,
                COALESCE(SUM(DurationMinutes),0) AS mins
         FROM Activities
         WHERE UserId = ? AND DATE(ActivityDate) = ?
         GROUP BY HOUR(ActivityDate)
         ORDER BY HOUR(ActivityDate) ASC"
    )) {
        $q->bind_param("is",$UserId,$today);
        $q->execute();
        $res = $q->get_result();
        while($r = $res->fetch_assoc()){
            $map[(int)$r['h']] = (int)$r['mins'];
        }
        $q->close();
    }

    for($h=0;$h<24;$h++){
        $chartLabels[]  = sprintf('%02d:00',$h);
        $chartMinutes[] = $map[$h] ?? 0;
    }

} elseif ($chartMode === 'monthly') {
    $firstOfMonth = date('Y-m-01');
    $lastOfMonth  = date('Y-m-t');
    $map = [];

    if ($q = $conn->prepare(
        "SELECT DATE(ActivityDate) AS dt,
                COALESCE(SUM(DurationMinutes),0) AS mins
         FROM Activities
         WHERE UserId = ? AND DATE(ActivityDate) BETWEEN ? AND ?
         GROUP BY DATE(ActivityDate)
         ORDER BY DATE(ActivityDate) ASC"
    )) {
        $q->bind_param("iss",$UserId,$firstOfMonth,$lastOfMonth);
        $q->execute();
        $res = $q->get_result();
        while($r = $res->fetch_assoc()){
            $map[$r['dt']] = (int)$r['mins'];
        }
        $q->close();
    }

    $daysInMonth = (int)date('t');
    for($d=1;$d<=$daysInMonth;$d++){
        $dateStr = date('Y-m-'.sprintf('%02d',$d));
        $chartLabels[]  = $d;
        $chartMinutes[] = $map[$dateStr] ?? 0;
    }

} elseif ($chartMode === 'yearly') {
    $year = date('Y');
    $map = [];

    if ($q = $conn->prepare(
        "SELECT DATE_FORMAT(ActivityDate,'%Y-%m') AS ym,
                COALESCE(SUM(DurationMinutes),0) AS mins
         FROM Activities
         WHERE UserId = ? AND YEAR(ActivityDate) = ?
         GROUP BY DATE_FORMAT(ActivityDate,'%Y-%m')
         ORDER BY ym ASC"
    )) {
        $q->bind_param("ii",$UserId,$year);
        $q->execute();
        $res = $q->get_result();
        while($r = $res->fetch_assoc()){
            $map[$r['ym']] = (int)$r['mins'];
        }
        $q->close();
    }

    for($m=1;$m<=12;$m++){
        $ym = $year.'-'.sprintf('%02d',$m);
        $chartLabels[]  = date('M',strtotime($ym.'-01'));
        $chartMinutes[] = $map[$ym] ?? 0;
    }

} else {
    $map = [];
    if ($q = $conn->prepare(
        "SELECT DATE(ActivityDate) AS dt,
                COALESCE(SUM(DurationMinutes),0) AS mins
         FROM Activities
         WHERE UserId = ? AND DATE(ActivityDate) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(ActivityDate)
         ORDER BY DATE(ActivityDate) ASC"
    )) {
        $q->bind_param("i",$UserId);
        $q->execute();
        $res = $q->get_result();
        while($r = $res->fetch_assoc()){
            $map[$r['dt']] = (int)$r['mins'];
        }
        $q->close();
    }
    for($i=6;$i>=0;$i--){
        $d = date('Y-m-d',strtotime("-$i days"));
        $chartLabels[]  = date('d M',strtotime($d));
        $chartMinutes[] = $map[$d] ?? 0;
    }
}

/* ========= ATTENDANCE (current month) ========= */
$presentDays = []; $firstOfMonth = date('Y-m-01'); $lastOfMonth = date('Y-m-t');
if ($p = $conn->prepare(
    "SELECT DATE(ActivityDate) AS dt
     FROM Activities
     WHERE UserId = ? AND DATE(ActivityDate) BETWEEN ? AND ?
     GROUP BY DATE(ActivityDate)"
)) {
    $p->bind_param("iss", $UserId, $firstOfMonth, $lastOfMonth);
    $p->execute();
    $pres = $p->get_result();
    while ($r = $pres->fetch_assoc()) $presentDays[(int)date('j', strtotime($r['dt']))] = true;
    $p->close();
}
$daysInMonth = (int)date('t');

/* ========= SCHEDULE (selected date dari Activities) ========= */
$selectedDate  = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$scheduledDays = [];
$monthStart    = date('Y-m-01', strtotime($selectedDate));
$monthEnd      = date('Y-m-t', strtotime($selectedDate));

if ($sd = $conn->prepare(
    "SELECT DATE(ActivityDate) AS dt, COUNT(*) AS cnt
     FROM Activities
     WHERE UserId = ? AND DATE(ActivityDate) BETWEEN ? AND ?
     GROUP BY DATE(ActivityDate)"
)) {
    $sd->bind_param("iss", $UserId, $monthStart, $monthEnd);
    $sd->execute();
    $resSd = $sd->get_result();
    while ($r = $resSd->fetch_assoc()) {
        $dnum = (int)date('j', strtotime($r['dt']));
        $scheduledDays[$dnum] = (int)$r['cnt'];
    }
    $sd->close();
}

$daySchedule = [];
if ($ds = $conn->prepare(
    "SELECT Id, ActivityDate, SportType, DurationMinutes, Points
     FROM Activities
     WHERE UserId = ? AND DATE(ActivityDate) = ?
     ORDER BY ActivityDate ASC"
)) {
    $ds->bind_param("is", $UserId, $selectedDate);
    $ds->execute();
    $resDs = $ds->get_result();
    while ($r = $resDs->fetch_assoc()) $daySchedule[] = $r;
    $ds->close();
}

/* ========= MY SCHEDULE: PAKAI ACTIVITIES HARI INI ========= */
$todayDate   = date('Y-m-d');
$todayEvents = [];
if ($se = $conn->prepare("
    SELECT Id,
           DATE(ActivityDate) AS EventDate,
           SportType AS Title,
           '07:00:00' AS EventTime,
           DurationMinutes
    FROM Activities
    WHERE UserId = ? AND DATE(ActivityDate) = ?
    ORDER BY ActivityDate ASC, Id ASC
")) {
    $se->bind_param("is", $UserId, $todayDate);
    $se->execute();
    $resSe = $se->get_result();
    while ($r = $resSe->fetch_assoc()) {
        $todayEvents[] = $r;
    }
    $se->close();
}

/* ========= placeholders & level ========= */
$weeklyDistance = 56; $totalDistance = 236; $offlineHours = 10;
if     ($totalPoints >= 1000) $levelLabel = "Elite";
elseif ($totalPoints >= 500)  $levelLabel = "Pro";
elseif ($totalPoints >= 200)  $levelLabel = "Active";
elseif ($totalPoints >= 50)   $levelLabel = "Beginner";
else                          $levelLabel = "Newbie";

$progressPercent = 0;
if ($totalDistance > 0) {
    $progressPercent = (int)min(100, round(($weeklyDistance / $totalDistance) * 100));
}

$initial = strtoupper(substr($name,0,1));
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard FitPulse U</title>

<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-bold-rounded/css/uicons-bold-rounded.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
.sidebar .nav-ico {
  text-decoration: none;
  border: none;
  outline: none;
}
.sidebar .nav-ico:focus,
.sidebar .nav-ico:hover,
.sidebar .nav-ico:active {
  text-decoration: none;
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
      <a class="nav-ico<?php echo $activeNav==='home'?' active':'';?>" href="dashboard.php" title="Dashboard">
        <i class="fi fi-sr-home"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='analytics'?' active':'';?>" href="activity_list.php" title="Analytics">
        <i class="fi fi-sr-stats"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='workout'?' active':'';?>" href="activity_add.php" title="Tambah Aktivitas">
        <i class="fi fi-br-gym"></i>
      </a>
      <a class="nav-ico<?php echo $activeNav==='schedule'?' active':'';?>" href="activity_plan.php" title="Workout Plan">
        <i class="fi fi-rr-calendar"></i>
      </a>
    </nav>

    <nav class="bottom-nav">
      <a class="nav-ico<?php echo $activeNav==='settings'?' active':'';?>" href="profile.php#settings" title="Settings">
        <i class="fi fi-br-sun"></i>
      </a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="header">
      <div>
        <h3 class="title">Dashboard FitPulse U</h3>
        <div class="stat-muted">Selamat datang kembali, <strong><?php echo htmlspecialchars($name); ?></strong></div>
      </div>
      <div class="controls">
        <div class="btn-group">
          <button id="modeDropdown" type="button"
                  class="btn btn-sm btn-outline-secondary dropdown-toggle"
                  data-bs-toggle="dropdown" aria-expanded="false">
            <?php echo htmlspecialchars($modeLabel); ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="?mode=daily">Daily</a></li>
            <li><a class="dropdown-item" href="?mode=weekly">Weekly</a></li>
            <li><a class="dropdown-item" href="?mode=monthly">Monthly</a></li>
            <li><a class="dropdown-item" href="?mode=yearly">Yearly</a></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- summary cards -->
    <div class="row4">
      <div class="card">
        <div class="stat-muted">Total Poin</div>
        <div class="card-main-number"><?php echo $totalPoints; ?></div>
        <div class="stat-muted mt-1">Akumulasi semua aktivitas</div>
      </div>

      <div class="card">
        <div class="stat-muted">Total Aktivitas</div>
        <div class="card-main-number"><?php echo $totalActivities; ?></div>
        <div class="stat-muted mt-1">Sesi latihan tercatat</div>
      </div>

      <div class="card">
        <div class="stat-muted">Minggu ini (menit)</div>
        <div class="card-main-number"><?php echo array_sum($chartMinutes); ?> mnt</div>
        <div class="stat-muted mt-1">Total menit 7 hari terakhir</div>
      </div>

      <div class="card">
        <div class="stat-muted">Level Kebugaran</div>
        <div class="card-main-number"><?php echo $levelLabel; ?></div>
        <div class="stat-muted mt-1">Berdasar poin total</div>
      </div>
    </div>

    <!-- chart + latest -->
    <div class="mid">
      <div class="card">
        <div class="card-head-flex">
          <div>
            <strong>Workout Activity</strong>
            <div class="stat-muted">Durasi per hari (7 hari)</div>
          </div>
          <div class="stat-muted"><?php echo date('d M Y'); ?></div>
        </div>

        <div class="chart-wrapper">
          <canvas id="weeklyChart"></canvas>
        </div>

        <hr class="divider">

        <strong>Aktivitas Terbaru</strong>
        <ul class="latest-list">
          <?php if ($latest === null || $latest->num_rows === 0): ?>
            <li class="latest-item">
              <div>Belum ada data</div>
              <div class="stat-muted">—</div>
            </li>
          <?php else: while ($row = $latest->fetch_assoc()): ?>
            <li class="latest-item">
              <div>
                <div class="latest-title"><?php echo htmlspecialchars($row["SportType"]); ?></div>
                <div class="stat-muted latest-sub"><?php echo htmlspecialchars($row["ActivityDate"]); ?></div>
              </div>
              <div class="latest-right">
                <div class="latest-duration"><?php echo (int)$row["DurationMinutes"]; ?> mnt</div>
                <div class="stat-muted latest-points"><?php echo (int)$row["Points"]; ?> poin</div>
              </div>
            </li>
          <?php endwhile; endif; ?>
        </ul>
      </div>
    </div>

    <!-- helper cards -->
    <div class="helper-row">
      <div class="card">
        <strong>Offline Exercises</strong>
        <div class="helper-inline">
          <img src="project/lari.jpg" class="helper-thumb" alt="Cardio">
          <div>
            <div class="helper-title">Cardio Exercises</div>
            <div class="stat-muted helper-sub">Boost your energy</div>
          </div>
        </div>
      </div>

      <div class="card">
        <strong>Most Trending</strong>
        <div class="helper-images">
          <img src="project/lari.jpg" class="helper-img-square" alt="Trending 1">
          <img src="project/angkat.jpg" class="helper-img-square" alt="Trending 2">
        </div>
        <div class="stat-muted helper-meta">12M &nbsp;&nbsp; 55M</div>
      </div>
    </div>
  </main>

  <!-- RIGHT SIDEBAR -->
  <aside class="right">
    <!-- profile -->
    <div class="card profile-card">
      <div class="card-head-flex">
        <strong>My Profile</strong>
        <small class="stat-muted">...</small>
      </div>

      <div class="profile-top">
        <div class="profile-avatar"><?php echo htmlspecialchars($initial); ?></div>
        <div class="profile-info">
          <div class="profile-name"><?php echo htmlspecialchars($name); ?></div>
          <div class="stat-muted profile-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
        </div>
        <div>
          <button id="btn-edit-profile" class="btn btn-sm btn-outline-primary">Edit</button>
        </div>
      </div>

      <div class="profile-stats">
        <div class="stat">
          <div class="label">Weight</div>
          <strong><?php echo htmlspecialchars($userWeight); ?></strong>
        </div>
        <div class="stat">
          <div class="label">Height</div>
          <strong><?php echo htmlspecialchars($userHeight); ?></strong>
        </div>
        <div class="stat">
          <div class="label">Age</div>
          <strong><?php echo htmlspecialchars($userAge); ?></strong>
        </div>
      </div>

      <div id="profile-edit-form" class="profile-edit">
        <?php if ($profileSaveError): ?>
          <div class="text-danger small"><?php echo htmlspecialchars($profileSaveError); ?></div>
        <?php endif; ?>
        <form method="post" onsubmit="return confirm('Simpan perubahan profil?');">
          <input type="hidden" name="profile_update" value="1">
          <div class="profile-edit-row">
            <input name="weight" class="form-control form-control-sm" value="<?php echo htmlspecialchars($userWeight); ?>" placeholder="75 kg" required>
            <input name="height" class="form-control form-control-sm" value="<?php echo htmlspecialchars($userHeight); ?>" placeholder="175 cm" required>
          </div>
          <div class="profile-edit-row2">
            <input type="date" name="birthdate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($userBirthdate); ?>" required>
          </div>
          <div class="profile-edit-actions">
            <button type="button" id="btn-cancel-edit" class="btn btn-sm btn-outline-secondary">Batal</button>
            <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- calendar -->
    <div class="card">
      <div class="card-head-flex">
        <strong>Calendar</strong>
        <small class="stat-muted"><?php echo date('F Y', strtotime($selectedDate)); ?></small>
      </div>

      <div class="calendar-wrapper">
        <div class="attendance-grid">
          <?php
          for ($d = 1; $d <= $daysInMonth; $d++) {
              $cls = 'day';
              if (isset($presentDays[$d]))   $cls = 'present';
              if (isset($scheduledDays[$d])) $cls = 'present';
              $dateStr = date(
                  'Y-m-d',
                  strtotime(date('Y-m-01', strtotime($selectedDate)) . " +".($d-1)." days")
              );
              echo "<a href=\"?date={$dateStr}\" class=\"{$cls}\">{$d}</a>";
          }
          ?>
        </div>
      </div>
    </div>

    <!-- My Schedule -->
    <div class="card">
      <div class="card-head-flex">
        <strong>My Schedule</strong>
        <small class="stat-muted">
          <?php echo date('l, d M Y'); ?>
          · <a href="activity_plan.php"
               style="font-size:12px;font-weight:600;text-decoration:underline;color:#0d6efd;">
              Kelola Plan
            </a>
        </small>
      </div>

      <div class="schedule-list">
        <?php if (empty($todayEvents)): ?>
          <div class="stat-muted">Tidak ada jadwal untuk hari ini.</div>
        <?php else:
          $sessionNo = 1;
          foreach ($todayEvents as $sch):
            $dObj    = new DateTime($sch['EventDate']);
            $dayName = $dObj->format('l');
            $timeStr = substr($sch['EventTime'], 0, 5);
            $durStr  = (int)$sch['DurationMinutes'] . ' mnt';
        ?>
          <div class="schedule-item">
            <div>
              <div class="schedule-title"><?php echo htmlspecialchars($sch['Title']); ?></div>
              <div class="stat-muted schedule-sub">
                <?php
                  echo $dayName . ' • Sesi ' . $sessionNo . ' • ' . $timeStr . ' — ' . $durStr;
                ?>
              </div>
            </div>
          </div>
        <?php
            $sessionNo++;
          endforeach;
        endif; ?>
      </div>
    </div>

    <!-- progress -->
    <div class="card">
      <div class="card-head-flex">
        <strong>Progress</strong>
        <small class="stat-muted">Target mingguan</small>
      </div>

      <div class="progress-row">
        <div class="progress-main">
          <div class="progress-number"><?php echo htmlspecialchars($weeklyDistance); ?> kms</div>
          <div class="stat-muted">Weekly distance</div>

          <div class="progress-bar">
            <div class="progress-inner" style="width: <?php echo (int)$progressPercent; ?>%"></div>
          </div>

          <div class="progress-meta stat-muted">
            Total distance: <strong><?php echo htmlspecialchars($totalDistance); ?> kms</strong><br>
            Offline cardio: <strong><?php echo htmlspecialchars($offlineHours); ?> hrs</strong>
          </div>
        </div>

        <div class="progress-target">
          <?php echo (int)$weeklyDistance; ?>
        </div>
      </div>
    </div>
  </aside>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const btn    = document.getElementById('btn-edit-profile');
  const cancel = document.getElementById('btn-cancel-edit');
  const form   = document.getElementById('profile-edit-form');
  if (btn && form) {
    btn.addEventListener('click', ()=> form.style.display =
      form.style.display === 'block' ? 'none' : 'block');
  }
  if (cancel && form) cancel.addEventListener('click', ()=> form.style.display = 'none');
})();

(function(){
  const canvas = document.getElementById('weeklyChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const labels = <?php echo json_encode($chartLabels); ?>;
  const data   = <?php echo json_encode($chartMinutes); ?>;

  const gradient = ctx.createLinearGradient(0,0,0,220);
  gradient.addColorStop(0,'rgba(34,197,94,0.20)');
  gradient.addColorStop(0.6,'rgba(34,211,238,0.10)');
  gradient.addColorStop(1,'rgba(34,211,238,0.03)');

  new Chart(ctx,{
    type:'line',
    data:{
      labels:labels,
      datasets:[{
        label:'Menit per hari',
        data:data,
        tension:0.35,
        fill:true,
        backgroundColor:gradient,
        borderColor:'#16a34a',
        borderWidth:3,
        pointRadius:4,
        pointBackgroundColor:'#ffffff',
        pointBorderColor:'#16a34a'
      }]
    },
    options:{
      maintainAspectRatio:false,
      scales:{
        x:{grid:{display:false},ticks:{color:'#6b7280'}},
        y:{beginAtZero:true,ticks:{color:'#6b7280',stepSize:20},grid:{color:'rgba(2,6,23,0.06)'}}
      },
      plugins:{legend:{display:false}}
    }
  });
})();
</script>

</body>
</html>
