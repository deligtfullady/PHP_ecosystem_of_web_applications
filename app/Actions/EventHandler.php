<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\WrongCronString;
use App\Repositories\EventRepository;

/** Находит события, которые нужно отправить в указанную минуту, и отдаёт их отправителю. */
class EventHandler
{
    public function __construct(
        private EventRepository $repository,
        private Cron $cron,
        private MessageSender $sender,
    ) {
    }

    /** @return int сколько сообщений отправлено */
    public function handle(\DateTimeInterface $at): int
    {
        $sent = 0;

        foreach ($this->repository->all() as $event) {
            try {
                if (!$this->cron->isDue($event->cron, $at)) {
                    continue;
                }
            } catch (WrongCronString) {
                continue; // расписание валидируется при сохранении; испорченную запись пропускаем
            }

            $this->sender->sendMessage($event->receiver, $event->text);
            $sent++;
        }

        return $sent;
    }
}
