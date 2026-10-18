<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\WrongCronString;
use App\Models\EventDto;
use App\Repositories\EventRepository;

class EventSaver
{
    public function __construct(
        private EventRepository $repository,
        private Cron $cron,
    ) {
    }

    /**
     * @throws \InvalidArgumentException пустое поле
     * @throws WrongCronString неверное cron-расписание
     */
    public function save(string $name, string $receiver, string $text, string $cron): EventDto
    {
        foreach (['название' => $name, 'получатель' => $receiver, 'текст' => $text] as $field => $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException("Поле «{$field}» не может быть пустым");
            }
        }

        $this->cron->validate($cron);

        $event = new EventDto(null, $name, $receiver, $text, trim($cron));
        $id = $this->repository->save($event);

        return new EventDto($id, $event->name, $event->receiver, $event->text, $event->cron);
    }
}
