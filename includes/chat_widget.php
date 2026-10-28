<?php
// =========================================================
// chat_widget.php
// Reusable "Study Assistant" chat box (AI component).
// Before including, set:
//   $chat_tasks    - array of the student's tasks (rows from `tasks`)
//   $chat_selected - task_id to pre-select (optional)
// The box sends questions to ai_chat.php, which adds the task's
// real data to the prompt and asks the Gemini LLM for a plan.
// The priority score itself always comes from recommender.php,
// never from the AI.
// =========================================================
$chat_tasks = $chat_tasks ?? [];
$chat_selected = $chat_selected ?? null;
?>
<div class="chat-box" id="ai-chat">
    <p class="chat-note">🤖 AI suggestions can be wrong. Your task priority is calculated by the app's recommender, not the AI.</p>

    <?php if (empty($chat_tasks)): ?>
        <p class="muted">Add a task first, then ask the assistant how to plan it.</p>
    <?php else: ?>
        <label for="chat-task">Which task do you need help with?</label>
        <select id="chat-task">
            <?php foreach ($chat_tasks as $t): ?>
                <option value="<?= (int) $t['task_id'] ?>" <?= ((int) $t['task_id'] === (int) $chat_selected) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['title']) ?>
                    <?php if (!empty($t['module_name'])): ?>&mdash; <?= htmlspecialchars($t['module_name']) ?><?php endif; ?>
                    (due <?= htmlspecialchars($t['due_date']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <div class="chat-suggestions">
            <button type="button" class="chat-chip">How should I start this task?</button>
            <button type="button" class="chat-chip">Make me a day-by-day plan</button>
            <button type="button" class="chat-chip">What should I do today?</button>
        </div>

        <div class="chat-messages" id="chat-messages">
            <div class="chat-msg assistant">Hi! Pick a task and ask me how to plan it. I use its deadline, workload and progress to suggest realistic steps.</div>
        </div>

        <form class="chat-form" id="chat-form">
            <input type="text" id="chat-input" placeholder="Ask about this task..." maxlength="1000" autocomplete="off" required>
            <button type="submit" class="btn chat-send">Send</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!empty($chat_tasks)): ?>
<script>
(function () {
    const messages = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const taskSelect = document.getElementById('chat-task');
    const sendBtn = form.querySelector('button');

    function addMessage(text, type) {
        const div = document.createElement('div');
        div.className = 'chat-msg ' + type;
        div.textContent = text; // textContent = safe, never runs HTML
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    async function ask(question) {
        addMessage(question, 'user');
        const thinking = addMessage('Thinking…', 'assistant thinking');
        sendBtn.disabled = true;
        try {
            const response = await fetch('study_help.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: question, task_id: taskSelect.value })
            });
            const data = await response.json();
            thinking.remove();
            addMessage(data.reply || data.error || 'Something went wrong.', data.reply ? 'assistant' : 'error');
        } catch (err) {
            thinking.remove();
            addMessage('Could not reach the server. Please try again.', 'error');
        }
        sendBtn.disabled = false;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question) return;
        input.value = '';
        ask(question);
    });

    document.querySelectorAll('.chat-chip').forEach((chip) => {
        chip.addEventListener('click', () => ask(chip.textContent));
    });

    taskSelect.addEventListener('change', () => {
        messages.innerHTML = '';
        addMessage('Task changed. What would you like to know about it?', 'assistant');
    });
})();
</script>
<?php endif; ?>
