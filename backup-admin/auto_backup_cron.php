<?php
require __DIR__ . "/app_bootstrap.php";
$tzName = get_setting("backup_timezone", "UTC");
if ($tzName === "") {
    $tzName = "UTC";
}
$autoTime = get_setting("auto_backup_time", "03:00");
if (!preg_match("/^\d{2}:\d{2}$/", $autoTime)) {
    $autoTime = "03:00";
}
list($h, $m) = explode(":", $autoTime);
$tz = new DateTimeZone($tzName);
$now = new DateTimeImmutable("now", $tz);
$targetToday = $now->setTime((int)$h, (int)$m);
$diff = abs($now->getTimestamp() - $targetToday->getTimestamp());
if ($diff > 600) {
    exit;
}
$sites = $mysqli->query("SELECT * FROM sites WHERE auto_backup_enabled = 1");
if (!$sites) {
    exit;
}
while ($site = $sites->fetch_assoc()) {
    $siteId = (int)$site["id"];
    $freq = $site["auto_backup_frequency"] ?: "daily";
    $stmt = $mysqli->prepare("SELECT created_at FROM backups WHERE site_id = ? ORDER BY created_at DESC LIMIT 1");
    if (!$stmt) {
        continue;
    }
    $stmt->bind_param("i", $siteId);
    $stmt->execute();
    $stmt->bind_result($lastCreated);
    $hasLast = $stmt->fetch();
    $stmt->close();
    $shouldRun = false;
    if (!$hasLast || !$lastCreated) {
        $shouldRun = true;
    } else {
        $lastDt = DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $lastCreated);
        if ($lastDt) {
            if ($freq === "daily") {
                if ($lastDt->format("Y-m-d") < $now->format("Y-m-d")) {
                    $shouldRun = true;
                }
            } else {
                $diffDays = ($now->getTimestamp() - $lastDt->getTimestamp()) / 86400;
                if ($diffDays >= 7) {
                    $shouldRun = true;
                }
            }
        } else {
            $shouldRun = true;
        }
    }
    if (!$shouldRun) {
        continue;
    }
    $timestamp = $now->format("Ymd-His");
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
    if ($exitCode !== 0 || count($output) === 0) {
        continue;
    }
    $backupPath = trim($output[count($output) - 1]);
    $siteZip = $backupPath . "/site.zip";
    $dbSql = $backupPath . "/db.sql";
    $stmtIns = $mysqli->prepare("INSERT INTO backups (site_id, backup_path, site_zip_path, db_sql_path) VALUES (?,?,?,?)");
    if ($stmtIns) {
        $stmtIns->bind_param("isss", $siteId, $backupPath, $siteZip, $dbSql);
        $stmtIns->execute();
        $stmtIns->close();
    }
    $stmtList = $mysqli->prepare("SELECT id, backup_path, site_zip_path, db_sql_path FROM backups WHERE site_id = ? ORDER BY created_at DESC");
    if (!$stmtList) {
        continue;
    }
    $stmtList->bind_param("i", $siteId);
    $stmtList->execute();
    $res = $stmtList->get_result();
    $index = 0;
    while ($row = $res->fetch_assoc()) {
        $index++;
        if ($index <= 7) {
            continue;
        }
        if (is_dir($row["backup_path"])) {
            $files = @scandir($row["backup_path"]);
            if ($files !== false) {
                foreach ($files as $f) {
                    if ($f === "." || $f === "..") {
                        continue;
                    }
                    @unlink($row["backup_path"] . "/" . $f);
                }
            }
            @rmdir($row["backup_path"]);
        } else {
            if ($row["site_zip_path"]) {
                @unlink($row["site_zip_path"]);
            }
            if ($row["db_sql_path"]) {
                @unlink($row["db_sql_path"]);
            }
        }
        $stmtDel = $mysqli->prepare("DELETE FROM backups WHERE id = ?");
        if ($stmtDel) {
            $bid = (int)$row["id"];
            $stmtDel->bind_param("i", $bid);
            $stmtDel->execute();
            $stmtDel->close();
        }
    }
    $stmtList->close();
}
