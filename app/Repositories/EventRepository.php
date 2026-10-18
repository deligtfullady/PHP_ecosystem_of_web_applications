<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\EventDto;

interface EventRepository
{
    /** Сохраняет событие и возвращает его идентификатор. */
    public function save(EventDto $event): int;

    /** @return EventDto[] */
    public function all(): array;
}
