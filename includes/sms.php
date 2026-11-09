<?php
// =========================================================
// sms.php
// Sends SMS reminders through Twilio's REST API using cURL.
// Twilio was chosen because it is a cloud API: no Android phone
// has to stay switched on (TextBee and SMSMobileAPI needed one).
//
// The real keys are only on the server. The copy on GitHub and in
// the submission ZIP has PASTE-... placeholders instead (NFR3).
// =========================================================

const TWILIO_ACCOUNT_SID = "PASTE-YOUR-TWILIO-ACCOUNT-SID-HERE";
const TWILIO_AUTH_TOKEN  = "PASTE-YOUR-TWILIO-AUTH-TOKEN-HERE";
// The SMS shows "ClariTask" as the sender instead of a phone number.
const TWILIO_FROM_NUMBER = "ClariTask"; // Alphanumeric Sender ID (name instead of a number, max 11 letters)
// Secret key so that only the timer service can run send_reminders.php online.
const CRON_SECRET = "PASTE-A-LONG-RANDOM-SECRET-HERE"; // secret key for send_reminders.php (keep private)

/**
 * Sends one SMS via Twilio. Returns true on success, false on
 * failure — never throws, because a failed text should never
 * break the dashboard for the student using the app.
 */
function send_sms(string $to, string $message): bool {
    // If the keys are still placeholders, do not try to send (the app keeps working).
    if (TWILIO_ACCOUNT_SID === "" || strpos(TWILIO_ACCOUNT_SID, "PASTE-") === 0) {
        // Twilio not configured yet — fail quietly rather than crash.
        error_log("SMS not sent (Twilio not configured): $message");
        return false;
    }

    // Twilio's endpoint for sending one message.
    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";
    $data = ['To' => $to, 'From' => TWILIO_FROM_NUMBER, 'Body' => $message];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    // Twilio uses HTTP Basic authentication: account SID and auth token.
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Give up after 8 seconds so a slow network never freezes the dashboard.
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Any HTTP status outside 200-299 means Twilio did not accept the message.
    if ($status < 200 || $status >= 300) {
        error_log("Twilio SMS failed (HTTP $status): $response");
        return false;
    }

    return true;
}

// The text of the SMS: task, module and deadline, as students asked for.
function build_reminder_message(array $task): string {
    $module = !empty($task['module_name']) ? $task['module_name'] : 'Not set';
    $deadline = date('j M Y', strtotime($task['due_date']));
    return "ClariTask reminder\n"
         . "Task: {$task['title']}\n"
         . "Module: {$module}\n"
         . "Deadline: {$deadline}\n"
         . "Open ClariTask to see which task to do first.";
}
