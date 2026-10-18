<?php

declare(strict_types=1);

namespace App\Storage;

class JsonFileStore
{
    public function __construct(private string $path)
    {
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): void
    {
        $tmp = $this->path . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX);
        rename($tmp, $this->path); // атомарная замена: сигнал не оставит наполовину записанный файл
    }
}
