<?php

declare(strict_types=1);

namespace Tests\Demo;

use App\Actions\Cron;
use PHPUnit\Framework\TestCase;

/**
 * Намеренно «сломанные» тесты: по одному на каждый тип результата PHPUnit.
 * Запуск:  php8.2 ./vendor/bin/phpunit --testsuite demo --display-incomplete --display-skipped
 * В обычный прогон (suite "unit") не входят.
 */
class ResultTypesTest extends TestCase
{
    /** . — успех (OK) */
    public function testPassed(): void
    {
        $this->assertTrue((new Cron())->isValid('* * * * *'));
    }

    /** F — Failure: проверка (assert) не выполнилась */
    public function testFailure(): void
    {
        $this->assertTrue((new Cron())->isValid('60 * * * *'), 'минута 60 невалидна — тест «сломан» намеренно');
    }

    /** E — Error: неожиданное исключение/ошибка в самом коде или тесте */
    public function testError(): void
    {
        (new Cron())->isDue('это не cron', new \DateTimeImmutable()); // бросит WrongCronString, которого тест не ждёт
        $this->assertTrue(true);
    }

    /** R — Risky: тест ничего не проверяет (нет ни одного assert) */
    public function testRisky(): void
    {
        (new Cron())->isValid('* * * * *');
    }

    /** S — Skipped: тест пропущен */
    public function testSkipped(): void
    {
        $this->markTestSkipped('Пропущен намеренно: нет Redis на этом стенде');
    }

    /** I — Incomplete: тест ещё не дописан */
    public function testIncomplete(): void
    {
        $this->markTestIncomplete('TODO: проверить cron с названиями месяцев');
    }
}
