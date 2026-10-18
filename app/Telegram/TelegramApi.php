<?php

declare(strict_types=1);

namespace App\Telegram;

interface TelegramApi
{
    /**
     * Получает новые сообщения (getUpdates).
     *
     * @param int $offset идентификатор первого нужного update (предыдущий update_id + 1)
     * @return array{offset: int, result: array<int|string, string[]>}
     *         offset — значение для следующего вызова; result — тексты сообщений по chat_id
     */
    public function getMessages(int $offset): array;

    /** Отправляет сообщение (sendMessage). */
    public function sendMessage(string $receiver, string $text): void;
}
