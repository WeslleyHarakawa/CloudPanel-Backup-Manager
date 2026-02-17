<?php
require __DIR__ . "/app_bootstrap.php";
require_login();
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $domain = trim($_POST["domain"] ?? "");
    $site_user = trim($_POST["site_user"] ?? "");
    $docroot = trim($_POST["docroot"] ?? "");
    $db_host = trim($_POST["db_host"] ?? "127.0.0.1");
    $db_name = trim($_POST["db_name"] ?? "");
    $db_user = trim($_POST["db_user"] ?? "");
    $db_pass = trim($_POST["db_pass"] ?? "");
    $auto_backup = isset($_POST["auto_backup"]) && $_POST["auto_backup"] === "1" ? 1 : 0;
    $auto_freq = $_POST["auto_backup_frequency"] ?? "daily";
    if ($auto_backup === 0) {
        $auto_freq = "daily";
    }
    if ($name !== "" && $domain !== "" && $site_user !== "" && $docroot !== "" && $db_name !== "" && $db_user !== "") {
        $stmt = $mysqli->prepare("INSERT INTO sites (name, domain, site_user, docroot, db_host, db_name, db_user, db_pass, auto_backup_enabled, auto_backup_frequency) VALUES (?,?,?,?,?,?,?,?,?, ?, ?)");
        $stmt->bind_param("ssssssssiss", $name, $domain, $site_user, $docroot, $db_host, $db_name, $db_user, $db_pass, $auto_backup, $auto_freq);
        $stmt->execute();
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>New site</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="p-4">
<div class="container">
<div class="d-flex justify-content-between align-items-center mb-4">
<h1 class="mb-0">Add site</h1>
<a class="btn btn-link" href="index.php">&laquo; Back</a>
</div>
<form method="post">
<div class="row">
<div class="col-md-6">
<div class="mb-3">
<label class="form-label">Name</label>
<input class="form-control" name="name" required>
</div>
<div class="mb-3">
<label class="form-label">Domain (e.g.: dashboard.casabaselettings.co.uk)</label>
<input class="form-control" name="domain" required>
</div>
<div class="mb-3">
<label class="form-label">Site user (e.g.: casabaselettings)</label>
<input class="form-control" name="site_user" required>
</div>
<div class="mb-3">
<label class="form-label">Document root (e.g.: /home/casabaselettings/htdocs/dashboard.casabaselettings.co.uk)</label>
<input class="form-control" name="docroot" required>
</div>
</div>
<div class="col-md-6">
<div class="mb-3">
<label class="form-label">DB host</label>
<input class="form-control" name="db_host" value="127.0.0.1" required>
</div>
<div class="mb-3">
<label class="form-label">DB name</label>
<input class="form-control" name="db_name" required>
</div>
<div class="mb-3">
<label class="form-label">DB user</label>
<input class="form-control" name="db_user" required>
</div>
<div class="mb-3">
<label class="form-label">DB password</label>
<div class="input-group">
<input class="form-control" id="db_pass" name="db_pass" type="password">
<button class="btn btn-outline-secondary" type="button" id="toggleDbPass"><i class="bi bi-eye"></i></button>
</div>
</div>
<div class="mb-3">
<label class="form-label d-block">Auto backup</label>
<div class="form-check form-check-inline">
  <input class="form-check-input" type="radio" name="auto_backup" id="autoOff" value="0" checked>
  <label class="form-check-label" for="autoOff">Off</label>
</div>
<div class="form-check form-check-inline">
  <input class="form-check-input" type="radio" name="auto_backup" id="autoOn" value="1">
  <label class="form-check-label" for="autoOn">On</label>
</div>
</div>
<div class="mb-3" id="autoFreqWrapper">
<label class="form-label">Frequency</label>
<select class="form-select" name="auto_backup_frequency">
  <option value="daily" selected>Daily</option>
  <option value="weekly">Weekly</option>
</select>
</div>
</div>
</div>
<button class="btn btn-primary" type="submit">Save</button>
<a class="btn btn-secondary" href="index.php">Cancel</a>
</form>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var btn = document.getElementById("toggleDbPass");
  var input = document.getElementById("db_pass");
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
  var autoOn = document.getElementById("autoOn");
  var autoOff = document.getElementById("autoOff");
  var freqWrapper = document.getElementById("autoFreqWrapper");
  function refreshFreq() {
    if (autoOn && autoOn.checked) {
      freqWrapper.style.display = "";
    } else {
      freqWrapper.style.display = "none";
    }
  }
  if (autoOn && autoOff && freqWrapper) {
    autoOn.addEventListener("change", refreshFreq);
    autoOff.addEventListener("change", refreshFreq);
    refreshFreq();
  }
});
</script>
</body>
</html>
