<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Storage\JsonFileStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonFileStore::class)]
class JsonFileStoreTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/json_store_' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testLoadWithMissingFileReturnsEmptyArray(): void
    {
        $this->assertSame([], (new JsonFileStore($this->file))->load());
    }

    public function testSaveThenLoadReturnsSameData(): void
    {
        $store = new JsonFileStore($this->file);

        $store->save(['last_minute' => 123, 'name' => 'Привет']);

        $this->assertSame(['last_minute' => 123, 'name' => 'Привет'], $store->load());
    }

    public function testLoadWithCorruptedFileReturnsEmptyArray(): void
    {
        file_put_contents($this->file, '{не json');

        $this->assertSame([], (new JsonFileStore($this->file))->load());
    }
}
