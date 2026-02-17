<?php
require __DIR__ . "/app_bootstrap.php";
require_login();
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id > 0) {
    $stmt = $mysqli->prepare("DELETE FROM sites WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}
header("Location: index.php");

