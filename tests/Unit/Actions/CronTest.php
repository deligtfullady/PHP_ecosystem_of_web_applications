<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Cron;
use App\Exceptions\WrongCronString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tests\Providers\CronProvider;

#[CoversClass(Cron::class)]
class CronTest extends TestCase
{
    #[DataProviderExternal(CronProvider::class, 'dueCases')]
    public function testIsDueWithExpressionReturnsExpectedResult(string $expression, string $at, bool $expected): void
    {
        // Arrange
        $cron = new Cron();

        // Act
        $actual = $cron->isDue($expression, new \DateTimeImmutable($at));

        // Assert
        $this->assertSame($expected, $actual);
    }

    #[DataProviderExternal(CronProvider::class, 'invalidExpressions')]
    public function testValidateWithInvalidExpressionThrowsWrongCronString(string $expression): void
    {
        $cron = new Cron();

        $this->expectException(WrongCronString::class);

        $cron->validate($expression);
    }

    #[DataProviderExternal(CronProvider::class, 'invalidExpressions')]
    public function testIsValidWithInvalidExpressionReturnsFalse(string $expression): void
    {
        $this->assertFalse((new Cron())->isValid($expression));
    }

    #[TestWith(['* * * * *'])]
    #[TestWith(['0 9 * * 1-5'])]
    #[TestWith(['*/5 * * * *'])]
    #[TestWith(['0,30 8-18 * * *'])]
    #[TestWith(['59 23 31 12 7'])]
    #[TestWith(['  0   12  *  *  *  '])]
    public function testIsValidWithCorrectExpressionReturnsTrue(string $expression): void
    {
        $this->assertTrue((new Cron())->isValid($expression));
    }

    public function testIsDueWithInvalidExpressionThrowsWrongCronString(): void
    {
        $this->expectException(WrongCronString::class);
        $this->expectExceptionMessageMatches('/5 полей/u');

        (new Cron())->isDue('* * *', new \DateTimeImmutable('2024-03-15 10:30:00'));
    }
}
