<?php
// =========================================================
// sms.php
// Sends real SMS reminders using Twilio's REST API directly
// via cURL — a pure cloud API, no phone pairing required.
// Sign up free at twilio.com/try-twilio (no credit card needed),
// verify your own number as the recipient, then fill in the
// three constants below.
// =========================================================

const TWILIO_ACCOUNT_SID = "YOUR-TWILIO-SID";
const TWILIO_AUTH_TOKEN  = "YOUR-TWILIO-TOKEN";
const TWILIO_FROM_NUMBER = "YOUR-TWILIO-NUMBER"; // your Twilio trial number, shown on the Console dashboard

/**
 * Sends one SMS via Twilio. Returns true on success, false on
 * failure — never throws, because a failed text should never
 * break the dashboard for the student using the app.
 */
function send_sms(string $to, string $message): bool {
    if (TWILIO_ACCOUNT_SID === "YOUR-TWILIO-SID") {
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

    if ($status < 200 || $status >= 300) {
        error_log("Twilio SMS failed (HTTP $status): $response");
        return false;
    }

    return true;
}

function build_reminder_message(array $task): string {
    $module = !empty($task['module_name']) ? " ({$task['module_name']})" : "";
    return "Reminder: \"{$task['title']}\"{$module} is due {$task['due_date']}. — Your ClariTask";
}
