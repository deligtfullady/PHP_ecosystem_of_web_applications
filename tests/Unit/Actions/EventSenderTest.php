<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\EventSender;
use App\Queue\Queue;
use App\Telegram\TelegramApi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventSender::class)]
class EventSenderTest extends TestCase
{
    public function testSendMessagePutsJsonIntoQueueAndDoesNotCallTelegram(): void
    {
        $telegram = $this->createMock(TelegramApi::class);
        $telegram->expects($this->never())->method('sendMessage');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('sendMessage')
            ->with('{"receiver":"123","text":"Привет"}');

        (new EventSender($telegram, $queue))->sendMessage('123', 'Привет');
    }

    public function testHandleWithMessageSendsToTelegramAndAcknowledges(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getMessage')->willReturn('{"receiver":"123","text":"Привет"}');
        $queue->expects($this->once())->method('ackLastMessage');
        $queue->expects($this->never())->method('rejectLastMessage');

        $telegram = $this->createMock(TelegramApi::class);
        $telegram->expects($this->once())->method('sendMessage')->with('123', 'Привет');

        (new EventSender($telegram, $queue))->handle();
    }

    public function testHandleWithEmptyQueueDoesNothing(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getMessage')->willReturn(null);
        $queue->expects($this->never())->method('ackLastMessage');
        $queue->expects($this->never())->method('rejectLastMessage');

        $telegram = $this->createMock(TelegramApi::class);
        $telegram->expects($this->never())->method('sendMessage');

        (new EventSender($telegram, $queue))->handle();
    }

    public function testHandleWhenTelegramFailsReturnsMessageToQueueAndRethrows(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getMessage')->willReturn('{"receiver":"123","text":"Привет"}');
        $queue->expects($this->once())->method('rejectLastMessage');
        $queue->expects($this->never())->method('ackLastMessage');

        $telegram = $this->createStub(TelegramApi::class);
        $telegram->method('sendMessage')->willThrowException(new \RuntimeException('Telegram недоступен'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Telegram недоступен');

        (new EventSender($telegram, $queue))->handle();
    }

    public function testHandleWithCorruptedPayloadAcknowledgesAndThrows(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getMessage')->willReturn('это не json');
        $queue->expects($this->once())->method('ackLastMessage'); // иначе зациклимся на «ядовитом» сообщении

        $telegram = $this->createMock(TelegramApi::class);
        $telegram->expects($this->never())->method('sendMessage');

        $this->expectException(\UnexpectedValueException::class);

        (new EventSender($telegram, $queue))->handle();
    }
}
