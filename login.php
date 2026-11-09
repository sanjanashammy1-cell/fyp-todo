<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/activity_log.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Find the account by email with a prepared statement.
    $stmt = $pdo->prepare("SELECT user_id, name, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // password_verify() compares the typed password with the stored hash.
    // The real password is never stored (security test S3).
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        log_activity($pdo, (int) $user['user_id'], 'login', null);
        header("Location: dashboard.php");
        exit;
    } else {
        // Same message for a wrong email or a wrong password, so an attacker
        // cannot find out which emails are registered.
        $error = "Incorrect email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Login —ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
    <div class="container">
        <a href="index.php" style="text-decoration:none; color:var(--navy); font-weight:bold; font-size:14px; display:inline-block; margin-bottom:16px;">← Back to home</a>
        <h1>Welcome back</h1>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="success">Your account and all your data have been deleted.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit" class="btn">Login</button>
        </form>

        <p class="muted" style="margin-top:18px;">
            Don't have an account? <a href="register.php">Register</a>
        </p>
    </div>
</body>
</html>
