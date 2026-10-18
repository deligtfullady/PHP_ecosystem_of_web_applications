<?php

declare(strict_types=1);

namespace App\Models;

final class EventDto
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $receiver,
        public readonly string $text,
        public readonly string $cron,
    ) {
    }
}
