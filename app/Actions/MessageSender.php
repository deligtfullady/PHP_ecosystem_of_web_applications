<?php

declare(strict_types=1);

namespace App\Actions;

/** Контракт «отправить сообщение получателю» — то, что нужно EventHandler и TgEvents. */
interface MessageSender
{
    public function sendMessage(string $receiver, string $message): void;
}
