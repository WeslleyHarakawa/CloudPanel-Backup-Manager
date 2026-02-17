<?php
require __DIR__ . "/app_bootstrap.php";
require_login();
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$siteId = isset($_GET["site_id"]) ? (int)$_GET["site_id"] : 0;
$stmt = $mysqli->prepare("SELECT * FROM backups WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) {
    if (is_file($row["site_zip_path"])) {
        unlink($row["site_zip_path"]);
    }
    if (is_file($row["db_sql_path"])) {
        unlink($row["db_sql_path"]);
    }
    if (is_dir($row["backup_path"])) {
        @rmdir($row["backup_path"]);
    }
    $stmt2 = $mysqli->prepare("DELETE FROM backups WHERE id = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
}
if ($siteId > 0) {
    header("Location: site.php?id=" . $siteId);
} else {
    header("Location: index.php");
}
