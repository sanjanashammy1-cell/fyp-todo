<?php
// ai_chat.php - AI study assistant endpoint
require_once __DIR__ . '/includes/auth.php'; // database ($pdo), session, current_user_id()
require_once __DIR__ . '/config.php';        // Gemini API key + callGemini()
header('Content-Type: application/json');

function sendJson(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

// 1. Only logged-in users can use the assistant
$userId = current_user_id();
if (!$userId) {
    sendJson(401, ['error' => 'Please log in first.']);
}
$userId = (int) $userId;

// 2. Read the question sent by chat.js (as JSON)
$input = json_decode(file_get_contents('php://input'), true);
$question = trim($input['message'] ?? '');
$taskId = !empty($input['task_id']) ? (int) $input['task_id'] : null;

if ($question === '' || mb_strlen($question) > 1000) {
    sendJson(400, ['error' => 'Please write a question (max 1000 characters).']);
}

// 3. Load the task - only if it belongs to this user
$taskText = 'No specific task selected.';
if ($taskId) {
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE task_id = ? AND user_id = ?');
    $stmt->execute([$taskId, $userId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        sendJson(404, ['error' => 'Task not found.']);
    }

    // Build a short description using only task details (no personal data)
    $lines = ['Task: ' . ($task['title'] ?? $task['module_name'] ?? 'Untitled task')];
    if (!empty($task['module_name']))        $lines[] = "Module: {$task['module_name']}";
    if (!empty($task['due_date'])) {
        $due = strtotime(date('Y-m-d', strtotime($task['due_date'])));
        $daysLeft = (int) floor(($due - strtotime('today')) / 86400);
        $lines[] = "Due date: {$task['due_date']} ($daysLeft days from today)";
    }
    if (isset($task['assessment_weight']))   $lines[] = "Assessment weight: {$task['assessment_weight']}%";
    if (isset($task['estimated_hours']))     $lines[] = "Estimated total hours: {$task['estimated_hours']}";
    if (isset($task['progress_percent']))    $lines[] = "Progress so far: {$task['progress_percent']}%";
    if (isset($task['estimated_hours'], $task['progress_percent'])) {
        $remaining = round($task['estimated_hours'] * (100 - $task['progress_percent']) / 100, 1);
        $lines[] = "Remaining work: about $remaining hours";
    }
    if (!empty($task['status']))             $lines[] = "Status: {$task['status']}";
    if (isset($task['priority_score']))      $lines[] = "Priority score from the app: {$task['priority_score']}";
    $taskText = implode("\n", $lines);
}

// 4. Last 6 messages, so the AI remembers the conversation
$stmt = $pdo->prepare(
    'SELECT role, message FROM chat_messages
     WHERE user_id = ? AND task_id <=> ?
     ORDER BY id DESC LIMIT 6'
);
$stmt->execute([$userId, $taskId]);
$history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
$historyText = '';
foreach ($history as $row) {
    $who = $row['role'] === 'user' ? 'Student' : 'Assistant';
    $historyText .= "$who: {$row['message']}\n";
}

// 5. Build the prompt: fixed rules (system instruction) + task data + question
$system = <<<TXT
You are a study planning assistant inside a ClariTask app for university students.
Rules:
- Base your answer on the task details given: days left and remaining hours.
- Give short, practical next steps or a day-by-day plan (max about 200 words).
- Be realistic: no more than 3-4 hours of work on one day.
- Do not change or argue with the app's priority score; the app calculates it.
- If information is missing, say what you are assuming.
- Only help with study planning and time management; politely decline other requests.
- Do not write the assignment for the student; help them plan and start it.
- Use plain text with simple numbered steps, no markdown symbols like # or *.
TXT;

$prompt = 'Today is ' . date('Y-m-d') . ".\n\n"
    . "TASK DETAILS:\n$taskText\n\n"
    . "RECENT CONVERSATION:\n" . ($historyText ?: "(none)\n") . "\n"
    . "STUDENT'S QUESTION:\n$question";

// 6. Ask Gemini (friendly message if it fails)
try {
    $reply = callGemini($system, $prompt);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendJson(502, ['error' => 'The AI assistant is having trouble responding right now. Please try again in a moment.']);
}

// 7. Save the question and the answer
$stmt = $pdo->prepare('INSERT INTO chat_messages (user_id, task_id, role, message) VALUES (?, ?, ?, ?)');
$stmt->execute([$userId, $taskId, 'user', $question]);
$stmt->execute([$userId, $taskId, 'assistant', $reply]);

// 8. Send the answer back to the browser as JSON
sendJson(200, ['reply' => $reply]);