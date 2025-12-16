<?php
require 'config.php';
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$stmt = $conn->prepare("SELECT IsAdmin FROM Users WHERE Id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$me = $result->fetch_assoc();

if (!$me || (int)$me["IsAdmin"] !== 1) {
    die("Anda tidak memiliki akses ke halaman admin.");
}

$users = $conn->query("SELECT Id, Name, Npm, Email, IsActive, CreatedAt, IsAdmin FROM Users ORDER BY CreatedAt DESC");

$activities = $conn->query(
    "SELECT A.Id, U.Name, A.ActivityDate, A.SportType, A.DurationMinutes, A.Points
     FROM Activities A
     JOIN Users U ON A.UserId = U.Id
     ORDER BY A.ActivityDate DESC, A.Id DESC"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - FitPulse U</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Admin Dashboard</h1>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">Kembali ke Dashboard User</a>
    </div>

    <div class="mb-4">
        <h2 class="h5">Daftar Pengguna</h2>
        <table class="table table-striped table-sm table-bordered">
            <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>NPM</th>
                <th>Email</th>
                <th>Aktif</th>
                <th>Admin</th>
                <th>Created At</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($u = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int)$u["Id"]; ?></td>
                    <td><?php echo htmlspecialchars($u["Name"]); ?></td>
                    <td><?php echo htmlspecialchars($u["Npm"]); ?></td>
                    <td><?php echo htmlspecialchars($u["Email"]); ?></td>
                    <td><?php echo (int)$u["IsActive"]; ?></td>
                    <td><?php echo (int)$u["IsAdmin"]; ?></td>
                    <td><?php echo htmlspecialchars($u["CreatedAt"]); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div>
        <h2 class="h5">Semua Aktivitas</h2>
        <table class="table table-striped table-sm table-bordered">
            <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Nama User</th>
                <th>Tanggal</th>
                <th>Olahraga</th>
                <th>Durasi (menit)</th>
                <th>Poin</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($a = $activities->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int)$a["Id"]; ?></td>
                    <td><?php echo htmlspecialchars($a["Name"]); ?></td>
                    <td><?php echo htmlspecialchars($a["ActivityDate"]); ?></td>
                    <td><?php echo htmlspecialchars($a["SportType"]); ?></td>
                    <td><?php echo (int)$a["DurationMinutes"]; ?></td>
                    <td><?php echo (int)$a["Points"]; ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
