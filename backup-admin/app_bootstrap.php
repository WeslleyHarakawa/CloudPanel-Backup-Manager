<?php
require __DIR__ . "/config.php";
$mysqli = new mysqli($cfg_db_host, $cfg_db_user, $cfg_db_pass, $cfg_db_name);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Database connection error";
    exit;
}
$mysqli->set_charset("utf8mb4");
$mysqli->query("CREATE TABLE IF NOT EXISTS sites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  site_user VARCHAR(255) NOT NULL,
  docroot VARCHAR(512) NOT NULL,
  db_host VARCHAR(255) NOT NULL,
  db_name VARCHAR(255) NOT NULL,
  db_user VARCHAR(255) NOT NULL,
  db_pass VARCHAR(255) NOT NULL,
  auto_backup_enabled TINYINT(1) NOT NULL DEFAULT 0,
  auto_backup_frequency VARCHAR(16) NOT NULL DEFAULT 'daily',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$mysqli->query("CREATE TABLE IF NOT EXISTS backups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  backup_path VARCHAR(512) NOT NULL,
  site_zip_path VARCHAR(512) NOT NULL,
  db_sql_path VARCHAR(512) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$mysqli->query("CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(64) NOT NULL UNIQUE,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// ensure new columns exist on older installations
$resCols = $mysqli->query("SHOW COLUMNS FROM sites LIKE 'auto_backup_enabled'");
if ($resCols && $resCols->num_rows === 0) {
    $mysqli->query("ALTER TABLE sites ADD COLUMN auto_backup_enabled TINYINT(1) NOT NULL DEFAULT 0");
}
$resCols2 = $mysqli->query("SHOW COLUMNS FROM sites LIKE 'auto_backup_frequency'");
if ($resCols2 && $resCols2->num_rows === 0) {
    $mysqli->query("ALTER TABLE sites ADD COLUMN auto_backup_frequency VARCHAR(16) NOT NULL DEFAULT 'daily'");
}
session_start();
function get_setting($key, $default = null)
{
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $stmt->bind_result($val);
    if ($stmt->fetch()) {
        $stmt->close();
        return $val;
    }
    $stmt->close();
    return $default;
}
function set_setting($key, $value)
{
    global $mysqli;
    $stmt = $mysqli->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
    $stmt->close();
}
function totp_base32_decode($s)
{
    $s = trim($s);
    if ($s === "") {
        return "";
    }
    if (preg_match("/^[0-9a-fA-F]+$/", $s)) {
        $bin = @hex2bin($s);
        return $bin === false ? "" : $bin;
    }
    $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    $s = strtoupper($s);
    $bits = "";
    $result = "";
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $ch = strpos($alphabet, $s[$i]);
        if ($ch === false) {
            continue;
        }
        $bits .= str_pad(decbin($ch), 5, "0", STR_PAD_LEFT);
    }
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $byte = substr($bits, $i, 8);
        $result .= chr(bindec($byte));
    }
    return $result;
}
function totp_base32_encode($data)
{
    $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    $bits = "";
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, "0", STR_PAD_LEFT);
    }
    $result = "";
    for ($i = 0; $i < strlen($bits); $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, "0", STR_PAD_RIGHT);
        }
        $index = bindec($chunk);
        $result .= $alphabet[$index];
    }
    return $result;
}
function generate_totp($secret, $timeSlice)
{
    $secretKey = totp_base32_decode($secret);
    $time = pack("N*", 0) . pack("N*", $timeSlice);
    $hash = hash_hmac("sha1", $time, $secretKey, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $part = substr($hash, $offset, 4);
    $value = unpack("N", $part)[1] & 0x7FFFFFFF;
    $code = $value % 1000000;
    return str_pad((string)$code, 6, "0", STR_PAD_LEFT);
}
function verify_totp_code($secret, $code, $window = 1)
{
    $code = trim($code);
    if ($code === "") {
        return false;
    }
    $time = time();
    $slice = floor($time / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $calc = generate_totp($secret, $slice + $i);
        if (hash_equals($calc, $code)) {
            return true;
        }
    }
    return false;
}
function send_smtp_mail($to, $subject, $body)
{
    $host = get_setting("smtp_host", "");
    $port = get_setting("smtp_port", "");
    $user = get_setting("smtp_user", "");
    $pass = get_setting("smtp_pass", "");
    $enc = get_setting("smtp_encryption", "");
    $from = get_setting("notify_email", $user);
    if ($host === "" || $port === "" || $from === "") {
        throw new Exception("SMTP is not configured.");
    }
    $port = (int)$port;
    $transport = $host;
    if ($enc === "ssl") {
        $transport = "ssl://" . $host;
    } elseif ($enc === "tls") {
        $transport = "tls://" . $host;
    }
    $fp = @stream_socket_client($transport . ":" . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        throw new Exception("SMTP connect failed: " . $errstr);
    }
    $read = function () use ($fp) {
        $data = "";
        while ($str = fgets($fp, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === " ") {
                break;
            }
        }
        return $data;
    };
    $write = function ($cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };
    $read();
    $write("EHLO localhost");
    $ehlo = $read();
    if (strpos($ehlo, "250") !== 0) {
        $write("HELO localhost");
        $read();
    }
    if ($user !== "" && $pass !== "") {
        $write("AUTH LOGIN");
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $authResp = $read();
        if (strpos($authResp, "235") !== 0) {
            fclose($fp);
            throw new Exception("SMTP auth failed.");
        }
    }
    $write("MAIL FROM:<" . $from . ">");
    $read();
    $write("RCPT TO:<" . $to . ">");
    $read();
    $write("DATA");
    $read();
    $headers = [];
    $headers[] = "From: " . $from;
    $headers[] = "To: " . $to;
    $headers[] = "Subject: " . $subject;
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $msg = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    $write($msg);
    $read();
    $write("QUIT");
    fclose($fp);
}
function get_admin_hash()
{
    global $cfg_admin_pass;
    static $hash = null;
    if ($hash === null) {
        $hash = password_hash($cfg_admin_pass, PASSWORD_DEFAULT);
    }
    return $hash;
}
function sync_sites_from_cloudpanel()
{
    global $mysqli, $cfg_base_path;
    $output = [];
    $exitCode = 0;
    $candidates = [];
    if (is_file('/usr/local/bin/list_cloudpanel_sites.sh')) {
        $candidates[] = 'sudo /usr/local/bin/list_cloudpanel_sites.sh';
        $candidates[] = '/usr/local/bin/list_cloudpanel_sites.sh';
    }
    $local = rtrim($cfg_base_path, '/') . '/list_cloudpanel_sites.sh';
    if (is_file($local)) {
        $candidates[] = escapeshellcmd($local);
    }
    foreach ($candidates as $cmd) {
        $output = [];
        $exitCode = 0;
        exec($cmd . ' 2>/dev/null', $output, $exitCode);
        if ($exitCode === 0 && count($output) > 0) {
            break;
        }
    }
    if ($exitCode !== 0) {
        return;
    }
    foreach ($output as $line) {
        $line = trim($line);
        if ($line === "") {
            continue;
        }
        $parts = explode("|", $line);
        if (count($parts) !== 7) {
            continue;
        }
        list($site_user, $domain, $docroot, $db_host, $db_name, $db_user, $db_pass) = $parts;
        $site_user = trim($site_user);
        $domain = trim($domain);
        $docroot = trim($docroot);
        $db_host = trim($db_host);
        $db_name = trim($db_name);
        $db_user = trim($db_user);
        $db_pass = trim($db_pass);
        if ($domain === "" || $docroot === "") {
            continue;
        }
        $name = $domain;
        $stmt = $mysqli->prepare("SELECT id, name, db_host, db_name, db_user, db_pass, auto_backup_enabled, auto_backup_frequency FROM sites WHERE domain = ? AND docroot = ?");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param("ss", $domain, $docroot);
        $stmt->execute();
        $stmt->bind_result($id, $cur_name, $cur_db_host, $cur_db_name, $cur_db_user, $cur_db_pass, $cur_auto_enabled, $cur_auto_freq);
        if ($stmt->fetch()) {
            $stmt->close();
            $keep_name = $cur_name !== "" ? $cur_name : $name;
            $new_db_host = $cur_db_host !== "" ? $cur_db_host : $db_host;
            $new_db_name = $cur_db_name !== "" ? $cur_db_name : $db_name;
            $new_db_user = $cur_db_user !== "" ? $cur_db_user : $db_user;
            $new_db_pass = $cur_db_pass !== "" ? $cur_db_pass : $db_pass;
            $auto_enabled = (int)$cur_auto_enabled;
            $auto_freq = $cur_auto_freq !== "" ? $cur_auto_freq : "daily";
            $stmt2 = $mysqli->prepare("UPDATE sites SET name = ?, site_user = ?, db_host = ?, db_name = ?, db_user = ?, db_pass = ?, auto_backup_enabled = ?, auto_backup_frequency = ? WHERE id = ?");
            if ($stmt2) {
                $stmt2->bind_param("ssssssisi", $keep_name, $site_user, $new_db_host, $new_db_name, $new_db_user, $new_db_pass, $auto_enabled, $auto_freq, $id);
                $stmt2->execute();
                $stmt2->close();
            }
        } else {
            $stmt->close();
            $stmt2 = $mysqli->prepare("INSERT INTO sites (name, domain, site_user, docroot, db_host, db_name, db_user, db_pass, auto_backup_enabled, auto_backup_frequency) VALUES (?,?,?,?,?,?,?,?,0,'daily')");
            if ($stmt2) {
                $stmt2->bind_param("ssssssss", $name, $domain, $site_user, $docroot, $db_host, $db_name, $db_user, $db_pass);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    }
}
function require_login()
{
    global $cfg_admin_user;
    $systemTitle = get_setting("system_title", "Backup Admin");
    $twofaEnabled = get_setting("twofa_enabled", "0") === "1" && get_setting("twofa_secret", "") !== "";
    $twofaSecret = $twofaEnabled ? get_setting("twofa_secret", "") : "";
    if (!isset($_SESSION["admin_logged"]) || $_SESSION["admin_logged"] !== true) {
        if (!$twofaEnabled && isset($_COOKIE["remember_admin"])) {
            $stored = get_setting("remember_token", "");
            $cookieToken = $_COOKIE["remember_admin"];
            if ($stored !== "" && $cookieToken !== "" && password_verify($cookieToken, $stored)) {
                $_SESSION["admin_logged"] = true;
                return;
            }
        }
    }
    if (isset($_SESSION["admin_logged"]) && $_SESSION["admin_logged"] === true) {
        return;
    }
    $loginError = "";
    $twofaError = "";
    if ($twofaEnabled && isset($_SESSION["pending_2fa"]) && $_SESSION["pending_2fa"] === true) {
        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["code"])) {
            $code = trim($_POST["code"]);
            if ($twofaSecret !== "" && $code !== "" && verify_totp_code($twofaSecret, $code)) {
                $_SESSION["admin_logged"] = true;
                if (!empty($_SESSION["remember_after_2fa"])) {
                    $token = bin2hex(random_bytes(16));
                    set_setting("remember_token", password_hash($token, PASSWORD_DEFAULT));
                    setcookie("remember_admin", $token, time() + 60 * 60 * 24 * 30, "/", "", true, true);
                }
                unset($_SESSION["pending_2fa"], $_SESSION["remember_after_2fa"]);
                header("Location: index.php");
                exit;
            } else {
                $twofaError = "Invalid authenticator code.";
            }
        }
        echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>" . htmlspecialchars($systemTitle) . " 2FA</title><link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\"><link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css\"></head>";
        echo "<body class=\"bg-light d-flex align-items-center\" style=\"min-height:100vh;\">";
        echo "<div class=\"container\">";
        echo "<div class=\"row justify-content-center\">";
        echo "<div class=\"col-md-4\">";
        echo "<div class=\"text-center mb-4\"><h1 class=\"h4 mb-1\">" . htmlspecialchars($systemTitle) . "</h1><div class=\"text-muted\">Enter the code from Google Authenticator</div></div>";
        echo "<form method=\"post\" class=\"card p-4 shadow-sm\">";
        if ($twofaError !== "") {
            echo "<div class=\"alert alert-danger\">" . htmlspecialchars($twofaError) . "</div>";
        }
        echo "<div class=\"mb-3\"><label class=\"form-label\">Authenticator code</label><input class=\"form-control\" type=\"text\" name=\"code\" autocomplete=\"one-time-code\" autofocus></div>";
        echo "<button class=\"btn btn-primary w-100\" type=\"submit\">Verify</button>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</body></html>";
        exit;
    }
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["email"], $_POST["pass"])) {
        if ($_POST["email"] === $cfg_admin_user && password_verify($_POST["pass"], get_admin_hash())) {
            if ($twofaEnabled && $twofaSecret !== "") {
                $_SESSION["pending_2fa"] = true;
                if (!empty($_POST["remember"])) {
                    $_SESSION["remember_after_2fa"] = 1;
                } else {
                    unset($_SESSION["remember_after_2fa"]);
                }
            } else {
                $_SESSION["admin_logged"] = true;
                if (!empty($_POST["remember"])) {
                    $token = bin2hex(random_bytes(16));
                    set_setting("remember_token", password_hash($token, PASSWORD_DEFAULT));
                    setcookie("remember_admin", $token, time() + 60 * 60 * 24 * 30, "/", "", true, true);
                }
                header("Location: index.php");
                exit;
            }
        } else {
            $loginError = "Invalid email or password.";
        }
    }
    $savedEmail = "";
    if (isset($_POST["email"])) {
        $savedEmail = $_POST["email"];
    }
    echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>" . htmlspecialchars($systemTitle) . " Login</title><link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\"><link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css\"></head>";
    echo "<body class=\"bg-light d-flex align-items-center\" style=\"min-height:100vh;\">";
    echo "<div class=\"container\">";
    echo "<div class=\"row justify-content-center\">";
    echo "<div class=\"col-md-4\">";
    echo "<div class=\"text-center mb-4\"><h1 class=\"h4 mb-1\">" . htmlspecialchars($systemTitle) . "</h1><div class=\"text-muted\">Sign in to manage backups</div></div>";
    echo "<form method=\"post\" class=\"card p-4 shadow-sm\">";
    if ($loginError !== "") {
        echo "<div class=\"alert alert-danger\">" . htmlspecialchars($loginError) . "</div>";
    }
    echo "<div class=\"mb-3\"><label class=\"form-label\">Email</label><input class=\"form-control\" type=\"email\" name=\"email\" placeholder=\"Email\" autocomplete=\"username\" value=\"" . htmlspecialchars($savedEmail) . "\"></div>";
    echo "<div class=\"mb-3\"><label class=\"form-label\">Password</label><div class=\"input-group\"><input class=\"form-control\" id=\"login_pass\" type=\"password\" name=\"pass\" autocomplete=\"current-password\"><button class=\"btn btn-outline-secondary\" type=\"button\" id=\"togglePass\"><i class=\"bi bi-eye\"></i></button></div></div>";
    echo "<div class=\"d-flex justify-content-between align-items-center mb-3\">";
    echo "<div class=\"form-check\"><input class=\"form-check-input\" type=\"checkbox\" id=\"remember\" name=\"remember\" value=\"1\"><label class=\"form-check-label\" for=\"remember\">Remember me</label></div>";
    echo "<a href=\"forgot_password.php\">Forgot password?</a>";
    echo "</div>";
    echo "<button class=\"btn btn-primary w-100\" type=\"submit\">Sign in</button>";
    echo "</form>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "<script>document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('togglePass');var i=document.getElementById('login_pass');if(!b||!i)return;b.addEventListener('click',function(){if(i.type==='password'){i.type='text';b.innerHTML='<i class=\"bi bi-eye-slash\"></i>';}else{i.type='password';b.innerHTML='<i class=\"bi bi-eye\"></i>';}});});</script>";
    echo "</body></html>";
    exit;
}
