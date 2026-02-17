<?php
require __DIR__ . "/app_bootstrap.php";
$_SESSION = [];
if (session_id() !== "") {
    session_destroy();
}
header("Location: index.php");

