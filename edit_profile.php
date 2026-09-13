<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = current_user_id();
$stmt = $pdo->prepare("SELECT name, email, phone_number FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone_number'] ?? '');
    $update = $pdo->prepare("UPDATE users SET phone_number = ? WHERE user_id = ?");
    $update->execute([$phone ?: null, $user_id]);
    $success = "Saved.";
    $user['phone_number'] = $phone;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Profile — To-Do List</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Your profile</h1>

        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <label>Name</label>
        <input type="text" value="<?= htmlspecialchars($user['name']) ?>" disabled>

        <label>Email</label>
        <input type="text" value="<?= htmlspecialchars($user['email']) ?>" disabled>

        <form method="POST">
            <label>Phone number (for SMS reminders)</label>
            <input type="text" name="phone_number" placeholder="e.g. +4512345678" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
            <p class="muted" style="font-size:12px;">Include your country code. Leave blank to disable SMS reminders.</p>

            <button type="submit" class="btn">Save</button>
            <a href="dashboard.php" class="btn btn-secondary">Back to dashboard</a>
        </form>
    </div>
</body>
</html>
