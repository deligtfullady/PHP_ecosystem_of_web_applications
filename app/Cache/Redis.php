<?php

declare(strict_types=1);

namespace App\Cache;

use Predis\Client;
use Psr\SimpleCache\CacheInterface;

/** Адаптер PSR-16 (Simple Cache) поверх Redis (predis). */
class Redis implements CacheInterface
{
    public function __construct(private Client $client)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($key);

        return $value === null ? $default : $this->unpack($value);
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $seconds = $this->ttlToSeconds($ttl);

        if ($seconds !== null && $seconds <= 0) {
            return $this->delete($key); // по PSR-16 нулевой/отрицательный TTL = удалить
        }

        if ($seconds === null) {
            $this->client->set($key, $this->pack($value));
        } else {
            $this->client->setex($key, $seconds, $this->pack($value));
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $this->client->del([$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->client->flushdb();

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys = $this->toArray($keys);
        if ($keys === []) {
            return [];
        }

        $values = $this->client->mget($keys);
        $result = [];
        foreach ($keys as $i => $key) {
            $result[$key] = $values[$i] === null ? $default : $this->unpack($values[$i]);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keys = $this->toArray($keys);
        if ($keys !== []) {
            $this->client->del($keys);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return (int) $this->client->exists($key) === 1;
    }

    private function ttlToSeconds(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl instanceof \DateInterval) {
            $now = new \DateTimeImmutable();

            return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        return $ttl;
    }

    private function pack(mixed $value): string
    {
        return serialize($value);
    }

    private function unpack(string $value): mixed
    {
        return unserialize($value, ['allowed_classes' => false]);
    }

    /**
     * @param iterable<string> $keys
     * @return string[]
     */
    private function toArray(iterable $keys): array
    {
        return array_values(array_map('strval', is_array($keys) ? $keys : iterator_to_array($keys, false)));
    }
}
