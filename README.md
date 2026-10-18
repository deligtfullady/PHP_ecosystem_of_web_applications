# Бот-напоминалка (курс «Вокруг PHP — экосистема веб-приложений»)

Консольное PHP-приложение: пользователь через Telegram создаёт напоминания с cron-расписанием,
демон отправляет их в нужную минуту. Проект собран по заданиям семинаров 1–7.

| Урок | Что реализовано | Где |
|---|---|---|
| 1. Консольный PHP | `save_event`, обработчик событий, демон с обработкой SIGTERM/SIGINT/SIGHUP, crontab, supervisor, systemd | `app/Commands`, `app/Actions/Cron.php`, `EventHandler.php`, `EventDaemon.php`, `deploy/` |
| 2. Backend API | Telegram API (`getUpdates`, `sendMessage`), диалог `/start` → событие | `app/Telegram`, `app/Actions/TgEvents.php` |
| 4–5. Тесты | PHPUnit: DataProvider в отдельных классах, TestWith, Mock/Stub, покрытие, мутационные тесты | `tests/`, `phpunit.xml`, `infection.json` |
| 6. Кэширование | PSR-16 адаптер Redis; кэш offset и истории сообщений | `app/Cache` |
| 7. Очереди | RabbitMQ-адаптер, `EventSender` через очередь, обработчик очереди | `app/Queue`, `QueueManagerCommand.php` |

## Установка

```bash
sudo apt install php8.2-cli php8.2-curl php8.2-sqlite3 php8.2-xdebug redis-server rabbitmq-server supervisor
php8.2 composer.phar install          # или: composer install
cp .env.example .env                  # впишите TELEGRAM_TOKEN
```

## Команды

```bash
php8.2 runner -c save_event --name 'Планерка' --receiver '123456789' --text 'Планерка через 5 минут' --cron '55 9 * * 1-5'
php8.2 runner -c handle_events        # один проход (для crontab)
php8.2 runner -c worker               # демон: проверка событий раз в минуту
php8.2 runner -c tg_messages          # демон: диалог в Telegram (--once — один проход с выводом сообщений)
php8.2 runner -c queue_manager        # демон: отправка сообщений из очереди RabbitMQ в Telegram
```

Рабочая схема целиком: `worker` и `tg_messages` кладут сообщения в очередь RabbitMQ, `queue_manager` отправляет их в Telegram.
Нужны все три процесса (`deploy/supervisor/reminder.conf` или `deploy/systemd/*.service`), а также Redis и RabbitMQ.
Без `REDIS_HOST` в `.env` используется кэш в памяти (offset теряется при перезапуске).

## Тесты

```bash
php8.2 ./vendor/bin/phpunit                                  # unit-тесты (suite по умолчанию)
php8.2 ./vendor/bin/phpunit --testsuite demo                 # «сломанные» тесты: все типы результатов
XDEBUG_MODE=coverage php8.2 ./vendor/bin/phpunit --coverage-html ./covers_html
XDEBUG_MODE=coverage vendor/bin/infection                    # мутационное тестирование
```

Команды (`app/Commands`), `Application`, адаптеры Redis/RabbitMQ и `CurlHttpClient` обращаются к внешним системам,
поэтому исключены из покрытия; вся логика вынесена в `app/Actions` и покрыта тестами.

## Структура

```
runner                      точка входа
app/Application.php         микрофреймворк: разбор -c, .env, фабрики зависимостей
app/Commands/               тонкие команды — собирают объекты и передают управление
app/Actions/                логика: Cron, EventSaver, EventHandler, EventDaemon, EventSender, TgEvents
app/Telegram|Cache|Queue    адаптеры внешних систем (интерфейс + реализация)
app/Repositories|Storage    SQLite и файл состояния демона
tests/  tests-demo/         unit-тесты и демонстрация типов результатов
deploy/                     crontab, supervisor, systemd
```
