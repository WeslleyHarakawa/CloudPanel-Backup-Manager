<?php
require __DIR__ . "/app_bootstrap.php";
require_login();
if (!function_exists("format_bytes")) {
    function format_bytes($bytes)
    {
        $bytes = (float)$bytes;
        if ($bytes <= 0) {
            return "0 B";
        }
        $units = ["B", "KB", "MB", "GB", "TB"];
        $pow = floor(log($bytes, 1024));
        if ($pow < 0) {
            $pow = 0;
        }
        if ($pow >= count($units)) {
            $pow = count($units) - 1;
        }
        $value = $bytes / pow(1024, $pow);
        if ($value >= 10 || $pow === 0) {
            $formatted = number_format($value, 0);
        } else {
            $formatted = number_format($value, 1);
        }
        return $formatted . " " . $units[$pow];
    }
}
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$stmt = $mysqli->prepare("SELECT * FROM sites WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$site = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$site) {
    http_response_code(404);
    echo "Site not found";
    exit;
}
$message = "";
$message_type = "";
// validate required data
$missing_required = [];
if (trim($site["site_user"] ?? "") === "") {
    $missing_required[] = "Site user";
}
if (trim($site["domain"] ?? "") === "") {
    $missing_required[] = "Domain";
}
if (trim($site["docroot"] ?? "") === "") {
    $missing_required[] = "Document root";
}
$db_incomplete = false;
if (trim($site["db_name"] ?? "") === "" || trim($site["db_user"] ?? "") === "") {
    $db_incomplete = true;
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "create_backup") {
        if (!empty($missing_required)) {
            $message = "Cannot run backup. Please fill: " . implode(", ", $missing_required) . ".";
            $message_type = "danger";
        } else {
            $timestamp = date("Ymd-His");
            $cmd = "sudo /usr/local/bin/backup_site.sh "
                . escapeshellarg($site["site_user"]) . " "
                . escapeshellarg($site["domain"]) . " "
                . escapeshellarg($site["docroot"]) . " "
                . escapeshellarg($site["db_host"]) . " "
                . escapeshellarg($site["db_name"]) . " "
                . escapeshellarg($site["db_user"]) . " "
                . escapeshellarg($site["db_pass"]) . " "
                . escapeshellarg($timestamp);
            $output = [];
            $exitCode = 0;
            exec($cmd . " 2>&1", $output, $exitCode);
            $log = date("c") . "\n";
            $log .= "CMD: " . $cmd . "\n";
            $log .= "EXIT: " . $exitCode . "\n";
            $log .= "OUT:\n" . implode("\n", $output) . "\n\n";
            @file_put_contents(__DIR__ . "/backup_debug.log", $log, FILE_APPEND);
            if ($exitCode === 0 && count($output) > 0) {
                $backupPath = trim($output[count($output) - 1]);
                $siteZip = $backupPath . "/site.zip";
                $dbSql = $backupPath . "/db.sql";
                $stmt2 = $mysqli->prepare("INSERT INTO backups (site_id, backup_path, site_zip_path, db_sql_path) VALUES (?,?,?,?)");
                $stmt2->bind_param("isss", $id, $backupPath, $siteZip, $dbSql);
                $stmt2->execute();
                $message = "Backup created successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to create backup. See backup_debug.log on the server.";
                $message_type = "danger";
            }
        }
    } elseif ($_POST["action"] === "restore_backup" && isset($_POST["backup_id"])) {
        $backupId = (int)$_POST["backup_id"];
        $stmtRestore = $mysqli->prepare("SELECT b.*, s.docroot, s.db_host, s.db_name, s.db_user, s.db_pass FROM backups b INNER JOIN sites s ON s.id = b.site_id WHERE b.id = ? AND s.id = ?");
        $stmtRestore->bind_param("ii", $backupId, $id);
        $stmtRestore->execute();
        $rowRestore = $stmtRestore->get_result()->fetch_assoc();
        $stmtRestore->close();
        if (!$rowRestore) {
            $message = "Backup not found for this site.";
            $message_type = "danger";
        } else {
            $errors = [];
            if (is_file($rowRestore["site_zip_path"]) && is_dir($rowRestore["docroot"])) {
                $cmdFiles = "cd " . escapeshellarg($rowRestore["docroot"]) . " && unzip -oq " . escapeshellarg($rowRestore["site_zip_path"]);
                $outFiles = [];
                $codeFiles = 0;
                exec("bash -lc " . escapeshellarg($cmdFiles), $outFiles, $codeFiles);
                if ($codeFiles !== 0) {
                    $errors[] = "Failed to restore site files.";
                }
            }
            if ($rowRestore["db_name"] !== "" && $rowRestore["db_user"] !== "" && is_file($rowRestore["db_sql_path"])) {
                $mysqlCmd = "mysql -h" . escapeshellarg($rowRestore["db_host"]) . " -u" . escapeshellarg($rowRestore["db_user"]);
                if ($rowRestore["db_pass"] !== "") {
                    $mysqlCmd .= " -p" . escapeshellarg($rowRestore["db_pass"]);
                }
                $mysqlCmd .= " " . escapeshellarg($rowRestore["db_name"]) . " < " . escapeshellarg($rowRestore["db_sql_path"]);
                $outDb = [];
                $codeDb = 0;
                exec("bash -lc " . escapeshellarg($mysqlCmd), $outDb, $codeDb);
                if ($codeDb !== 0) {
                    $errors[] = "Failed to restore database.";
                }
            }
            if (empty($errors)) {
                $message = "Backup restored successfully.";
                $message_type = "success";
            } else {
                $message = implode(" ", $errors);
                $message_type = "danger";
            }
        }
    }
}
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
if ($page < 1) {
    $page = 1;
}
$perPage = 10;
$offset = ($page - 1) * $perPage;
$totalBackups = 0;
$stmtCount = $mysqli->prepare("SELECT COUNT(*) AS c FROM backups WHERE site_id = ?");
$stmtCount->bind_param("i", $id);
$stmtCount->execute();
$resCount = $stmtCount->get_result();
if ($rowCount = $resCount->fetch_assoc()) {
    $totalBackups = (int)$rowCount["c"];
}
$stmtCount->close();
$totalPages = $totalBackups > 0 ? (int)ceil($totalBackups / $perPage) : 1;
$stmt = $mysqli->prepare("SELECT * FROM backups WHERE site_id = ? ORDER BY created_at DESC LIMIT ?, ?");
$stmt->bind_param("iii", $id, $offset, $perPage);
$stmt->execute();
$backups = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $size = 0;
    if (!empty($row["site_zip_path"]) && is_file($row["site_zip_path"])) {
        $size += filesize($row["site_zip_path"]);
    }
    if (!empty($row["db_sql_path"]) && is_file($row["db_sql_path"])) {
        $size += filesize($row["db_sql_path"]);
    }
    $row["size_bytes"] = $size;
    $backups[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Backups - <?php echo htmlspecialchars($site["domain"]); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
<div class="d-flex justify-content-between align-items-center mb-3">
<div>
<h1 class="mb-1"><?php echo htmlspecialchars($site["name"]); ?></h1>
<div class="text-muted small"><?php echo htmlspecialchars($site["domain"]); ?></div>
</div>
<div class="text-end">
<form method="post" class="d-inline me-2" id="generateForm">
<input type="hidden" name="action" value="create_backup">
<button class="btn btn-primary" type="submit" id="generateBtn">Generate backup</button>
</form>
<a class="btn btn-outline-secondary btn-sm" href="site_edit.php?id=<?php echo (int)$site["id"]; ?>">Edit site</a>
<a class="btn btn-link btn-sm" href="index.php">&laquo; Back</a>
</div>
</div>
<div id="generateLoading" class="alert alert-info" style="display:none">Generating backup, please wait...</div>
<?php if ($db_incomplete): ?>
<div class="alert alert-warning">
Database information is incomplete. The backup will include only files until you fill database fields on the edit screen.
</div>
<?php endif; ?>
<?php if ($message !== ""): ?>
<div class="alert alert-<?php echo $message_type === "success" ? "success" : "danger"; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
<table class="table table-bordered table-striped">
<thead>
<tr><th>ID</th><th>Date</th><th>Path</th><th>Size</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($backups as $b): ?>
<tr>
<td><?php echo (int)$b["id"]; ?></td>
<?php
$dt = DateTime::createFromFormat("Y-m-d H:i:s", $b["created_at"]);
$formattedDate = $dt ? $dt->format("d/m/Y H:i:s") : $b["created_at"];
?>
<td><?php echo htmlspecialchars($formattedDate); ?></td>
<td><?php echo htmlspecialchars($b["backup_path"]); ?></td>
<td><?php echo $b["size_bytes"] > 0 ? htmlspecialchars(format_bytes($b["size_bytes"])) : "-"; ?></td>
<td>
<a class="btn btn-sm btn-success" href="download.php?id=<?php echo (int)$b["id"]; ?>&type=site">Download ZIP</a>
<a class="btn btn-sm btn-info" href="download.php?id=<?php echo (int)$b["id"]; ?>&type=db">Download SQL</a>
<form method="post" class="d-inline" onsubmit="return confirm('Restore this backup? This will overwrite current files and database.');">
<input type="hidden" name="action" value="restore_backup">
<input type="hidden" name="backup_id" value="<?php echo (int)$b["id"]; ?>">
<button type="submit" class="btn btn-sm btn-warning">Restore</button>
</form>
<a class="btn btn-sm btn-danger" href="delete_backup.php?id=<?php echo (int)$b["id"]; ?>&site_id=<?php echo (int)$site["id"]; ?>" onclick="return confirm('Delete this backup?');">Delete</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (count($backups) === 0): ?>
<tr><td colspan="5">No backups yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
<?php if ($totalBackups > 0): ?>
<?php
$from = $offset + 1;
$to = $offset + count($backups);
if ($to > $totalBackups) {
    $to = $totalBackups;
}
?>
<div class="d-flex justify-content-between align-items-center mt-2">
<div class="text-muted">
Showing <?php echo $from; ?>–<?php echo $to; ?> of <?php echo $totalBackups; ?> backups
</div>
<div>
<?php if ($page > 1): ?>
<a class="btn btn-outline-secondary btn-sm me-2" href="?id=<?php echo $id; ?>&page=<?php echo $page - 1; ?>">Previous</a>
<?php else: ?>
<button class="btn btn-outline-secondary btn-sm me-2" disabled>Previous</button>
<?php endif; ?>
<?php if ($page < $totalPages): ?>
<a class="btn btn-outline-secondary btn-sm" href="?id=<?php echo $id; ?>&page=<?php echo $page + 1; ?>">Next</a>
<?php else: ?>
<button class="btn btn-outline-secondary btn-sm" disabled>Next</button>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var form = document.getElementById("generateForm");
  if (!form) return;
  var btn = document.getElementById("generateBtn");
  var loading = document.getElementById("generateLoading");
  form.addEventListener("submit", function () {
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Generating backup...";
    }
    if (loading) {
      loading.style.display = "block";
    }
  });
});
</script>
</body>
</html>
