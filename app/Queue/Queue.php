<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * Адаптер очереди. Обработчик работает в один поток, поэтому «последнее полученное
 * сообщение» хранится в адаптере и подтверждается/отклоняется без передачи тега.
 */
interface Queue
{
    public function sendMessage($message): void;

    public function getMessage(): ?string;

    /** Подтверждает (ack) последнее полученное сообщение. */
    public function ackLastMessage(): void;

    /** Возвращает последнее полученное сообщение в очередь (nack + requeue) для повторной обработки. */
    public function rejectLastMessage(): void;
}
