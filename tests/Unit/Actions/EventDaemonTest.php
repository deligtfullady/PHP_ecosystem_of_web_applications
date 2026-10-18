<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\EventDaemon;
use App\Actions\EventHandler;
use App\Storage\JsonFileStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventDaemon::class)]
class EventDaemonTest extends TestCase
{
    private string $stateFile;
    private JsonFileStore $store;

    protected function setUp(): void
    {
        $this->stateFile = tempnam(sys_get_temp_dir(), 'daemon_state_');
        unlink($this->stateFile); // начинаем без файла состояния
        $this->store = new JsonFileStore($this->stateFile);
    }

    protected function tearDown(): void
    {
        foreach ([$this->stateFile, $this->stateFile . '.tmp'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function daemon(array &$minutes, int $maxCatchUp = 60, ?callable $afterHandle = null): EventDaemon
    {
        $handler = $this->createMock(EventHandler::class);
        $daemon = null;
        $handler->method('handle')->willReturnCallback(
            function (\DateTimeInterface $at) use (&$minutes, &$daemon, $afterHandle): int {
                $minutes[] = intdiv($at->getTimestamp(), 60);
                if ($afterHandle !== null) {
                    $afterHandle($daemon);
                }

                return 0;
            }
        );

        $daemon = new EventDaemon($handler, $this->store, $maxCatchUp);

        return $daemon;
    }

    public function testTickOnFirstRunProcessesOnlyCurrentMinute(): void
    {
        $minutes = [];
        $daemon = $this->daemon($minutes);

        $processed = $daemon->tick(6000 * 60 + 15);

        $this->assertSame(1, $processed);
        $this->assertSame([6000], $minutes);
    }

    public function testTickTwiceInSameMinuteProcessesMinuteOnce(): void
    {
        $minutes = [];
        $daemon = $this->daemon($minutes);

        $daemon->tick(6000 * 60 + 1);
        $second = $daemon->tick(6000 * 60 + 40);

        $this->assertSame(0, $second);
        $this->assertSame([6000], $minutes);
    }

    public function testTickAfterGapProcessesAllMissedMinutes(): void
    {
        $minutes = [];
        $daemon = $this->daemon($minutes);
        $daemon->tick(6000 * 60);

        $processed = $daemon->tick(6003 * 60 + 5);

        $this->assertSame(3, $processed);
        $this->assertSame([6000, 6001, 6002, 6003], $minutes);
    }

    public function testTickWithUnfinishedMinuteInStateRepeatsThatMinute(): void
    {
        $this->store->save(['last_minute' => 5998, 'in_progress' => 5999]);
        $minutes = [];
        $daemon = $this->daemon($minutes);

        $daemon->tick(6000 * 60);

        $this->assertSame([5999, 6000], $minutes);
    }

    public function testTickLimitsCatchUpToConfiguredWindow(): void
    {
        $this->store->save(['last_minute' => 1000]);
        $minutes = [];
        $daemon = $this->daemon($minutes, 2);

        $daemon->tick(6000 * 60);

        $this->assertSame([5998, 5999, 6000], $minutes);
    }

    public function testTickAfterStopRequestedDuringMinuteFinishesThatMinuteAndStops(): void
    {
        $this->store->save(['last_minute' => 5996]);
        $minutes = [];
        $daemon = $this->daemon($minutes, 60, static function (EventDaemon $d): void {
            $d->requestStop(); // «пришёл SIGTERM» во время обработки
        });

        $processed = $daemon->tick(6000 * 60);

        $this->assertSame(1, $processed);
        $this->assertSame([5997], $minutes);
        $this->assertTrue($daemon->isStopRequested());
        $this->assertSame(5997, $this->store->load()['last_minute']);
        $this->assertArrayNotHasKey('in_progress', $this->store->load());
    }

    public function testTickAfterRestartContinuesFromSavedPosition(): void
    {
        $firstRunMinutes = [];
        $first = $this->daemon($firstRunMinutes, 60, static function (EventDaemon $d): void {
            $d->requestStop();
        });
        $this->store->save(['last_minute' => 5996]);
        $first->tick(6000 * 60);

        $secondRunMinutes = [];
        $second = $this->daemon($secondRunMinutes);
        $second->tick(6000 * 60);

        $this->assertSame([5997], $firstRunMinutes);
        $this->assertSame([5998, 5999, 6000], $secondRunMinutes);
    }

    public function testMarkStoppedSavesTimeParts(): void
    {
        date_default_timezone_set('UTC');
        $minutes = [];
        $daemon = $this->daemon($minutes);
        $at = (new \DateTimeImmutable('2024-03-15 10:30:45'))->getTimestamp(); // пятница

        $daemon->markStopped($at);

        $this->assertSame(
            ['timestamp' => $at, 'minute' => 30, 'hour' => 10, 'day' => 15, 'month' => 3, 'weekday' => 5],
            $this->store->load()['stopped_at']
        );
    }
}
