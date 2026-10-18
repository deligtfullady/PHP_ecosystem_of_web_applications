<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Exceptions\TelegramApiException;
use App\Telegram\HttpClient;
use App\Telegram\TelegramApiImpl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramApiImpl::class)]
class TelegramApiImplTest extends TestCase
{
    public function testGetMessagesGroupsTextsByChatAndReturnsNextOffset(): void
    {
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())->method('request')
            ->with('GET', 'https://api.telegram.org/botTOKEN/getUpdates', ['timeout' => 1, 'offset' => 10])
            ->willReturn(json_encode(['ok' => true, 'result' => [
                ['update_id' => 10, 'message' => ['chat' => ['id' => 111], 'text' => 'a']],
                ['update_id' => 11, 'message' => ['chat' => ['id' => 222], 'text' => 'b']],
                ['update_id' => 12, 'message' => ['chat' => ['id' => 111], 'text' => 'c']],
            ]]));

        $result = (new TelegramApiImpl('TOKEN', $http))->getMessages(10);

        $this->assertSame(13, $result['offset']);
        $this->assertSame([111 => ['a', 'c'], 222 => ['b']], $result['result']);
    }

    public function testGetMessagesWithStickerSkipsMessageButAdvancesOffset(): void
    {
        $http = $this->createStub(HttpClient::class);
        $http->method('request')->willReturn(json_encode(['ok' => true, 'result' => [
            ['update_id' => 5, 'message' => ['chat' => ['id' => 111], 'sticker' => ['file_id' => 'x']]],
        ]]));

        $result = (new TelegramApiImpl('TOKEN', $http))->getMessages(0);

        $this->assertSame(6, $result['offset']);
        $this->assertSame([], $result['result']);
    }

    public function testGetMessagesWithNoUpdatesKeepsOffset(): void
    {
        $http = $this->createStub(HttpClient::class);
        $http->method('request')->willReturn('{"ok":true,"result":[]}');

        $result = (new TelegramApiImpl('TOKEN', $http))->getMessages(77);

        $this->assertSame(77, $result['offset']);
    }

    public function testSendMessagePostsChatIdAndText(): void
    {
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())->method('request')
            ->with('POST', 'https://api.telegram.org/botTOKEN/sendMessage', ['chat_id' => '123', 'text' => 'Привет'])
            ->willReturn('{"ok":true,"result":{}}');

        (new TelegramApiImpl('TOKEN', $http))->sendMessage('123', 'Привет');
    }

    public function testSendMessageWhenTelegramRepliesWithErrorThrowsWithoutLeakingToken(): void
    {
        $http = $this->createStub(HttpClient::class);
        $http->method('request')->willReturn('{"ok":false,"description":"Forbidden: bot was blocked by the user"}');

        try {
            (new TelegramApiImpl('SECRET-TOKEN', $http))->sendMessage('123', 'Привет');
            $this->fail('Ожидалось исключение TelegramApiException');
        } catch (TelegramApiException $e) {
            $this->assertStringContainsString('bot was blocked', $e->getMessage());
            $this->assertStringNotContainsString('SECRET-TOKEN', $e->getMessage());
        }
    }

    public function testGetMessagesWithInvalidJsonThrowsTelegramApiException(): void
    {
        $http = $this->createStub(HttpClient::class);
        $http->method('request')->willReturn('<html>502 Bad Gateway</html>');

        $this->expectException(TelegramApiException::class);

        (new TelegramApiImpl('TOKEN', $http))->getMessages(0);
    }
}
