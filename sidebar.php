<?php
// =========================================================
// sidebar.php
// Shared left-hand navigation, included at the top of every
// logged-in page. $active_page should be set before including
// this, so the current page highlights correctly.
// =========================================================
$active_page = $active_page ?? '';
?>
<div class="app-shell">
    <div class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">📋 ClariTask</a>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="<?= $active_page === 'dashboard' ? 'active' : '' ?>">🏠 Dashboard</a>
            <a href="assistant.php" class="<?= $active_page === 'assistant' ? 'active' : '' ?>">🤖 Study Assistant</a>
            <a href="calendar.php" class="<?= $active_page === 'calendar' ? 'active' : '' ?>">📅 Calendar</a>
            <a href="subjects.php" class="<?= $active_page === 'subjects' ? 'active' : '' ?>">📚 Subjects</a>
            <a href="analytics.php" class="<?= $active_page === 'analytics' ? 'active' : '' ?>">📊 Analytics</a>
            <a href="notifications.php" class="<?= $active_page === 'notifications' ? 'active' : '' ?>">🔔 Notifications</a>
            <a href="settings.php" class="<?= $active_page === 'settings' ? 'active' : '' ?>">⚙️ Settings</a>
        </nav>
        <div class="sidebar-footer">
            <button type="button" id="theme-toggle" class="theme-toggle-btn" onclick="toggleTheme()">
                <span id="theme-icon">🌙</span> <span id="theme-label">Dark mode</span>
            </button>
            <a href="privacy.php" style="font-size:11.5px;">🔒 Privacy Notice</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
    </div>
    <div class="app-main">

<script>
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    const icon = document.getElementById('theme-icon');
    const label = document.getElementById('theme-label');
    if (icon && label) {
        icon.textContent = theme === 'dark' ? '☀️' : '🌙';
        label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
    }
}
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem('todolist-theme', next);
    applyTheme(next);
}
// Re-apply on this page too (the early script in <head> already set the
// attribute before paint; this just syncs the button's icon/label).
applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

// If the browser restores an old copy of this page (Back button),
// reload it so the server can check the login again.
window.addEventListener('pageshow', function (e) {
    if (e.persisted) { window.location.reload(); }
});
</script>
