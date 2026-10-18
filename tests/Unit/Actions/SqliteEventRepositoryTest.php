<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Models\EventDto;
use App\Repositories\SqliteEventRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqliteEventRepository::class)]
class SqliteEventRepositoryTest extends TestCase
{
    private function repository(): SqliteEventRepository
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $repository = new SqliteEventRepository($pdo);
        $repository->migrate();

        return $repository;
    }

    public function testAllWithEmptyTableReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->repository()->all());
    }

    public function testSaveThenAllReturnsStoredEventsInOrder(): void
    {
        $repository = $this->repository();

        $firstId = $repository->save(new EventDto(null, 'Первое', '111', 'Текст 1', '* * * * *'));
        $secondId = $repository->save(new EventDto(null, 'Второе', '-222', "Текст с 'кавычками'", '0 9 * * 1-5'));

        $events = $repository->all();

        $this->assertSame(1, $firstId);
        $this->assertSame(2, $secondId);
        $this->assertCount(2, $events);
        $this->assertEquals(new EventDto(2, 'Второе', '-222', "Текст с 'кавычками'", '0 9 * * 1-5'), $events[1]);
    }

    public function testMigrateCalledTwiceKeepsExistingData(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $repository = new SqliteEventRepository($pdo);
        $repository->migrate();
        $repository->save(new EventDto(null, 'Имя', '1', 'Текст', '* * * * *'));

        $repository->migrate();

        $this->assertCount(1, $repository->all());
    }
}
