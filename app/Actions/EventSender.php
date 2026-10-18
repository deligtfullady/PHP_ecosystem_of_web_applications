<?php

declare(strict_types=1);

namespace App\Actions;

use App\Queue\Queue;
use App\Queue\Queueable;
use App\Telegram\TelegramApi;

/**
 * Отправка сообщений в Telegram через очередь:
 *  - sendMessage() только кладёт сообщение в очередь (быстро, без обращения к Telegram);
 *  - handle() (его вызывает QueueManagerCommand) достаёт сообщение и отправляет в Telegram.
 * Если Telegram недоступен — сообщение возвращается в очередь и будет обработано повторно.
 */
class EventSender implements MessageSender, Queueable
{
    public function __construct(
        private TelegramApi $telegram,
        private Queue $queue,
    ) {
    }

    public function sendMessage(string $receiver, string $message): void
    {
        $this->toQueue($receiver, $message);
    }

    public function toQueue(...$args): void
    {
        [$receiver, $message] = $args;

        $this->queue->sendMessage(json_encode(
            ['receiver' => (string) $receiver, 'text' => (string) $message],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        ));
    }

    public function handle(): void
    {
        $raw = $this->queue->getMessage();
        if ($raw === null) {
            return;
        }

        $payload = $this->decode($raw);
        if ($payload === null) {
            // «ядовитое» сообщение: повторная обработка не поможет — подтверждаем, чтобы не зациклиться
            $this->queue->ackLastMessage();
            throw new \UnexpectedValueException('Некорректное сообщение в очереди отброшено');
        }

        try {
            $this->telegram->sendMessage($payload['receiver'], $payload['text']);
        } catch (\Throwable $e) {
            $this->queue->rejectLastMessage();
            throw $e;
        }

        $this->queue->ackLastMessage();
    }

    /** @return array{receiver: string, text: string}|null */
    private function decode(string $raw): ?array
    {
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) || !isset($data['receiver'], $data['text'])) {
            return null;
        }

        return ['receiver' => (string) $data['receiver'], 'text' => (string) $data['text']];
    }
}
