<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * Один проход: отправляет события, которые должны сработать в текущую минуту.
 * Подходит для crontab:  * * * * * php8.2 /path/runner -c handle_events
 */
class HandleEventsCommand extends Command
{
    public function run(array $options = []): void
    {
        $sent = $this->app->makeEventHandler()->handle(new \DateTimeImmutable());

        $this->out(date('d.m.y H:i') . " Событий к отправке: {$sent}");
    }
}
