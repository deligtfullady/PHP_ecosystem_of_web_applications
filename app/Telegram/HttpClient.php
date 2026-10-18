<?php

declare(strict_types=1);

namespace App\Telegram;

interface HttpClient
{
    /**
     * @param array<string, scalar> $params GET — в query-строку, POST — в тело формы
     * @return string тело ответа
     * @throws \RuntimeException при сетевой ошибке
     */
    public function request(string $method, string $url, array $params = []): string;
}
