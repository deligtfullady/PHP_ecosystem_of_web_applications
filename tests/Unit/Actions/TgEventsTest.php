<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Cron;
use App\Actions\EventSaver;
use App\Actions\MessageSender;
use App\Actions\TgEvents;
use App\Cache\InMemoryCache;
use App\Dictionaries\TgEventAnswers;
use App\Models\EventDto;
use App\Telegram\TelegramApi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Tests\Providers\TgDialogProvider;

#[CoversClass(TgEvents::class)]
class TgEventsTest extends TestCase
{
    /**
     * Telegram — ВХОДЯЩАЯ зависимость (отдаёт данные) → Stub.
     * Отправитель и сохранялка событий — ИСХОДЯЩИЕ (проверяем вызовы) → Mock.
     *
     * @param array<int, array{offset: int, result: array<int, string[]>}> $batches ответы getMessages по порядку
     */
    private function telegramReturning(array $batches): TelegramApi
    {
        $telegram = $this->createStub(TelegramApi::class);
        $telegram->method('getMessages')->willReturnOnConsecutiveCalls(...$batches);

        return $telegram;
    }

    private function tgEvents(
        TelegramApi $telegram,
        MessageSender $sender,
        ?EventSaver $saver = null,
        ?CacheInterface $cache = null,
    ): TgEvents {
        return new TgEvents(
            $telegram,
            $saver ?? $this->createStub(EventSaver::class),
            $sender,
            $cache ?? new InMemoryCache(),
            new Cron(),
        );
    }

    /** @param string[] $messages */
    #[DataProviderExternal(TgDialogProvider::class, 'singleBatchAnswers')]
    public function testHandleWithMessagesAnswersAccordingToDialogStep(array $messages, string $expectedAnswer): void
    {
        $telegram = $this->telegramReturning([['offset' => 11, 'result' => [555 => $messages]]]);

        $sender = $this->createMock(MessageSender::class);
        $sender->expects($this->once())->method('sendMessage')->with('555', $expectedAnswer);

        $this->tgEvents($telegram, $sender)->handle();
    }

    public function testHandleWithFullDialogSavesEventAndConfirms(): void
    {
        $telegram = $this->telegramReturning([
            ['offset' => 2, 'result' => [555 => ['/start']]],
            ['offset' => 3, 'result' => [555 => ['Встреча']]],
            ['offset' => 4, 'result' => [555 => ['123']]],
            ['offset' => 5, 'result' => [555 => ['Созвон в 10:00']]],
            ['offset' => 6, 'result' => [555 => ['0 10 * * 1-5']]],
        ]);

        $sent = [];
        $sender = $this->createMock(MessageSender::class);
        $sender->expects($this->exactly(5))->method('sendMessage')
            ->willReturnCallback(static function (string $to, string $text) use (&$sent): void {
                $sent[] = [$to, $text];
            });

        $saver = $this->createMock(EventSaver::class);
        $saver->expects($this->once())->method('save')->with('Встреча', '123', 'Созвон в 10:00', '0 10 * * 1-5')
            ->willReturn(new EventDto(1, 'Встреча', '123', 'Созвон в 10:00', '0 10 * * 1-5')); // EventDto final — double не создать

        $tgEvents = $this->tgEvents($telegram, $sender, $saver);
        for ($i = 0; $i < 5; $i++) {
            $tgEvents->handle();
        }

        $this->assertSame(
            [
                ['555', TgEventAnswers::ASK_NAME],
                ['555', TgEventAnswers::ASK_RECEIVER],
                ['555', TgEventAnswers::ASK_TEXT],
                ['555', TgEventAnswers::ASK_CRON],
                ['555', TgEventAnswers::SAVED],
            ],
            $sent
        );
    }

    public function testHandleWithWholeDialogInOneBatchSavesEventOnce(): void
    {
        $telegram = $this->telegramReturning([[
            'offset' => 6,
            'result' => [555 => ['/start', 'Встреча', '123', 'Созвон', '* * * * *']],
        ]]);

        $sender = $this->createMock(MessageSender::class);
        $sender->expects($this->once())->method('sendMessage')->with('555', TgEventAnswers::SAVED);

        $saver = $this->createMock(EventSaver::class);
        $saver->expects($this->once())->method('save')->with('Встреча', '123', 'Созвон', '* * * * *')
            ->willReturn(new EventDto(1, 'Встреча', '123', 'Созвон', '* * * * *'));

        $this->tgEvents($telegram, $sender, $saver)->handle();
    }

    public function testHandleWithNonNumericReceiverAsksAgainAndDoesNotSave(): void
    {
        $telegram = $this->telegramReturning([['offset' => 4, 'result' => [555 => ['/start', 'Встреча', 'Вася']]]]);

        $sender = $this->createMock(MessageSender::class);
        $sender->expects($this->once())->method('sendMessage')
            ->with('555', TgEventAnswers::WRONG_RECEIVER . "\n" . TgEventAnswers::ASK_RECEIVER);

        $saver = $this->createMock(EventSaver::class);
        $saver->expects($this->never())->method('save');

        $this->tgEvents($telegram, $sender, $saver)->handle();
    }

    public function testHandleWithWrongCronAsksAgainAndDoesNotSave(): void
    {
        $telegram = $this->telegramReturning([[
            'offset' => 6,
            'result' => [555 => ['/start', 'Встреча', '123', 'Созвон', '99 * * * *']],
        ]]);

        $answer = null;
        $sender = $this->createMock(MessageSender::class);
        $sender->expects($this->once())->method('sendMessage')
            ->willReturnCallback(static function (string $to, string $text) use (&$answer): void {
                $answer = $text;
            });

        $saver = $this->createMock(EventSaver::class);
        $saver->expects($this->never())->method('save');

        $this->tgEvents($telegram, $sender, $saver)->handle();

        $this->assertStringStartsWith(TgEventAnswers::WRONG_CRON, (string) $answer);
        $this->assertStringEndsWith(TgEventAnswers::ASK_CRON, (string) $answer);
    }

    public function testHandleKeepsDialogsOfDifferentChatsSeparate(): void
    {
        $telegram = $this->telegramReturning([[
            'offset' => 5,
            'result' => [111 => ['/start', 'Первый'], 222 => ['/start']],
        ]]);

        $sent = [];
        $sender = $this->createMock(MessageSender::class);
        $sender->expects($this->exactly(2))->method('sendMessage')
            ->willReturnCallback(static function (string $to, string $text) use (&$sent): void {
                $sent[$to] = $text;
            });

        $this->tgEvents($telegram, $sender)->handle();

        $this->assertSame(TgEventAnswers::ASK_RECEIVER, $sent['111']);
        $this->assertSame(TgEventAnswers::ASK_NAME, $sent['222']);
    }

    public function testHandleStoresOffsetAndHistoryInCache(): void
    {
        $telegram = $this->telegramReturning([['offset' => 42, 'result' => [555 => ['привет']]]]);
        $cache = new InMemoryCache();

        $this->tgEvents($telegram, $this->createStub(MessageSender::class), null, $cache)->handle();

        $this->assertSame(42, $cache->get(TgEvents::OFFSET_KEY));
        $this->assertSame([555 => ['привет']], $cache->get(TgEvents::HISTORY_KEY));
    }

    public function testHandleRequestsMessagesStartingFromCachedOffset(): void
    {
        $cache = new InMemoryCache();
        $cache->set(TgEvents::OFFSET_KEY, 100);

        $telegram = $this->createMock(TelegramApi::class);
        $telegram->expects($this->once())->method('getMessages')->with(100)
            ->willReturn(['offset' => 100, 'result' => []]);

        $this->tgEvents($telegram, $this->createStub(MessageSender::class), null, $cache)->handle();
    }

    public function testHandleWithNoNewMessagesSendsNothingAndReturnsEmptyArray(): void
    {
        $telegram = $this->telegramReturning([['offset' => 7, 'result' => []]]);

        $sender = $this->createMock(MessageSender::class);
        $sender->expects($this->never())->method('sendMessage');

        $this->assertSame([], $this->tgEvents($telegram, $sender)->handle());
    }
}
