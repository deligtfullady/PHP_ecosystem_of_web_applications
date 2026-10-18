<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\MessageSender;

/** Шпион: запоминает всё, что ему отправили (для случаев, где удобнее проверять накопленный список). */
final class SpyMessageSender implements MessageSender
{
    /** @var array<int, array{0: string, 1: string}> */
    public array $sent = [];

    public function sendMessage(string $receiver, string $message): void
    {
        $this->sent[] = [$receiver, $message];
    }
}
