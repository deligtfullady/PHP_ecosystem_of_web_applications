<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Cron;
use App\Actions\EventSaver;
use App\Exceptions\WrongCronString;
use App\Models\EventDto;
use App\Repositories\EventRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventSaver::class)]
class EventSaverTest extends TestCase
{
    public function testSaveWithValidDataStoresEventAndReturnsDtoWithId(): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(
                static fn (EventDto $e): bool => $e->name === 'Встреча'
                    && $e->receiver === '123'
                    && $e->text === 'Созвон в 10:00'
                    && $e->cron === '0 10 * * 1-5'
            ))
            ->willReturn(7);

        $saved = (new EventSaver($repository, new Cron()))->save('Встреча', '123', 'Созвон в 10:00', ' 0 10 * * 1-5 ');

        $this->assertSame(7, $saved->id);
        $this->assertSame('0 10 * * 1-5', $saved->cron);
    }

    public function testSaveWithWrongCronThrowsAndDoesNotTouchRepository(): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects($this->never())->method('save');

        $this->expectException(WrongCronString::class);

        (new EventSaver($repository, new Cron()))->save('Встреча', '123', 'Текст', '99 * * * *');
    }

    #[TestWith(['', '123', 'Текст'])]
    #[TestWith(['Имя', '  ', 'Текст'])]
    #[TestWith(['Имя', '123', ''])]
    public function testSaveWithEmptyFieldThrowsInvalidArgumentException(string $name, string $receiver, string $text): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/не может быть пустым/u');

        (new EventSaver($repository, new Cron()))->save($name, $receiver, $text, '* * * * *');
    }
}
