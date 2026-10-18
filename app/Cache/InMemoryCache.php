<?php

declare(strict_types=1);

namespace App\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 кэш в памяти процесса. Для тестов и запуска без Redis
 * (данные теряются при перезапуске процесса). TTL учитывается.
 */
class InMemoryCache implements CacheInterface
{
    /** @var array<string, array{0: mixed, 1: ?int}> */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->items[$key][0] : $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = (new \DateTimeImmutable())->add($ttl)->getTimestamp() - time();
        }
        if ($ttl !== null && $ttl <= 0) {
            return $this->delete($key);
        }

        $this->items[$key] = [$value, $ttl === null ? null : time() + $ttl];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->items[$key])) {
            return false;
        }
        $expiresAt = $this->items[$key][1];
        if ($expiresAt !== null && $expiresAt <= time()) {
            unset($this->items[$key]);

            return false;
        }

        return true;
    }
}
