<?php

declare(strict_types=1);

namespace App\Commands;

use App\Application;

abstract class Command
{
    public function __construct(protected Application $app)
    {
    }

    /** @param array<string, mixed> $options опции командной строки из getopt */
    abstract public function run(array $options = []): void;

    protected function out(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    protected function err(string $message): void
    {
        fwrite(STDERR, date('d.m.y H:i:s') . ' ' . $message . PHP_EOL);
    }

    /** @param array<string, mixed> $options */
    protected function option(array $options, string $name): string
    {
        $value = $options[$name] ?? null;
        $value = is_array($value) ? ($value[0] ?? null) : $value;

        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Не указан параметр --{$name}");
        }

        return $value;
    }

    /**
     * Остановка по SIGTERM / SIGINT / SIGHUP (supervisor, systemd, Ctrl+C, reload).
     * Обработчик только ставит флаг — текущая операция доделывается, затем цикл завершается.
     */
    protected function listenForStopSignals(callable $onStop): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT, SIGHUP] as $signal) {
            pcntl_signal($signal, static function () use ($onStop): void {
                $onStop();
            });
        }
    }

    /** Блокировка от запуска второй копии демона. Возвращает дескриптор (держать до конца работы) или null. */
    protected function acquireLock(string $name)
    {
        $handle = fopen($this->app->path('storage/' . $name . '.lock'), 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            return null;
        }

        return $handle;
    }
}
