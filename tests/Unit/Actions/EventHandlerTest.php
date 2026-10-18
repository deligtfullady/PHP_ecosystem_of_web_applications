<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Cron;
use App\Actions\EventHandler;
use App\Models\EventDto;
use App\Repositories\EventRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\SpyMessageSender;

#[CoversClass(EventHandler::class)]
class EventHandlerTest extends TestCase
{
    private function handlerWith(array $events, SpyMessageSender $sender): EventHandler
    {
        $repository = $this->createStub(EventRepository::class); // Stub: только отдаёт данные
        $repository->method('all')->willReturn($events);

        return new EventHandler($repository, new Cron(), $sender);
    }

    public function testHandleSendsOnlyEventsDueAtGivenMinute(): void
    {
        $sender = new SpyMessageSender();
        $handler = $this->handlerWith([
            new EventDto(1, 'Каждую минуту', '111', 'всегда', '* * * * *'),
            new EventDto(2, 'В 10:30', '222', 'в срок', '30 10 * * *'),
            new EventDto(3, 'В 11:00', '333', 'не сейчас', '0 11 * * *'),
        ], $sender);

        $sent = $handler->handle(new \DateTimeImmutable('2024-03-15 10:30:00'));

        $this->assertSame(2, $sent);
        $this->assertSame([['111', 'всегда'], ['222', 'в срок']], $sender->sent);
    }

    public function testHandleWithCorruptedCronSkipsEventAndContinues(): void
    {
        $sender = new SpyMessageSender();
        $handler = $this->handlerWith([
            new EventDto(1, 'Сломанное', '111', 'не уйдёт', 'bad cron'),
            new EventDto(2, 'Нормальное', '222', 'уйдёт', '* * * * *'),
        ], $sender);

        $sent = $handler->handle(new \DateTimeImmutable('2024-03-15 10:30:00'));

        $this->assertSame(1, $sent);
        $this->assertSame([['222', 'уйдёт']], $sender->sent);
    }

    public function testHandleWithNoEventsSendsNothing(): void
    {
        $sender = new SpyMessageSender();

        $sent = $this->handlerWith([], $sender)->handle(new \DateTimeImmutable('2024-03-15 10:30:00'));

        $this->assertSame(0, $sent);
        $this->assertSame([], $sender->sent);
    }
}
