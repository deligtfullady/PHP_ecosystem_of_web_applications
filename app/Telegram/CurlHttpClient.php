<?php

declare(strict_types=1);

namespace App\Telegram;

class CurlHttpClient implements HttpClient
{
    public function __construct(private int $timeoutSeconds = 10)
    {
    }

    public function request(string $method, string $url, array $params = []): string
    {
        $method = strtoupper($method);
        if ($method === 'GET' && $params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            // URL (с токеном) в сообщение не включаем
            throw new \RuntimeException('Ошибка HTTP-запроса к Telegram: ' . $error);
        }
        curl_close($ch);

        return (string) $body;
    }
}
