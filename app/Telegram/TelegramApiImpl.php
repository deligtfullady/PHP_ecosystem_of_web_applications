<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Exceptions\TelegramApiException;

class TelegramApiImpl implements TelegramApi
{
    private const BASE_URL = 'https://api.telegram.org/bot';

    private HttpClient $http;

    public function __construct(private string $token, ?HttpClient $http = null)
    {
        $this->http = $http ?? new CurlHttpClient();
    }

    public function getMessages(int $offset): array
    {
        $data = $this->call('GET', 'getUpdates', ['timeout' => 1, 'offset' => $offset]);

        $next = $offset;
        $result = [];

        foreach ($data['result'] ?? [] as $update) {
            $next = max($next, ((int) ($update['update_id'] ?? 0)) + 1);

            $chatId = $update['message']['chat']['id'] ?? null;
            $text = $update['message']['text'] ?? null;
            if ($chatId === null || !is_string($text)) {
                continue; // стикеры, фото и т.п. пропускаем, но offset сдвигаем
            }

            $result[$chatId][] = $text;
        }

        return ['offset' => $next, 'result' => $result];
    }

    public function sendMessage(string $receiver, string $text): void
    {
        $this->call('POST', 'sendMessage', ['chat_id' => $receiver, 'text' => $text]);
    }

    /**
     * @param array<string, scalar> $params
     * @return array<string, mixed>
     */
    private function call(string $httpMethod, string $apiMethod, array $params): array
    {
        $body = $this->http->request($httpMethod, self::BASE_URL . $this->token . '/' . $apiMethod, $params);

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new TelegramApiException("Telegram вернул некорректный ответ на {$apiMethod}");
        }

        if (!is_array($data) || ($data['ok'] ?? false) !== true) {
            $description = is_array($data) ? (string) ($data['description'] ?? 'неизвестная ошибка') : 'неизвестная ошибка';
            throw new TelegramApiException("Telegram {$apiMethod}: {$description}");
        }

        return $data;
    }
}
