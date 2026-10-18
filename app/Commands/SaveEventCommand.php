<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * php runner -c save_event --name 'Имя события' --receiver '123456' --text 'Текст' --cron '* * * * *'
 */
class SaveEventCommand extends Command
{
    public function run(array $options = []): void
    {
        $event = $this->app->makeEventSaver()->save(
            $this->option($options, 'name'),
            $this->option($options, 'receiver'),
            $this->option($options, 'text'),
            $this->option($options, 'cron'),
        );

        $this->out("Событие #{$event->id} «{$event->name}» сохранено (cron: {$event->cron})");
    }
}
