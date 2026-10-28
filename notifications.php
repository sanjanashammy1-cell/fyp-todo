<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = current_user_id();

// Every reminder ever scheduled for this student's tasks, most recent first.
// This reuses the existing reminders table (remind_at, sent, sms_sent) —
// no new table needed, this page just gives that data somewhere to live.
$stmt = $pdo->prepare(
    "SELECT r.reminder_id, r.remind_at, r.sent, r.sms_sent, t.title, t.module_name, t.due_date
     FROM reminders r
     JOIN tasks t ON t.task_id = r.task_id
     WHERE t.user_id = ?
     ORDER BY r.remind_at DESC
     LIMIT 30"
);
$stmt->execute([$user_id]);
$reminders = $stmt->fetchAll();

$active_page = 'notifications';
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Notifications — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="container" style="max-width:640px;">
        <h1>Notifications</h1>
        <p class="muted">Every deadline reminder scheduled for your tasks, most recent first. Manage timing and SMS on/off in <a href="settings.php">Settings</a>.</p>

        <?php if (empty($reminders)): ?>
            <p class="muted">No reminders yet — they're created automatically when you add a task with a due date.</p>
        <?php endif; ?>

        <?php foreach ($reminders as $r):
            $isPast = strtotime($r['remind_at']) <= time();
            $status = !$isPast ? 'Scheduled' : ($r['sent'] ? 'Shown in-app' : 'Pending');
        ?>
            <div class="notif-row">
                <div class="notif-icon"><?= $r['sms_sent'] ? '📱' : '🔔' ?></div>
                <div style="flex:1;">
                    <div class="notif-title"><?= htmlspecialchars($r['title']) ?></div>
                    <div class="notif-meta">
                        <?php if (!empty($r['module_name'])): ?><?= htmlspecialchars($r['module_name']) ?> &middot; <?php endif; ?>
                        Due <?= htmlspecialchars($r['due_date']) ?>
                        &middot; Reminder time: <?= date('j M, g:ia', strtotime($r['remind_at'])) ?>
                        &middot; <?= $status ?><?= $r['sms_sent'] ? ' + SMS sent' : '' ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php include __DIR__ . '/includes/sidebar_end.php'; ?>
</body>
</html>
