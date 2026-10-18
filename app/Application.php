<?php

declare(strict_types=1);

namespace App;

use App\Actions\Cron;
use App\Actions\EventHandler;
use App\Actions\EventSaver;
use App\Actions\EventSender;
use App\Actions\TgEvents;
use App\Cache\InMemoryCache;
use App\Cache\Redis;
use App\Commands\Command;
use App\Commands\HandleEventsCommand;
use App\Commands\QueueManagerCommand;
use App\Commands\SaveEventCommand;
use App\Commands\TgMessagesCommand;
use App\Commands\WorkerCommand;
use App\Queue\RabbitMQ;
use App\Repositories\EventRepository;
use App\Repositories\SqliteEventRepository;
use App\Telegram\TelegramApi;
use App\Telegram\TelegramApiImpl;
use Psr\SimpleCache\CacheInterface;

/**
 * Консольный микрофреймворк: разбирает «php runner -c <команда> [--опции]»,
 * загружает .env и является единственным местом, где создаются объекты (фабрика).
 * Команды (App\Commands) только собирают зависимости и передают управление Actions.
 */
class Application
{
    /** @var array<string, class-string<Command>> */
    private const COMMANDS = [
        'save_event' => SaveEventCommand::class,
        'handle_events' => HandleEventsCommand::class,
        'worker' => WorkerCommand::class,
        'tg_messages' => TgMessagesCommand::class,
        'queue_manager' => QueueManagerCommand::class,
    ];

    /** @var array<string, string> */
    private array $env = [];

    public function __construct(private string $basePath)
    {
        $this->loadEnv($this->path('.env'));
        date_default_timezone_set($this->env('TIMEZONE', 'UTC'));
    }

    public function run(): int
    {
        $options = getopt('c:', ['name:', 'receiver:', 'text:', 'cron:', 'once', 'help']);
        $name = $options['c'] ?? null;

        if (!is_string($name) || !isset(self::COMMANDS[$name])) {
            fwrite(STDERR, $this->usage());

            return $name === null || array_key_exists('help', $options) ? 0 : 1;
        }

        $class = self::COMMANDS[$name];

        try {
            (new $class($this))->run($options);
        } catch (\InvalidArgumentException $e) { // в том числе WrongCronString
            fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);

            return 1;
        }

        return 0;
    }

    public function env(string $key, string $default = ''): string
    {
        $value = $this->env[$key] ?? getenv($key);

        return ($value === false || $value === '') ? $default : (string) $value;
    }

    public function path(string $relative = ''): string
    {
        return rtrim($this->basePath, '/') . ($relative === '' ? '' : '/' . ltrim($relative, '/'));
    }

    // ---------- Фабрики зависимостей ----------

    public function makeCron(): Cron
    {
        return new Cron();
    }

    public function makeEventRepository(): EventRepository
    {
        $dbPath = $this->env('DB_PATH', 'storage/events.sqlite');
        $full = str_starts_with($dbPath, '/') ? $dbPath : $this->path($dbPath);

        $pdo = new \PDO('sqlite:' . $full);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $repository = new SqliteEventRepository($pdo);
        $repository->migrate();

        return $repository;
    }

    public function makeEventSaver(): EventSaver
    {
        return new EventSaver($this->makeEventRepository(), $this->makeCron());
    }

    public function makeTelegramApi(): TelegramApi
    {
        $token = $this->env('TELEGRAM_TOKEN');
        if ($token === '') {
            throw new \InvalidArgumentException('Не задан TELEGRAM_TOKEN в .env');
        }

        return new TelegramApiImpl($token);
    }

    public function makeQueue(string $name = 'eventSender'): RabbitMQ
    {
        return new RabbitMQ(
            $name,
            $this->env('RABBITMQ_HOST', '127.0.0.1'),
            (int) $this->env('RABBITMQ_PORT', '5672'),
            $this->env('RABBITMQ_USER', 'guest'),
            $this->env('RABBITMQ_PASSWORD', 'guest'),
        );
    }

    public function makeEventSender(): EventSender
    {
        return new EventSender($this->makeTelegramApi(), $this->makeQueue());
    }

    public function makeEventHandler(): EventHandler
    {
        return new EventHandler($this->makeEventRepository(), $this->makeCron(), $this->makeEventSender());
    }

    public function makeCache(): CacheInterface
    {
        $host = $this->env('REDIS_HOST');
        if ($host === '') {
            return new InMemoryCache(); // без Redis: работает, но offset теряется при перезапуске
        }

        return new Redis(new \Predis\Client([
            'scheme' => 'tcp',
            'host' => $host,
            'port' => (int) $this->env('REDIS_PORT', '6379'),
        ]));
    }

    public function makeTgEvents(): TgEvents
    {
        return new TgEvents(
            $this->makeTelegramApi(),
            $this->makeEventSaver(),
            $this->makeEventSender(),
            $this->makeCache(),
            $this->makeCron(),
        );
    }

    private function loadEnv(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $this->env[trim($key)] = trim($value, " \t\"'");
        }
    }

    private function usage(): string
    {
        return <<<TXT
        Использование: php runner -c <команда> [опции]

        Команды:
          save_event     --name 'Имя' --receiver 'ID' --text 'Текст' --cron '* * * * *'
                         сохранить событие в БД
          handle_events  один проход: отправить события текущей минуты (для crontab)
          worker         демон: проверяет события каждую минуту (supervisor / systemd)
          tg_messages    демон: диалог с пользователями в Telegram (--once — один проход)
          queue_manager  демон: отправляет сообщения из очереди RabbitMQ в Telegram

        TXT;
    }
}
