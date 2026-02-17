<?php
require __DIR__ . "/app_bootstrap.php";
require_login();
require __DIR__ . "/config.php";
$tab = isset($_GET["tab"]) ? $_GET["tab"] : "general";
$message = "";
$error = "";
$overrideTwofaSecret = null;
$overrideTwofaEnabled = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $section = $_POST["section"] ?? "";
    if ($section === "general") {
        $title = trim($_POST["system_title"] ?? "");
        $time = trim($_POST["auto_backup_time"] ?? "");
        $tz = trim($_POST["backup_timezone"] ?? "");
        $brandName = trim($_POST["brand_name"] ?? "");
        $brandUrl = trim($_POST["brand_url"] ?? "");
        if ($title === "") {
            $title = "Backup Admin";
        }
        if (!preg_match("/^\d{2}:\d{2}$/", $time)) {
            $time = "03:00";
        }
        if ($tz === "") {
            $tz = "UTC";
        }
        if ($brandName === "") {
            $brandName = "PMS Dash";
        }
        if ($brandUrl === "") {
            $brandUrl = "https://pmsdash.com";
        }
        set_setting("system_title", $title);
        set_setting("auto_backup_time", $time);
        set_setting("backup_timezone", $tz);
        set_setting("brand_name", $brandName);
        set_setting("brand_url", $brandUrl);
        $message = "General settings saved.";
        $tab = "general";
    } elseif ($section === "admin") {
        $newName = trim($_POST["admin_name"] ?? "");
        $newEmail = trim($_POST["admin_email"] ?? "");
        $newPass = trim($_POST["admin_pass"] ?? "");
        if ($newEmail === "") {
            $error = "Admin email is required.";
            $tab = "admin";
        } else {
            if ($newName === "") {
                $newName = "Admin";
            }
            if ($newPass === "") {
                $newPass = $cfg_admin_pass;
            }
            $cfg_admin_user = $newEmail;
            $cfg_admin_pass = $newPass;
            $configPath = __DIR__ . "/config.php";
            $content = "<?php\n";
            $content .= '$cfg_db_host = ' . var_export($cfg_db_host, true) . ";\n";
            $content .= '$cfg_db_name = ' . var_export($cfg_db_name, true) . ";\n";
            $content .= '$cfg_db_user = ' . var_export($cfg_db_user, true) . ";\n";
            $content .= '$cfg_db_pass = ' . var_export($cfg_db_pass, true) . ";\n";
            $content .= '$cfg_admin_user = ' . var_export($cfg_admin_user, true) . ";\n";
            $content .= '$cfg_admin_pass = ' . var_export($cfg_admin_pass, true) . ";\n";
            $content .= '$cfg_base_path = ' . var_export($cfg_base_path, true) . ";\n";
            $content .= '$cfg_backup_base = ' . var_export($cfg_backup_base, true) . ";\n";
            if (file_put_contents($configPath, $content) === false) {
                $error = "Failed to write config file.";
            } else {
                set_setting("admin_name", $newName);
                $message = "Admin user updated. New settings apply on next login.";
            }
            $tab = "admin";
        }
    } elseif ($section === "notification") {
        $email = trim($_POST["notify_email"] ?? "");
        $host = trim($_POST["smtp_host"] ?? "");
        $port = trim($_POST["smtp_port"] ?? "");
        $user = trim($_POST["smtp_user"] ?? "");
        $pass = trim($_POST["smtp_pass"] ?? "");
        $enc = trim($_POST["smtp_encryption"] ?? "");
        set_setting("notify_email", $email);
        set_setting("smtp_host", $host);
        set_setting("smtp_port", $port);
        set_setting("smtp_user", $user);
        set_setting("smtp_pass", $pass);
        set_setting("smtp_encryption", $enc);
        $message = "Notification settings saved.";
        $tab = "notification";
    } elseif ($section === "security") {
        $enable2fa = isset($_POST["enable_2fa"]) && $_POST["enable_2fa"] === "1" ? 1 : 0;
        $secret = get_setting("twofa_secret", "");
        if ($enable2fa && $secret === "") {
            $secret = bin2hex(random_bytes(10));
            set_setting("twofa_secret", $secret);
        }
        $overrideTwofaSecret = $secret;
        if (!$enable2fa) {
            set_setting("twofa_enabled", "0");
            $overrideTwofaEnabled = false;
            $message = "2FA disabled.";
        } else {
            $code = trim($_POST["twofa_code"] ?? "");
            if ($code === "") {
                $error = "Enter the code from Google Authenticator to enable 2FA.";
                $overrideTwofaEnabled = true;
            } elseif (!verify_totp_code($secret, $code)) {
                $error = "Invalid authentication code. Check the app and try again.";
                $overrideTwofaEnabled = true;
            } else {
                set_setting("twofa_enabled", "1");
                $overrideTwofaEnabled = true;
                $message = "2FA enabled successfully.";
            }
        }
        $tab = "security";
    }
}
$systemTitle = get_setting("system_title", "Backup Admin");
$autoTime = get_setting("auto_backup_time", "03:00");
$backupTz = get_setting("backup_timezone", "UTC");
$brandName = get_setting("brand_name", "PMS Dash");
$brandUrl = get_setting("brand_url", "https://pmsdash.com");
$adminName = get_setting("admin_name", "Admin");
$notifyEmail = get_setting("notify_email", "");
$smtpHost = get_setting("smtp_host", "");
$smtpPort = get_setting("smtp_port", "");
$smtpUser = get_setting("smtp_user", "");
$smtpPass = get_setting("smtp_pass", "");
$smtpEnc = get_setting("smtp_encryption", "");
$twofaSecret = get_setting("twofa_secret", "");
$twofaEnabled = get_setting("twofa_enabled", "0") === "1";
if ($overrideTwofaSecret !== null) {
    $twofaSecret = $overrideTwofaSecret;
}
if ($overrideTwofaEnabled !== null) {
    $twofaEnabled = (bool)$overrideTwofaEnabled;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($systemTitle); ?> - Settings</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="p-4">
<div class="container">
<div class="d-flex justify-content-between align-items-center mb-4">
<h1 class="h3 mb-0"><?php echo htmlspecialchars($systemTitle); ?> - Settings</h1>
<a class="btn btn-secondary" href="index.php">Back</a>
</div>
<?php if ($error !== ""): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($message !== ""): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<ul class="nav nav-tabs mb-3">
<li class="nav-item">
<a class="nav-link <?php echo $tab === 'general' ? 'active' : ''; ?>" href="?tab=general">General</a>
</li>
<li class="nav-item">
<a class="nav-link <?php echo $tab === 'admin' ? 'active' : ''; ?>" href="?tab=admin">Admin user</a>
</li>
<li class="nav-item">
<a class="nav-link <?php echo $tab === 'notification' ? 'active' : ''; ?>" href="?tab=notification">Notification</a>
</li>
<li class="nav-item">
<a class="nav-link <?php echo $tab === 'security' ? 'active' : ''; ?>" href="?tab=security">Security</a>
</li>
</ul>
<?php if ($tab === "general"): ?>
<form method="post" class="card p-3">
<input type="hidden" name="section" value="general">
<div class="mb-3">
<label class="form-label">System title</label>
<input class="form-control" name="system_title" value="<?php echo htmlspecialchars($systemTitle); ?>">
</div>
<div class="mb-3">
<label class="form-label">Footer brand name</label>
<input class="form-control" name="brand_name" value="<?php echo htmlspecialchars($brandName); ?>">
</div>
<div class="mb-3">
<label class="form-label">Footer brand URL</label>
<input class="form-control" name="brand_url" value="<?php echo htmlspecialchars($brandUrl); ?>">
</div>
<div class="mb-3">
<label class="form-label">Automatic backup time (HH:MM)</label>
<input class="form-control" name="auto_backup_time" value="<?php echo htmlspecialchars($autoTime); ?>">
</div>
<div class="mb-3">
<label class="form-label">Backup timezone</label>
<input class="form-control" name="backup_timezone" value="<?php echo htmlspecialchars($backupTz); ?>" placeholder="UTC, Europe/London, America/Sao_Paulo">
</div>
<button class="btn btn-primary" type="submit">Save</button>
</form>
<?php elseif ($tab === "admin"): ?>
<form method="post" class="card p-3" style="max-width:520px">
<input type="hidden" name="section" value="admin">
<div class="mb-3">
<label class="form-label">Admin name</label>
<input class="form-control" name="admin_name" value="<?php echo htmlspecialchars($adminName); ?>">
</div>
<div class="mb-3">
<label class="form-label">Admin email</label>
<input class="form-control" type="email" name="admin_email" required value="<?php echo htmlspecialchars($cfg_admin_user); ?>">
</div>
<div class="mb-3">
<label class="form-label">New password (leave blank to keep)</label>
<div class="input-group">
<input class="form-control" id="admin_pass" name="admin_pass" type="password">
<button class="btn btn-outline-secondary" type="button" id="toggleAdminPass"><i class="bi bi-eye"></i></button>
</div>
</div>
<button class="btn btn-primary" type="submit">Save</button>
</form>
<?php elseif ($tab === "notification"): ?>
<form method="post" class="card p-3">
<input type="hidden" name="section" value="notification">
<div class="mb-3">
<label class="form-label">Notification email</label>
<input class="form-control" type="email" name="notify_email" value="<?php echo htmlspecialchars($notifyEmail); ?>">
</div>
<div class="row">
<div class="col-md-6">
<div class="mb-3">
<label class="form-label">SMTP host</label>
<input class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($smtpHost); ?>">
</div>
</div>
<div class="col-md-2">
<div class="mb-3">
<label class="form-label">Port</label>
<input class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($smtpPort); ?>">
</div>
</div>
<div class="col-md-4">
<div class="mb-3">
<label class="form-label">Encryption</label>
<select class="form-select" name="smtp_encryption">
<option value="" <?php echo $smtpEnc === '' ? 'selected' : ''; ?>>None</option>
<option value="ssl" <?php echo $smtpEnc === 'ssl' ? 'selected' : ''; ?>>SSL</option>
<option value="tls" <?php echo $smtpEnc === 'tls' ? 'selected' : ''; ?>>TLS</option>
</select>
</div>
</div>
</div>
<div class="mb-3">
<label class="form-label">SMTP user</label>
<input class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($smtpUser); ?>">
</div>
<div class="mb-3">
<label class="form-label">SMTP password</label>
<div class="input-group">
<input class="form-control" id="smtp_pass" name="smtp_pass" type="password" value="<?php echo htmlspecialchars($smtpPass); ?>">
<button class="btn btn-outline-secondary" type="button" id="toggleSmtpPass"><i class="bi bi-eye"></i></button>
</div>
</div>
<button class="btn btn-primary" type="submit">Save</button>
</form>
<?php elseif ($tab === "security"): ?>
<form method="post" class="card p-3" style="max-width:520px">
<input type="hidden" name="section" value="security">
<div class="form-check form-switch mb-3">
  <input class="form-check-input" type="checkbox" id="enable2fa" name="enable_2fa" value="1" <?php echo $twofaEnabled ? 'checked' : ''; ?>>
  <label class="form-check-label" for="enable2fa">Enable Google Authenticator 2FA</label>
</div>
<?php if ($twofaSecret !== ""): ?>
<div class="mb-3">
<label class="form-label">Current secret</label>
<input class="form-control" value="<?php echo htmlspecialchars($twofaSecret); ?>" readonly>
<div class="form-text">Scan the QR code below in Google Authenticator or add this key manually.</div>
</div>
<?php
$secretBytes = totp_base32_decode($twofaSecret);
$secretB32 = $secretBytes !== "" ? totp_base32_encode($secretBytes) : "";
$issuer = $systemTitle;
$account = $cfg_admin_user;
$otpauth = "";
if ($secretB32 !== "" && $account !== "") {
    $otpauth = "otpauth://totp/" . rawurlencode($issuer) . ":" . rawurlencode($account) . "?secret=" . $secretB32 . "&issuer=" . rawurlencode($issuer) . "&digits=6&period=30";
}
?>
<?php if ($otpauth !== ""): ?>
<div class="mb-3 d-flex justify-content-center">
<div id="qrcode" data-otpauth="<?php echo htmlspecialchars($otpauth, ENT_QUOTES); ?>"></div>
</div>
<div class="form-text text-center">
Install Google Authenticator on your phone:<br>
Android: Google Play Store · iOS: App Store. Then scan the QR code above.
</div>
<?php endif; ?>
<?php else: ?>
<div class="mb-3">
<div class="form-text">When enabling 2FA a secret key will be generated.</div>
</div>
<?php endif; ?>
<?php if ($twofaSecret !== ""): ?>
<div class="mb-3">
<label class="form-label">Authenticator code</label>
<input class="form-control" name="twofa_code" inputmode="numeric" pattern="\d*" autocomplete="one-time-code">
<div class="form-text">Enter the 6-digit code from Google Authenticator to confirm.</div>
</div>
<?php endif; ?>
<button class="btn btn-primary" type="submit">Save</button>
</form>
<?php endif; ?>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var adminBtn = document.getElementById("toggleAdminPass");
  var adminInput = document.getElementById("admin_pass");
  if (adminBtn && adminInput) {
    adminBtn.addEventListener("click", function () {
      if (adminInput.type === "password") {
        adminInput.type = "text";
        adminBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
      } else {
        adminInput.type = "password";
        adminBtn.innerHTML = '<i class="bi bi-eye"></i>';
      }
    });
  }
  var smtpBtn = document.getElementById("toggleSmtpPass");
  var smtpInput = document.getElementById("smtp_pass");
  if (smtpBtn && smtpInput) {
    smtpBtn.addEventListener("click", function () {
      if (smtpInput.type === "password") {
        smtpInput.type = "text";
        smtpBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
      } else {
        smtpInput.type = "password";
        smtpBtn.innerHTML = '<i class=\"bi bi-eye\"></i>';
      }
    });
  }
  var qrEl = document.getElementById("qrcode");
  if (qrEl && qrEl.dataset.otpauth && typeof QRCode !== "undefined") {
    new QRCode(qrEl, {
      text: qrEl.dataset.otpauth,
      width: 180,
      height: 180
    });
  }
});
</script>
</body>
</html>
