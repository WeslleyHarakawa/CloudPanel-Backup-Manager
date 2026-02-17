<?php
require __DIR__ . "/app_bootstrap.php";
require_login();
require __DIR__ . "/config.php";
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newUser = trim($_POST["admin_user"] ?? "");
    $newPass = trim($_POST["admin_pass"] ?? "");
    if ($newUser === "") {
        $error = "Admin user is required.";
    } else {
        if ($newPass === "") {
            $newPass = $cfg_admin_pass;
        }
        $cfg_admin_user = $newUser;
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
            $success = "Admin user updated successfully. New settings will apply on next login.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Edit admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="p-4">
<div class="container" style="max-width:480px">
<h1 class="h4 mb-4">Edit admin</h1>
<?php if ($error !== ""): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success !== ""): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<form method="post">
<div class="mb-3">
<label class="form-label">Admin user</label>
<input class="form-control" name="admin_user" required value="<?php echo htmlspecialchars($cfg_admin_user); ?>">
</div>
<div class="mb-3">
<label class="form-label">New password (leave blank to keep current)</label>
<div class="input-group">
<input class="form-control" id="admin_pass" name="admin_pass" type="password">
<button class="btn btn-outline-secondary" type="button" id="toggleAdminPass"><i class="bi bi-eye"></i></button>
</div>
</div>
<button class="btn btn-primary" type="submit">Save</button>
<a class="btn btn-secondary" href="index.php">Back</a>
</form>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var btn = document.getElementById("toggleAdminPass");
  var input = document.getElementById("admin_pass");
  if (!btn || !input) return;
  btn.addEventListener("click", function () {
    if (input.type === "password") {
      input.type = "text";
      btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
      input.type = "password";
      btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
  });
});
</script>
</body>
</html>
