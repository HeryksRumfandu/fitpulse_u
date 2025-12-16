<?php
require 'config.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

$errors  = [];
$success = "";

// PROSES FORM REGISTER
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Ambil input utama
    $Name     = trim($_POST["Name"] ?? "");
    $Npm      = trim($_POST["Npm"] ?? "");
    $Email    = trim($_POST["Email"] ?? "");
    $Password = trim($_POST["Password"] ?? "");
    $Confirm  = trim($_POST["ConfirmPassword"] ?? "");

    // Input profil tambahan
    $Goal           = trim($_POST["Goal"] ?? "");
    $PreferredSport = trim($_POST["PreferredSport"] ?? "");
    $Bio            = trim($_POST["Bio"] ?? "");

    // Validasi sederhana
    if ($Name === "")  { $errors[] = "Nama tidak boleh kosong."; }
    if ($Npm === "")   { $errors[] = "NPM tidak boleh kosong."; }
    if ($Email === "") { $errors[] = "Email tidak boleh kosong."; }

    if (!filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }

    if ($Password === "" || strlen($Password) < 6) {
        $errors[] = "Password minimal 6 karakter.";
    }
    if ($Password !== $Confirm) {
        $errors[] = "Konfirmasi password tidak sama.";
    }

    // Cek email sudah dipakai atau belum
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT Id FROM users WHERE Email = ? LIMIT 1");
        $stmt->bind_param("s", $Email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email sudah terdaftar, gunakan email lain.";
        }
        $stmt->close();
    }

    // Proses upload foto profil (opsional) -> folder /profile
    $photoPath = null;
    if (empty($errors) && !empty($_FILES['Photo']['name'])) {
        $uploadDir = __DIR__ . '/profile/';
        $ext       = pathinfo($_FILES['Photo']['name'], PATHINFO_EXTENSION);
        $extLower  = strtolower($ext);
        $allowed   = ['jpg','jpeg','png','webp'];

        if (!in_array($extLower, $allowed)) {
            $errors[] = "Format foto tidak didukung. Gunakan JPG, JPEG, PNG, atau WEBP.";
        } elseif ($_FILES['Photo']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Ukuran foto maksimal 2MB.";
        } elseif (!is_dir($uploadDir)) {
            $errors[] = "Folder upload belum tersedia.";
        } else {
            $newName    = 'user_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extLower;
            $targetFile = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['Photo']['tmp_name'], $targetFile)) {
                $photoPath = 'profile/' . $newName;
            } else {
                $errors[] = "Gagal mengupload foto profil.";
            }
        }
    }

    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {

        // 1) Hash password
        $PasswordHash = password_hash($Password, PASSWORD_DEFAULT);

        // 2) Status awal BELUM aktif (0) dan token aktivasi acak
        $IsActive        = 0;
        $ActivationToken = bin2hex(random_bytes(32));
        $IsAdmin         = 0;

        // 3) Insert ke tabel users
        $sql = "INSERT INTO users 
                (Name, Npm, Email, PhotoPath, Goal, PreferredSport, Bio,
                 PasswordHash, IsActive, ActivationToken, CreatedAt, IsAdmin)
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssisi",
            $Name,
            $Npm,
            $Email,
            $photoPath,
            $Goal,
            $PreferredSport,
            $Bio,
            $PasswordHash,
            $IsActive,
            $ActivationToken,
            $IsAdmin
        );

        if ($stmt->execute()) {
            $newUserId = $stmt->insert_id;
            $stmt->close();

            // 4) Kirim EMAIL AKTIVASI via PHPMailer
            $baseUrl        = "http://localhost/fitpulse_u";
            $activationLink = $baseUrl . "/activate.php?token=" . urlencode($ActivationToken);

            $mail = new PHPMailer(true);

            try {
                // Konfigurasi SMTP Gmail
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'fitpulse.u.app@gmail.com';
                $mail->Password   = 'tpостqoxfyhjpxie';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Pengirim & penerima
                $mail->setFrom('fitpulse.u.app@gmail.com', 'FITPULSE U');
                $mail->addAddress($Email, $Name);

                // Konten email
                $mail->isHTML(true);
                $mail->Subject = 'Aktivasi Akun FITPULSE U';
                $mail->Body    = "
                    <p>Halo <strong>" . htmlspecialchars($Name) . "</strong>,</p>
                    <p>Terima kasih sudah mendaftar di <strong>FITPULSE U</strong>.</p>
                    <p>Silakan klik link berikut untuk mengaktifkan akun:</p>
                    <p><a href='" . $activationLink . "' style='display:inline-block;padding:10px 18px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:6px;'>Aktivasi Akun</a></p>
                    <p>Jika tombol tidak bisa diklik, salin dan tempel URL berikut ke browser:</p>
                    <p>de>" . htmlspecialchars($activationLink) . "</code></p>
                    <p>Jika kamu tidak merasa mendaftar, abaikan email ini.</p>
                ";
                $mail->AltBody = "Halo $Name,\n\n" .
                                 "Terima kasih sudah mendaftar di FITPULSE U.\n" .
                                 "Silakan buka link ini untuk aktivasi akun:\n$activationLink\n\n" .
                                 "Jika kamu tidak merasa mendaftar, abaikan email ini.";

                $mail->send();
            } catch (Exception $e) {
                // Optional: log error tapi tidak batalkan registrasi
                // error_log('Mailer Error: ' . $mail->ErrorInfo);
            }

            // 5) Arahkan ke halaman informasi
            header('Location: activation_pending.php');
            exit;

        } else {
            $errors[] = "Terjadi kesalahan saat menyimpan data.";
            $stmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi Akun FITPULSE_U</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>

<div class="register-page">
    <div class="register-card">

        <!-- HEADER BRAND -->
        <div class="register-hero">
            <div class="brand-row">
                <div class="brand-circle">
                    <div class="pulse-line"></div>
                </div>
                <div class="brand-text">
                    <div class="brand-main">FITPULSE_U</div>
                    <div class="brand-sub">FEEL THE PULSE</div>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php" enctype="multipart/form-data" class="register-form">

            <!-- FOTO PROFIL -->
            <div class="profile-photo-wrapper">
                <label class="profile-photo-label">
                    <div class="profile-photo-circle">
                        <span class="profile-photo-plus">+</span>
                    </div>
                    <span class="profile-photo-text">Upload foto profil (opsional)</span>
                    <input type="file" name="Photo" accept="image/*" class="profile-photo-input">
                </label>
            </div>

            <!-- DATA DASAR -->
            <div class="field-row">
                <div class="field">
                    <label>Nama lengkap</label>
                    <input type="text" name="Name"
                           value="<?php echo htmlspecialchars($_POST['Name'] ?? ''); ?>" required>
                </div>
                <div class="field">
                    <label>NPM</label>
                    <input type="text" name="Npm"
                           value="<?php echo htmlspecialchars($_POST['Npm'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="field">
                <label>Email</label>
                <input type="email" name="Email"
                       value="<?php echo htmlspecialchars($_POST['Email'] ?? ''); ?>" required>
            </div>

            <!-- GOAL / AKTIVITAS / BIO -->
            <div class="field">
                <label>Tujuan utama kamu?</label>
                <div class="goal-grid">
                    <label class="goal-card">
                        <input type="radio" name="Goal" value="Turunkan berat badan"
                            <?php echo (($_POST['Goal'] ?? '') === 'Turunkan berat badan') ? 'checked' : ''; ?> hidden>
                        <div class="goal-title">Turunkan berat badan</div>
                        <div class="goal-desc">Fokus kalori terbakar & cardio.</div>
                    </label>
                    <label class="goal-card">
                        <input type="radio" name="Goal" value="Tingkatkan performa olahraga"
                            <?php echo (($_POST['Goal'] ?? '') === 'Tingkatkan performa olahraga') ? 'checked' : ''; ?> hidden>
                        <div class="goal-title">Tingkatkan performa</div>
                        <div class="goal-desc">Latihan kekuatan & daya tahan.</div>
                    </label>
                    <label class="goal-card">
                        <input type="radio" name="Goal" value="Kesehatan & rutin bergerak"
                            <?php echo (($_POST['Goal'] ?? '') === 'Kesehatan & rutin bergerak') ? 'checked' : ''; ?> hidden>
                        <div class="goal-title">Kesehatan umum</div>
                        <div class="goal-desc">Tetap aktif setiap hari.</div>
                    </label>
                </div>
            </div>

            <div class="field">
                <label>Aktivitas favorit</label>
                <select name="PreferredSport">
                    <option value="">Pilih salah satu</option>
                    <?php
                    $sports = [
                        "Jogging",
                        "Futsal / Sepak bola",
                        "Gym / Weight training",
                        "Basket",
                        "Yoga / Stretching"
                    ];
                    $selectedSport = $_POST['PreferredSport'] ?? "";
                    foreach ($sports as $s) {
                        $sel = ($s === $selectedSport) ? "selected" : "";
                        echo "<option value=\"".htmlspecialchars($s)."\" $sel>"
                             .htmlspecialchars($s)."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="field">
                <label>Bio singkat (opsional)</label>
                <textarea name="Bio" rows="2"
                          placeholder="Contoh: Mahasiswa aktif suka futsal dan lari pagi."><?php
                    echo htmlspecialchars($_POST['Bio'] ?? '');
                ?></textarea>
            </div>

            <!-- PASSWORD -->
            <div class="field-row">
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="Password" required>
                </div>
                <div class="field">
                    <label>Konfirmasi password</label>
                    <input type="password" name="ConfirmPassword" required>
                </div>
            </div>

            <button type="submit" class="btn-primary">Buat Akun</button>

            <p class="login-link">
                Sudah punya akun? <a href="login.php">Masuk di sini</a>
            </p>
        </form>
    </div>
</div>

</body>
</html>
