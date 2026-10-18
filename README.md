# Курс «Вокруг PHP — экосистема веб-приложений»
## Выполнила Емельянова Ирина

Консольное PHP-приложение: пользователь через Telegram создаёт напоминания с cron-расписанием,
демон отправляет их в нужную минуту. Проект собран по заданиям семинаров 1–7.

| Урок | Что реализовано | Где |
|---|---|---|
| 1. Консольный PHP | `save_event`, обработчик событий, демон с обработкой SIGTERM/SIGINT/SIGHUP, crontab, supervisor, systemd | `app/Commands`, `app/Actions/Cron.php`, `EventHandler.php`, `EventDaemon.php`, `deploy/` |
| 2. Backend API | Telegram API (`getUpdates`, `sendMessage`), диалог `/start` → событие | `app/Telegram`, `app/Actions/TgEvents.php` |
| 4–5. Тесты | PHPUnit: DataProvider в отдельных классах, TestWith, Mock/Stub, покрытие, мутационные тесты | `tests/`, `phpunit.xml`, `infection.json` |
| 6. Кэширование | PSR-16 адаптер Redis; кэш offset и истории сообщений | `app/Cache` |
| 7. Очереди | RabbitMQ-адаптер, `EventSender` через очередь, обработчик очереди | `app/Queue`, `QueueManagerCommand.php` |
