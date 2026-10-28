<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/recommender.php';
require_once __DIR__ . '/includes/reminders.php';
require_login();

$user_id = current_user_id();
$success = "";
$error = "";

// --- Load current values ---
$stmt = $pdo->prepare("SELECT name, email, phone_number FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$weights = get_user_weights($pdo, $user_id);
$prefs = get_notification_preferences($pdo, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    // Delete account: the user must type their password to confirm.
    if ($form === 'delete_account') {
        $confirm_password = $_POST['confirm_password'] ?? '';
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if ($row && password_verify($confirm_password, $row['password_hash'])) {
            $pdo->beginTransaction();
            // chat_messages has no foreign key, so delete it first by hand.
            $pdo->prepare("DELETE FROM chat_messages WHERE user_id = ?")->execute([$user_id]);
            // ON DELETE CASCADE removes tasks, reminders, weights, preferences and logs.
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);
            $pdo->commit();

            $_SESSION = [];
            session_destroy();
            header("Location: login.php?deleted=1");
            exit;
        } else {
            $error = "Password is not correct. Your account was not deleted.";
        }
    }

    if ($form === 'phone') {
        $phone = trim($_POST['phone_number'] ?? '');
        $pdo->prepare("UPDATE users SET phone_number = ? WHERE user_id = ?")->execute([$phone ?: null, $user_id]);
        $success = "Phone number saved.";
        $user['phone_number'] = $phone;
    }

    if ($form === 'notifications') {
        $sms_enabled = isset($_POST['sms_enabled']);
        $first_days = (int) ($_POST['first_reminder_days'] ?? 7);
        $second_days = (int) ($_POST['second_reminder_days'] ?? 1);
        save_notification_preferences($pdo, $user_id, $sms_enabled, $first_days, $second_days);
        $success = "Notification preferences saved.";
        $prefs = get_notification_preferences($pdo, $user_id);
    }

    if ($form === 'weights') {
        $w = [
            'urgency' => (int) ($_POST['w_urgency'] ?? 40),
            'weight' => (int) ($_POST['w_weight'] ?? 30),
            'workload' => (int) ($_POST['w_workload'] ?? 15),
            'progress' => (int) ($_POST['w_progress'] ?? 15),
        ];
        $sum = array_sum($w);
        if ($sum !== 100) {
            $error = "Weights must add up to 100% (currently {$sum}%).";
        } else {
            save_user_weights($pdo, $user_id, $w);
            $success = "Recommender weights saved.";
            $weights = $w;
        }
    }
}
$active_page = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Settings — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="container" style="max-width:640px;">
        <h1>Settings</h1>

        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <h2 style="font-size:16px; color:#1F3864;">Profile</h2>
        <form method="POST">
            <input type="hidden" name="form" value="phone">
            <label>Name</label>
            <input type="text" value="<?= htmlspecialchars($user['name']) ?>" disabled>
            <label>Email</label>
            <input type="text" value="<?= htmlspecialchars($user['email']) ?>" disabled>
            <label>Phone number (for SMS reminders)</label>
            <input type="text" name="phone_number" placeholder="e.g. +4512345678" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
            <button type="submit" class="btn">Save phone number</button>
        </form>

        <h2 style="font-size:16px; color:#1F3864; margin-top:32px;">Notification Preferences</h2>
        <form method="POST">
            <input type="hidden" name="form" value="notifications">
            <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="sms_enabled" style="width:auto;" <?= $prefs['sms_enabled'] ? 'checked' : '' ?>>
                Send SMS reminders (in-app reminders always stay on)
            </label>
            <label>First reminder — days before deadline</label>
            <input type="number" name="first_reminder_days" min="1" max="30" value="<?= $prefs['first_reminder_days'] ?>">
            <label>Second reminder — days before deadline</label>
            <input type="number" name="second_reminder_days" min="0" max="30" value="<?= $prefs['second_reminder_days'] ?>">
            <button type="submit" class="btn">Save notification preferences</button>
        </form>

        <h2 style="font-size:16px; color:#1F3864; margin-top:32px;">Recommender Weights</h2>
        <p class="muted">These four numbers must add up to 100%. They control how much each factor influences your "Suggested next task."</p>
        <form method="POST">
            <input type="hidden" name="form" value="weights">
            <label>Deadline proximity (%)</label>
            <input type="number" name="w_urgency" min="0" max="100" value="<?= $weights['urgency'] ?>">
            <label>Assessment weighting (%)</label>
            <input type="number" name="w_weight" min="0" max="100" value="<?= $weights['weight'] ?>">
            <label>Remaining workload (%)</label>
            <input type="number" name="w_workload" min="0" max="100" value="<?= $weights['workload'] ?>">
            <label>Progress (%)</label>
            <input type="number" name="w_progress" min="0" max="100" value="<?= $weights['progress'] ?>">
            <button type="submit" class="btn">Save weights</button>
        </form>

        <h2 style="font-size:16px; color:#B00020; margin-top:32px;">Delete Account</h2>
        <p style="font-size:14px;">This deletes your account and all your data (tasks, reminders, settings and chat history). This cannot be undone.</p>
        <form method="POST" onsubmit="return confirm('Are you sure? Your account and all your data will be deleted forever.');">
            <input type="hidden" name="form" value="delete_account">
            <label>Type your password to confirm</label>
            <input type="password" name="confirm_password" required>
            <button type="submit" class="btn" style="background:#B00020;">Delete my account</button>
        </form>
    </div>
<?php include __DIR__ . '/includes/sidebar_end.php'; ?>
</body>
</html>
