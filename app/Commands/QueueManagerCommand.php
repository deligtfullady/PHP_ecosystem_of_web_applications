<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * Обработчик очереди: достаёт сообщения из RabbitMQ и отправляет их в Telegram.
 * Запуск: php runner -c queue_manager
 */
class QueueManagerCommand extends Command
{
    private const MAX_FAILURES = 10;

    public function run(array $options = []): void
    {
        $lock = $this->acquireLock('queue_manager');
        if ($lock === null) {
            $this->err('queue_manager уже запущен — выходим');

            return;
        }

        $sender = $this->app->makeEventSender();

        $stop = false;
        $this->listenForStopSignals(static function () use (&$stop): void {
            $stop = true;
        });

        $this->out('queue_manager запущен (pid ' . getmypid() . ')');

        $failures = 0;
        while (!$stop) {
            try {
                $sender->handle(); // одно сообщение; при ошибке оно вернётся в очередь
                $failures = 0;
            } catch (\Throwable $e) {
                $this->err('Ошибка отправки: ' . $e->getMessage());
                if (++$failures >= self::MAX_FAILURES) {
                    throw new \RuntimeException('Слишком много ошибок подряд, перезапуск', 0, $e);
                }
                sleep(5); // пауза перед повтором (Telegram недоступен / лимиты)
            }

            usleep(100_000);
        }

        $this->out('queue_manager остановлен');
    }
}
