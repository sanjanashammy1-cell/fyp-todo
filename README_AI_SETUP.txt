ClariTask with AI Study Assistant - setup
==========================================
1. Copy the "todolist" folder into C:\xampp\htdocs\
2. Start Apache and MySQL in XAMPP.
3. In phpMyAdmin, create a database called "todolist" and import database.sql.
4. AI Study Assistant (optional): get a free API key from Google AI Studio
   (aistudio.google.com) and paste it into config.php where it says
   PASTE-YOUR-GEMINI-API-KEY-HERE. The rest of the app works without it.
5. Open http://localhost/todolist/ , register, log in, add a task,
   then open "Study Assistant" in the sidebar.

AI component files:
- ai_chat.php               backend: loads the task, builds the prompt, calls Gemini, saves the chat
- config.php                Gemini API key, model name and callGemini() function
- assistant.php             full-page Study Assistant
- includes/chat_widget.php  reusable chat box (also used in the dashboard side panel)
- chat_messages table       stores questions and answers (in database.sql)
