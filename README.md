# ClariTask

ClariTask is a web-based to-do list for students. It ranks tasks by urgency, grade weight, workload and progress, explains each ranking in plain language, warns about deadline clashes, sends reminders in the app and by SMS, and has an AI Study Assistant that suggests how to start a task.

- **Author:** Sanjana Shammy (P2863784), BSc (Hons) Computer Science, De Montfort University / Niels Brock
- **Module:** CTEC3451N Final Year Project
- **Online version:** https://claritask.infinityfree.me

## Requirements

- XAMPP (PHP 8 and MySQL/MariaDB): https://www.apachefriends.org
- A web browser (tested in Google Chrome on desktop)
- Optional: a Google Gemini API key (for the AI Study Assistant)
- Optional: a Twilio account (for SMS reminders)

The app works without the optional keys. Without Gemini the Study Assistant shows a friendly error, and without Twilio reminders are only shown in the app.

## Install and run (on your own computer)

1. Install XAMPP and open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and **MySQL**.
3. Copy the project folder into `C:\xampp\htdocs\` and name it `todolist`, so you have `C:\xampp\htdocs\todolist\index.php`.
4. Open http://localhost/phpmyadmin in the browser.
5. Click **Import**, choose `database.sql` from the project folder, and click **Import** (or **Go**). This creates the `todolist` database and its eight tables.
6. Open http://localhost/todolist/ in the browser.
7. Click **Register**, create an account (a made-up email address is fine), and log in.
8. Add a few tasks with a due date, grade weight, hours and progress. The dashboard shows "Today's Focus" with the ranking and the reason for each task.

The database settings are in `includes/config.php`. They use the XAMPP defaults (host `localhost`, user `root`, empty password, database `todolist`). Change them only if your MySQL is set up differently.

## Optional: AI Study Assistant (Google Gemini)

1. Get a free API key from Google AI Studio: https://aistudio.google.com
2. Open `config.php` (in the main project folder).
3. Replace `PASTE-YOUR-GEMINI-API-KEY-HERE` with your key and save.
4. Open a task and click **Study Assistant**.

## Optional: SMS reminders (Twilio)

1. Create a Twilio account: https://www.twilio.com
2. Open `includes/sms.php`.
3. Replace the placeholders `PASTE-YOUR-TWILIO-ACCOUNT-SID-HERE`, `PASTE-YOUR-TWILIO-AUTH-TOKEN-HERE` and `PASTE-A-LONG-RANDOM-SECRET-HERE` with your own values and save.
4. In ClariTask, open **Settings**, enter your phone number in international format (for example +45...), and switch SMS on.

Each SMS has a small cost on a paid Twilio account.

## Background reminders

`send_reminders.php` sends due SMS reminders for all students, even when they do not open ClariTask. Run it every 15 minutes:

- **On a laptop:** in Windows Task Scheduler, create a task that runs
  `C:\xampp\php\php.exe C:\xampp\htdocs\todolist\send_reminders.php`
- **Online:** a timer service (for example cron-job.org) opens
  `https://YOUR-SITE/send_reminders.php?key=YOUR-CRON-SECRET`

Without this job, reminders are still checked every time a student opens the dashboard.

## Automated tests

Open http://localhost/todolist/tests/browser.php. The page runs 43 unit tests of the priority recommender and the deadline-clash warning and shows PASS or FAIL for each one.

## Online deployment (InfinityFree)

1. Create a free hosting account and a MySQL database on InfinityFree.
2. In its phpMyAdmin, import `database_online.sql` (it does not contain `CREATE DATABASE`, because the host creates the database for you).
3. Put the host's database details in `includes/config.php`.
4. Upload all project files to the `htdocs` folder with the host's file manager or FTP.

## Main files

| File | Purpose |
|---|---|
| `index.php`, `register.php`, `login.php`, `logout.php` | Start page and accounts |
| `dashboard.php` | Today's Focus, priority ranking, explanations, clash warning, reminders |
| `add_task.php`, `edit_task.php`, `complete_task.php`, `delete_task.php` | Task management |
| `calendar.php`, `subjects.php`, `analytics.php` | Calendar, subject view and progress charts |
| `settings.php` | Recommender weights, reminder times, SMS, delete account |
| `notifications.php` | Reminder history |
| `assistant.php`, `study_help.php`, `includes/chat_widget.php`, `config.php` | AI Study Assistant |
| `includes/recommender.php` | Priority scores and explanations |
| `includes/clustering.php` | Deadline-clash warning |
| `includes/reminders.php`, `includes/sms.php`, `send_reminders.php` | In-app and SMS reminders |
| `includes/auth.php`, `includes/config.php` | Login check and database connection |
| `database.sql`, `database_online.sql`, `migration_add_advanced_tables.sql` | Database scripts |
| `tests/` | Automated unit tests |

## Security note

Real API keys are **not** included in this folder. Every query uses prepared statements, passwords are stored with `password_hash()`, and each student can only see their own tasks.
