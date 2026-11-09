<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/activity_log.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone_number'] ?? '');

    // Check the required fields and a minimum password length.
    if ($name === '' || $email === '' || $password === '') {
        $error = "Please fill in every required field.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Stop two accounts using the same email.
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "That email is already registered. Try logging in instead.";
        } else {
            // Store only a salted hash of the password (bcrypt), never the password itself.
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, phone_number) VALUES (?, ?, ?, ?)"
            );
            $insert->execute([$name, $email, $hash, $phone ?: null]);
            $new_id = (int) $pdo->lastInsertId();

            // Log the new student in straight away.
            $_SESSION['user_id'] = $new_id;
            $_SESSION['user_name'] = $name;
            log_activity($pdo, $new_id, 'register', "Account created");
            header("Location: dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Register — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
    <div class="container">
        <a href="index.php" style="text-decoration:none; color:var(--navy); font-weight:bold; font-size:14px; display:inline-block; margin-bottom:16px;">← Back to home</a>
        <h1>Create your account</h1>
        <p class="muted">Takes under a minute.</p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Full name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>

            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Phone number (optional — for SMS deadline reminders)</label>
            <input type="text" name="phone_number" placeholder="e.g. +4512345678" value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
            <p class="muted" style="font-size:12px;">Include your country code. Leave blank to only get in-app reminders.</p>

            <button type="submit" class="btn">Create account</button>
        </form>

        <p class="muted" style="margin-top:18px;">
            Already have an account? <a href="login.php">Log in</a>
        </p>
    </div>
</body>
</html>
