<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * Общение с пользователем в Telegram (диалог создания напоминания).
 *   php runner -c tg_messages --once   — один проход, печатает полученные сообщения (отладка из практики)
 *   php runner -c tg_messages          — демон
 */
class TgMessagesCommand extends Command
{
    private const MAX_FAILURES = 10;

    public function run(array $options = []): void
    {
        $tgEvents = $this->app->makeTgEvents();

        if (array_key_exists('once', $options)) {
            $this->printMessages($tgEvents->handle());

            return;
        }

        $lock = $this->acquireLock('tg_messages');
        if ($lock === null) {
            $this->err('tg_messages уже запущен — выходим');

            return;
        }

        $stop = false;
        $this->listenForStopSignals(static function () use (&$stop): void {
            $stop = true;
        });

        $this->out('tg_messages запущен (pid ' . getmypid() . ')');

        $failures = 0;
        while (!$stop) {
            try {
                $this->printMessages($tgEvents->handle());
                $failures = 0;
            } catch (\Throwable $e) {
                $this->err('Ошибка: ' . $e->getMessage());
                if (++$failures >= self::MAX_FAILURES) {
                    throw new \RuntimeException('Слишком много ошибок подряд, перезапуск', 0, $e);
                }
                sleep(5);
            }
            // getUpdates уже ждёт до timeout=1 с, дополнительная пауза не нужна
        }

        $this->out('tg_messages остановлен');
    }

    /** @param array<int|string, string[]> $messages */
    private function printMessages(array $messages): void
    {
        foreach ($messages as $chatId => $texts) {
            foreach ($texts as $text) {
                $this->out("[{$chatId}] {$text}");
            }
        }
    }
}
