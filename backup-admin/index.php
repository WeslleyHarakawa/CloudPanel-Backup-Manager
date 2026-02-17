<?php
require __DIR__ . "/app_bootstrap.php";
require_login();
if (isset($_GET["sync"])) {
    sync_sites_from_cloudpanel();
    $message = "Sites synchronized with CloudPanel.";
} else {
    $message = "";
}
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
if ($page < 1) {
    $page = 1;
}
$perPage = 10;
$offset = ($page - 1) * $perPage;
$totalSites = 0;
$resCount = $mysqli->query("SELECT COUNT(*) AS c FROM sites");
if ($resCount) {
    $rowCount = $resCount->fetch_assoc();
    $totalSites = (int)$rowCount["c"];
}
$stmt = $mysqli->prepare("SELECT * FROM sites ORDER BY name LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $perPage);
$stmt->execute();
$res = $stmt->get_result();
$sites = [];
$siteIds = [];
while ($row = $res->fetch_assoc()) {
    $sites[] = $row;
    $siteIds[] = (int)$row["id"];
}
$backupsInfo = [];
if (count($siteIds) > 0) {
    $idsList = implode(",", array_map("intval", $siteIds));
    $sql = "SELECT site_id, MAX(created_at) AS last_backup, COUNT(*) AS total_backups FROM backups WHERE site_id IN ($idsList) GROUP BY site_id";
    $res2 = $mysqli->query($sql);
    if ($res2) {
        while ($row2 = $res2->fetch_assoc()) {
            $backupsInfo[(int)$row2["site_id"]] = $row2;
        }
    }
}
$totalPages = $totalSites > 0 ? (int)ceil($totalSites / $perPage) : 1;
$systemTitle = get_setting("system_title", "Backup Admin");
$adminDisplayName = get_setting("admin_name", "Admin");
$brandName = get_setting("brand_name", "PMS Dash");
$brandUrl = get_setting("brand_url", "https://pmsdash.com");
$currentYear = date("Y");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($systemTitle); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="p-4">
<div class="container">
<div class="d-flex justify-content-between align-items-center mb-4">
<h1 class="h3 mb-0"><?php echo htmlspecialchars($systemTitle); ?></h1>
<div class="d-flex align-items-center">
<span class="me-3 text-muted"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($adminDisplayName); ?></span>
<a class="btn btn-outline-secondary btn-sm me-2" href="settings.php">Settings</a>
<a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
</div>
</div>
<?php if ($message !== ""): ?>
<div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
<div class="d-flex align-items-center">
<h2 class="h4 mb-0 me-3">Sites</h2>
<input type="text" class="form-control" id="searchSites" placeholder="Search by name or domain" style="max-width:260px">
</div>
<div>
<a class="btn btn-outline-secondary me-2" href="?sync=1" title="Sync sites with CloudPanel">
<i class="bi bi-arrow-repeat"></i>
</a>
<a class="btn btn-success" href="site_new.php">Add site</a>
</div>
</div>
<table class="table table-bordered table-striped">
<thead>
<tr>
<th>Name</th>
<th>Domain</th>
<th>Auto backup</th>
<th>Last backup</th>
<th>Total backups</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($sites as $site): ?>
<tr>
<td>
<a href="site.php?id=<?php echo (int)$site["id"]; ?>" class="text-decoration-none fw-bold text-dark">
<?php echo htmlspecialchars($site["name"]); ?>
</a>
</td>
<td><?php echo htmlspecialchars($site["domain"]); ?></td>
<?php
$info = $backupsInfo[(int)$site["id"]] ?? null;
$lastBackup = "-";
if ($info && $info["last_backup"]) {
    $dt = DateTime::createFromFormat("Y-m-d H:i:s", $info["last_backup"]);
    if ($dt) {
        $lastBackup = $dt->format("d/m/Y H:i:s");
    } else {
        $lastBackup = $info["last_backup"];
    }
}
$totalBackups = $info && $info["total_backups"] ? (int)$info["total_backups"] : 0;
?>
<td>
<?php
$autoEnabled = isset($site["auto_backup_enabled"]) ? (int)$site["auto_backup_enabled"] : 0;
$autoFreq = $site["auto_backup_frequency"] ?? "";
$label = $autoEnabled ? "ON" : "OFF";
$class = $autoEnabled ? "bg-success" : "bg-secondary";
$title = "";
if ($autoEnabled && $autoFreq !== "") {
    $title = ' title="' . htmlspecialchars(ucfirst($autoFreq)) . '"';
}
?>
<span class="badge <?php echo $class; ?>"<?php echo $title; ?>>
<?php echo $label; ?>
</span>
</td>
<td><?php echo htmlspecialchars($lastBackup); ?></td>
<td><?php echo $totalBackups; ?></td>
<td>
<a class="btn btn-sm btn-outline-primary" href="site.php?id=<?php echo (int)$site["id"]; ?>" title="View backups">
<i class="bi bi-folder2-open"></i>
</a>
<a class="btn btn-sm btn-outline-secondary ms-1" href="site_edit.php?id=<?php echo (int)$site["id"]; ?>" title="Edit site">
<i class="bi bi-pencil"></i>
</a>
<a class="btn btn-sm btn-outline-danger ms-1" href="delete_site.php?id=<?php echo (int)$site["id"]; ?>" title="Delete site" onclick="return confirm('Delete this site and all its backups?');">
<i class="bi bi-trash"></i>
</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (count($sites) === 0): ?>
<tr><td colspan="6">No sites registered yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
<?php if ($totalSites > 0): ?>
<?php
$from = $offset + 1;
$to = $offset + count($sites);
if ($to > $totalSites) {
    $to = $totalSites;
}
?>
<div class="d-flex justify-content-between align-items-center">
<div class="text-muted">
Showing <?php echo $from; ?>–<?php echo $to; ?> of <?php echo $totalSites; ?> sites
</div>
<div>
<?php if ($page > 1): ?>
<a class="btn btn-outline-secondary btn-sm me-2" href="?page=<?php echo $page - 1; ?>">Previous</a>
<?php else: ?>
<button class="btn btn-outline-secondary btn-sm me-2" disabled>Previous</button>
<?php endif; ?>
<?php if ($page < $totalPages): ?>
<a class="btn btn-outline-secondary btn-sm" href="?page=<?php echo $page + 1; ?>">Next</a>
<?php else: ?>
<button class="btn btn-outline-secondary btn-sm" disabled>Next</button>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
<div class="mt-4 mb-2 text-center text-muted small">
&copy; <?php echo $currentYear; ?> <strong><a href="<?php echo htmlspecialchars($brandUrl); ?>" target="_blank" class="text-decoration-none"><?php echo htmlspecialchars($brandName); ?></a></strong>. All rights reserved.
</div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var input = document.getElementById("searchSites");
  if (!input) {
    return;
  }
  var tbody = document.querySelector("table tbody");
  if (!tbody) {
    return;
  }
  var rows = Array.prototype.slice.call(tbody.querySelectorAll("tr"));
  input.addEventListener("input", function () {
    var q = input.value.toLowerCase();
    rows.forEach(function (row) {
      var cells = row.children;
      if (!cells || cells.length < 2) {
        return;
      }
      var nameText = cells[0].textContent || "";
      var domainText = cells[1].textContent || "";
      var text = (nameText + " " + domainText).toLowerCase();
      if (q === "" || text.indexOf(q) !== -1) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  });
});
</script>
</body>
</html>
