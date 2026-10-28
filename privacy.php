<?php
// =========================================================
// privacy.php
// Public privacy notice. No login required, so a student can
// read this before deciding to register.
// =========================================================
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Privacy Notice — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
    <div class="container" style="max-width:720px;">
        <a href="index.php" style="text-decoration:none; color:var(--navy); font-weight:bold; font-size:14px; display:inline-block; margin-bottom:16px;">← Back to home</a>
        <h1>Privacy Notice</h1>
        <p class="muted">Last updated: <?= date('j F Y') ?>. This system was built as a final-year Computer Science thesis project. This notice explains what personal data it collects, why, and how it is handled.</p>

        <h2>What data is collected</h2>
        <ul>
            <li><strong>Name and email</strong> — used to create your account and log you in.</li>
            <li><strong>Password</strong> — stored as a secure one-way hash, never as plain text. Nobody, including the developer, can view your actual password.</li>
            <li><strong>Phone number (optional)</strong> — only collected if you choose to enable SMS reminders. Leaving this blank simply means you won't receive SMS reminders; every other feature still works.</li>
            <li><strong>Task data</strong> — titles, modules, deadlines, grade weights, estimated hours and progress, which you enter yourself to use the system.</li>
        </ul>

        <h2>Why it's collected</h2>
        <p>Every piece of data collected has one direct purpose: making the system work for you. Your name and email create and secure your account. Your phone number, if provided, is used solely to deliver deadline reminder texts. Your task data is what the priority recommender uses to calculate and explain its suggestions.</p>

        <h2>Who your data is shared with</h2>
        <ul>
            <li><strong>Twilio</strong> — if you enable SMS reminders, your phone number and reminder message text are sent to Twilio, the service that delivers the actual text message. No other task data is sent to Twilio.</li>
            <li><strong>Google (Gemini API)</strong> — if you use the Study Assistant, your question and the details of the task you selected (title, module, deadline, grade weight, hours, progress) are sent to Google's Gemini API to generate a response. Your name, email, phone number and password are never included in this request.</li>
        </ul>
        <p>Your data is not sold, shared with advertisers, or used for any purpose beyond running this system.</p>

        <h2>Cookies</h2>
        <p>This system uses one cookie: a session identifier that keeps you logged in as you move between pages. This cookie is strictly necessary for the system to function and does not track you across other websites, so no cookie consent banner is required for it.</p>

        <h2>How long data is kept</h2>
        <p>Your data is kept for as long as your account exists. You can request account deletion at any time (see below), after which your account, tasks, reminders and chat history are permanently removed.</p>

        <h2>Your rights</h2>
        <p>You can review, correct, or delete your task data at any time directly within the system. To request full account deletion, contact the developer using the details on your institution's project record. As this is a small-scale academic project rather than a commercial service, deletion requests are handled manually rather than through an automated "delete account" button.</p>

        <h2>Contact</h2>
        <p>This system was built by a Computer Science student as part of a final-year thesis project. For any question about this notice or your data, please contact the developer through the university.</p>
    </div>
</body>
</html>
