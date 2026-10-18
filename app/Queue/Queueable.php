<?php

declare(strict_types=1);

namespace App\Queue;

interface Queueable
{
    /** Берёт одно сообщение из очереди и обрабатывает его (если очередь пуста — ничего не делает). */
    public function handle(): void;

    /** Кладёт задачу в очередь. */
    public function toQueue(...$args): void;
}
