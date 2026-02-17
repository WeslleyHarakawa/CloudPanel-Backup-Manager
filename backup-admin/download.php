<?php
require __DIR__ . "/app_bootstrap.php";
require_login();
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$type = $_GET["type"] ?? "";
$stmt = $mysqli->prepare("SELECT b.*, s.domain FROM backups b INNER JOIN sites s ON s.id = b.site_id WHERE b.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    http_response_code(404);
    echo "Backup not found";
    exit;
}
$path = "";
$downloadName = "";
if ($type === "site") {
    $path = $row["site_zip_path"];
    $downloadName = $row["domain"] . "-site.zip";
} elseif ($type === "db") {
    $path = $row["db_sql_path"];
    $downloadName = $row["domain"] . "-db.sql";
} else {
    http_response_code(400);
    echo "Invalid type";
    exit;
}
if (!is_file($path)) {
    http_response_code(404);
    echo "File not found";
    exit;
}
header("Content-Type: application/octet-stream");
header("Content-Length: " . filesize($path));
header("Content-Disposition: attachment; filename=\"" . basename($downloadName) . "\"");
readfile($path);
