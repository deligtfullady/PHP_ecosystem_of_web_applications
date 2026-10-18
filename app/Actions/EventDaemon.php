<?php

declare(strict_types=1);

namespace App\Actions;

use App\Storage\JsonFileStore;

/**
 * Логика демона без pcntl и sleep — поэтому её легко тестировать.
 *
 * Состояние (JSON) переживает перезапуск:
 *  - last_minute  — последняя полностью обработанная минута (unix-время / 60);
 *  - in_progress  — минута, обработка которой началась, но не завершилась (будет повторена);
 *  - stopped_at   — когда и в какую минуту/час/день/месяц/день недели процесс получил сигнал остановки.
 * При новом запуске обрабатываются все пропущенные минуты (не больше $maxCatchUpMinutes назад).
 */
class EventDaemon
{
    private bool $stopRequested = false;

    public function __construct(
        private EventHandler $handler,
        private JsonFileStore $state,
        private int $maxCatchUpMinutes = 60,
    ) {
    }

    /** Вызывается из обработчика SIGTERM / SIGINT / SIGHUP: текущая минута будет доработана, новые не начнутся. */
    public function requestStop(): void
    {
        $this->stopRequested = true;
    }

    public function isStopRequested(): bool
    {
        return $this->stopRequested;
    }

    /** @return int сколько минут обработано за этот вызов */
    public function tick(int $now): int
    {
        $current = intdiv($now, 60);
        $state = $this->state->load();

        if (isset($state['in_progress'])) {
            $next = (int) $state['in_progress'];
        } elseif (isset($state['last_minute'])) {
            $next = (int) $state['last_minute'] + 1;
        } else {
            $next = $current;
        }
        $next = max($next, $current - $this->maxCatchUpMinutes);

        $processed = 0;
        for ($minute = $next; $minute <= $current; $minute++) {
            if ($this->stopRequested) {
                break;
            }

            $state['in_progress'] = $minute;
            $this->state->save($state);

            $this->handler->handle((new \DateTimeImmutable())->setTimestamp($minute * 60));

            $state['last_minute'] = $minute;
            unset($state['in_progress']);
            $this->state->save($state);
            $processed++;
        }

        return $processed;
    }

    /** Фиксирует момент остановки (минута, час, день, месяц, день недели) для нового запуска. */
    public function markStopped(int $now): void
    {
        $at = (new \DateTimeImmutable())->setTimestamp($now);
        $state = $this->state->load();
        $state['stopped_at'] = [
            'timestamp' => $now,
            'minute' => (int) $at->format('i'),
            'hour' => (int) $at->format('G'),
            'day' => (int) $at->format('j'),
            'month' => (int) $at->format('n'),
            'weekday' => (int) $at->format('w'),
        ];
        $this->state->save($state);
    }
}
