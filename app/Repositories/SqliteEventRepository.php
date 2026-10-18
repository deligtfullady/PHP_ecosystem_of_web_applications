<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\EventDto;

class SqliteEventRepository implements EventRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /** Создаёт таблицу, если её ещё нет. Вызывается фабрикой (Application), а не конструктором. */
    public function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                receiver TEXT NOT NULL,
                text TEXT NOT NULL,
                cron TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    public function save(EventDto $event): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO events (name, receiver, text, cron) VALUES (:name, :receiver, :text, :cron)'
        );
        $stmt->execute([
            'name' => $event->name,
            'receiver' => $event->receiver,
            'text' => $event->text,
            'cron' => $event->cron,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function all(): array
    {
        $rows = $this->pdo
            ->query('SELECT id, name, receiver, text, cron FROM events ORDER BY id')
            ->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $r): EventDto => new EventDto(
                (int) $r['id'],
                (string) $r['name'],
                (string) $r['receiver'],
                (string) $r['text'],
                (string) $r['cron'],
            ),
            $rows
        );
    }
}
