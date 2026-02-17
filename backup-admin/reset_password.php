<?php
require __DIR__ . "/app_bootstrap.php";
require __DIR__ . "/config.php";
$systemTitle = get_setting("system_title", "Backup Admin");
$token = $_GET["token"] ?? "";
$error = "";
$message = "";
$valid = false;
$storedToken = get_setting("password_reset_token", "");
$expires = (int)get_setting("password_reset_expires", "0");
if ($token !== "" && $storedToken !== "" && hash_equals($storedToken, $token) && $expires > time()) {
    $valid = true;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["token"] ?? "";
    $newPass = trim($_POST["password"] ?? "");
    $storedToken = get_setting("password_reset_token", "");
    $expires = (int)get_setting("password_reset_expires", "0");
    if ($token === "" || $storedToken === "" || !hash_equals($storedToken, $token) || $expires <= time()) {
        $error = "Reset link is invalid or expired.";
    } elseif ($newPass === "") {
        $error = "Password is required.";
    } else {
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
            set_setting("password_reset_token", "");
            set_setting("password_reset_expires", "0");
            $message = "Password updated successfully. You can now sign in.";
            $valid = false;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($systemTitle); ?> - Reset password</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container">
<div class="row justify-content-center">
<div class="col-md-4">
<div class="text-center mb-4">
<h1 class="h4 mb-1"><?php echo htmlspecialchars($systemTitle); ?></h1>
<div class="text-muted">Set a new admin password</div>
</div>
<div class="card p-4 shadow-sm">
<?php if ($error !== ""): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($message !== ""): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($valid): ?>
<form method="post">
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
<div class="mb-3">
<label class="form-label">New password</label>
<div class="input-group">
<input class="form-control" id="new_pass" name="password" type="password" required>
<button class="btn btn-outline-secondary" type="button" id="toggleNewPass"><i class="bi bi-eye"></i></button>
</div>
</div>
<button class="btn btn-primary w-100" type="submit">Save new password</button>
</form>
<?php else: ?>
<div class="mb-3">
<p class="mb-0">This reset link is invalid or has expired.</p>
</div>
<div class="text-center">
<a href="forgot_password.php">Request a new reset link</a>
</div>
<?php endif; ?>
<div class="mt-3 text-center">
<a href="index.php">Back to login</a>
</div>
</div>
</div>
</div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var btn = document.getElementById("toggleNewPass");
  var input = document.getElementById("new_pass");
  if (btn && input) {
    btn.addEventListener("click", function () {
      if (input.type === "password") {
        input.type = "text";
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
      } else {
        input.type = "password";
        btn.innerHTML = '<i class="bi bi-eye"></i>';
      }
    });
  }
});
</script>
</body>
</html>

