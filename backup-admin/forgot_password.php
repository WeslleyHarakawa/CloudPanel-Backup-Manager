<?php
require __DIR__ . "/app_bootstrap.php";
require __DIR__ . "/config.php";
$systemTitle = get_setting("system_title", "Backup Admin");
$info = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    if ($email === "") {
        $error = "Email is required.";
    } else {
        $token = bin2hex(random_bytes(32));
        set_setting("password_reset_token", $token);
        set_setting("password_reset_expires", (string)(time() + 3600));
        $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
        $host = $_SERVER["HTTP_HOST"] ?? "localhost";
        $base = rtrim(dirname($_SERVER["PHP_SELF"]), "/");
        $resetUrl = $scheme . "://" . $host . $base . "/reset_password.php?token=" . urlencode($token);
        $subject = $systemTitle . " - Password reset";
        $body = "A password reset was requested for your admin account.\n\n";
        $body .= "If you requested this, click the link below to set a new password:\n";
        $body .= $resetUrl . "\n\n";
        $body .= "If you did not request this, you can ignore this email.";
        $to = $cfg_admin_user;
        try {
            send_smtp_mail($to, $subject, $body);
            $info = "If the email exists, a reset link has been sent.";
        } catch (Exception $e) {
            $error = "Failed to send email: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($systemTitle); ?> - Forgot password</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container">
<div class="row justify-content-center">
<div class="col-md-4">
<div class="text-center mb-4">
<h1 class="h4 mb-1"><?php echo htmlspecialchars($systemTitle); ?></h1>
<div class="text-muted">Reset admin password</div>
</div>
<div class="card p-4 shadow-sm">
<?php if ($error !== ""): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($info !== ""): ?>
<div class="alert alert-info"><?php echo htmlspecialchars($info); ?></div>
<?php endif; ?>
<form method="post">
<div class="mb-3">
<label class="form-label">Email</label>
<input class="form-control" type="email" name="email" required>
</div>
<button class="btn btn-primary w-100" type="submit">Send reset link</button>
</form>
<div class="mt-3 text-center">
<a href="index.php">Back to login</a>
</div>
</div>
</div>
</div>
</div>
</body>
</html>

