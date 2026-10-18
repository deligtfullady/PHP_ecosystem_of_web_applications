# Git: ветки и pull request для каждого урока

Каждое домашнее задание — новая ветка от ветки предыдущего урока и pull request.

```bash
# первый раз
git init && git add . && git commit -m "Урок 1: консольный PHP, демон"
git branch -M lesson-1
git remote add origin https://github.com/deligtfullady/PHP_ecosystem_of_web_applications.git
git push -u origin lesson-1

# каждый следующий урок — ветка от предыдущей
git switch -c lesson-2 lesson-1
# ... правки ...
git add -A && git commit -m "Урок 2: Telegram API"
git push -u origin lesson-2
```

Дальше откройте PR `lesson-2 → lesson-1` (или в `main`) на GitHub и пришлите ссылку.
Не коммитьте `.env`, `vendor/`, `storage/*` и `covers_html/` — они уже в `.gitignore`.

Так как весь проект собран сразу, можно оформить один PR; если хотите по урокам — разложите коммиты:
1) `Cron`, `EventSaver`, `EventHandler`, `EventDaemon`, команды, `deploy/`; 2) `app/Telegram`, `TgEvents`;
4–5) `tests/`, `phpunit.xml`, `infection.json`; 6) `app/Cache`; 7) `app/Queue`, `EventSender`, `QueueManagerCommand`.
