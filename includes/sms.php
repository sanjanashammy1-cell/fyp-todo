<?php
// =========================================================
// sms.php
// Sends real SMS reminders using Twilio's REST API directly
// via cURL — no SDK/Composer install needed, which keeps this
// runnable on a plain XAMPP setup.
//
// SETUP (free): sign up at twilio.com/try-twilio, verify your
// own phone number (free trial accounts can only text verified
// numbers — that's fine for a thesis demo), then copy your
// Account SID, Auth Token, and trial phone number below.
// =========================================================

const TWILIO_ACCOUNT_SID = "YOUR_ACCOUNT_SID_HERE";
const TWILIO_AUTH_TOKEN  = "YOUR_AUTH_TOKEN_HERE";
const TWILIO_FROM_NUMBER = "+1XXXXXXXXXX"; // your Twilio trial number

/**
 * Sends one SMS. Returns true on success, false on failure —
 * never throws, because a failed text should never break the
 * dashboard for the student using the app.
 */
function send_sms(string $to, string $message): bool {
    if (TWILIO_ACCOUNT_SID === "YOUR_ACCOUNT_SID_HERE") {
        // Twilio not configured yet — fail quietly rather than crash.
        error_log("SMS not sent (Twilio not configured): $message");
        return false;
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";
    $data = ['To' => $to, 'From' => TWILIO_FROM_NUMBER, 'Body' => $message];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status >= 200 && $status < 300;
}

/**
 * Builds the actual reminder text for a task.
 */
function build_reminder_message(array $task): string {
    $module = !empty($task['module_name']) ? " ({$task['module_name']})" : "";
    return "Reminder: \"{$task['title']}\"{$module} is due {$task['due_date']}. — Your To-Do List";
}
