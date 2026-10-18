<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\EventDaemon;
use App\Storage\JsonFileStore;

/**
 * Демон: бесконечный цикл, раз в минуту вызывает EventHandler.
 * Запуск: supervisor (deploy/supervisor) или systemd (deploy/systemd/worker.service).
 * SIGTERM / SIGINT / SIGHUP: доработать текущую минуту, сохранить позицию и выйти;
 * после перезапуска пропущенные минуты будут обработаны.
 */
class WorkerCommand extends Command
{
    private const MAX_FAILURES = 10;

    public function run(array $options = []): void
    {
        $lock = $this->acquireLock('worker');
        if ($lock === null) {
            $this->err('worker уже запущен — выходим');

            return;
        }

        $daemon = new EventDaemon(
            $this->app->makeEventHandler(),
            new JsonFileStore($this->app->path('storage/worker_state.json')),
        );

        $this->listenForStopSignals(static function () use ($daemon): void {
            $daemon->requestStop();
        });

        $this->out('worker запущен (pid ' . getmypid() . ')');

        $failures = 0;
        while (!$daemon->isStopRequested()) {
            try {
                $daemon->tick(time());
                $failures = 0;
            } catch (\Throwable $e) {
                $this->err('Ошибка обработки: ' . $e->getMessage());
                if (++$failures >= self::MAX_FAILURES) {
                    // соединение могло «протухнуть» — падаем, supervisor/systemd перезапустит процесс
                    throw new \RuntimeException('Слишком много ошибок подряд, перезапуск', 0, $e);
                }
                sleep(5); // не молотим при недоступном RabbitMQ/БД; незавершённая минута будет повторена
                continue;
            }

            sleep(1); // сигнал прерывает sleep, цикл сразу увидит флаг остановки
        }

        $daemon->markStopped(time());
        $this->out('worker остановлен, позиция сохранена');
    }
}
