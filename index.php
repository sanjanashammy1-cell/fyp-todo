<?php
require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>ClariTask — Every task, explained. Know what matters, and why.</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
    <div class="hero">
         <h1>ClariTask</h1> 
         <p class="hero-motto">Every task, explained. Know what matters, and why.</p> <p>A ClariTask built for overlapping module deadlines — with an explainable recommender, SMS reminders, and overload warnings, tuned to how you actually work.</p>
        <a href="register.php" class="btn-accent">Get started</a>
        <a href="login.php" class="btn-outline">Log in</a>

        <div class="hero-illustration">
            <svg viewBox="0 0 220 160" xmlns="http://www.w3.org/2000/svg">
                <rect x="40" y="10" width="140" height="150" rx="14" fill="#ffffff" opacity="0.95"/>
                <rect x="85" y="0" width="50" height="20" rx="8" fill="#FF6B5B"/>
                <rect x="60" y="40" width="18" height="18" rx="4" fill="#0E7C7B"/>
                <path d="M64 49 l4 4 l8 -8" stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="88" y="43" width="70" height="10" rx="5" fill="#DCE6E5"/>
                <rect x="60" y="72" width="18" height="18" rx="4" fill="#0E7C7B"/>
                <path d="M64 81 l4 4 l8 -8" stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="88" y="75" width="70" height="10" rx="5" fill="#DCE6E5"/>
                <rect x="60" y="104" width="18" height="18" rx="4" fill="#FF6B5B"/>
                <rect x="88" y="107" width="55" height="10" rx="5" fill="#DCE6E5"/>
                <rect x="60" y="130" width="18" height="18" rx="4" fill="none" stroke="#B9C4C3" stroke-width="2"/>
                <rect x="88" y="133" width="40" height="10" rx="5" fill="#EDF2F1"/>
            </svg>
        </div>
    </div>

    <div class="features">
        <div class="feature-card">
            <div class="icon">📱</div>
            <h3>SMS + in-app reminders</h3>
            <p>Real text messages before deadlines, with timing and channel fully under your control.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🎯</div>
            <h3>Explainable, tunable recommendations</h3>
            <p>Scored on deadline proximity, grade weighting, workload, and progress — with weights you can adjust.</p>
        </div>
        <div class="feature-card">
            <div class="icon">⚠️</div>
            <h3>Overload warnings</h3>
            <p>Flags when several big deadlines cluster together, before it becomes a crunch.</p>
        </div>
        <div class="feature-card">
            <div class="icon">📈</div>
            <h3>Track your consistency</h3>
            <p>See your completion streak and weekly activity at a glance.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🤖</div>
            <h3>AI study assistant</h3>
            <p>Ask how to start a task and get a realistic day-by-day plan based on its deadline, workload and progress.</p>
        </div>
    </div>

    <footer style="text-align:center; padding:24px; margin-top:20px; color:var(--grey); font-size:12.5px;">
     &copy; <?= date('Y') ?> ClariTask — final year thesis project &middot; <a href="privacy.php" style="color:var(--grey);">Privacy Notice</a>
    </footer>
</body>
</html>
