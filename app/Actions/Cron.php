<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\WrongCronString;

/**
 * Разбор и проверка cron-выражений из 5 полей: минута час день месяц день_недели.
 * Поддерживается: *, число, список (1,2), диапазон (1-5), шаг (звёздочка/5, 10-50/20).
 * День недели: 0..7 (0 и 7 — воскресенье). Названия (MON, JAN) не поддерживаются.
 * Если ограничены и день месяца, и день недели — достаточно совпадения любого (как в cron).
 */
class Cron
{
    private const RANGES = [
        'минута' => [0, 59],
        'час' => [0, 23],
        'день месяца' => [1, 31],
        'месяц' => [1, 12],
        'день недели' => [0, 7],
    ];

    public function isValid(string $expression): bool
    {
        try {
            $this->parse($expression);
        } catch (WrongCronString) {
            return false;
        }

        return true;
    }

    /** @throws WrongCronString */
    public function validate(string $expression): void
    {
        $this->parse($expression);
    }

    /** @throws WrongCronString */
    public function isDue(string $expression, \DateTimeInterface $at): bool
    {
        [$minutes, $hours, $days, $months, $weekdays, $anyDay, $anyWeekday] = $this->parse($expression);

        $dayOfMonthMatches = in_array((int) $at->format('j'), $days, true);
        $dayOfWeekMatches = in_array((int) $at->format('w'), $weekdays, true);

        $dayMatches = ($anyDay || $anyWeekday)
            ? ($dayOfMonthMatches && $dayOfWeekMatches)
            : ($dayOfMonthMatches || $dayOfWeekMatches);

        return in_array((int) $at->format('i'), $minutes, true)
            && in_array((int) $at->format('G'), $hours, true)
            && in_array((int) $at->format('n'), $months, true)
            && $dayMatches;
    }

    /**
     * @return array{0: int[], 1: int[], 2: int[], 3: int[], 4: int[], 5: bool, 6: bool}
     * @throws WrongCronString
     */
    private function parse(string $expression): array
    {
        $fields = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY);

        if ($fields === false || count($fields) !== 5) {
            throw new WrongCronString('Cron-расписание должно состоять из 5 полей: минута час день месяц день_недели');
        }

        $parsed = [];
        $i = 0;
        foreach (self::RANGES as $name => [$min, $max]) {
            $parsed[] = $this->parseField($fields[$i], $min, $max, $name, $name === 'день недели');
            $i++;
        }

        return [
            $parsed[0],
            $parsed[1],
            $parsed[2],
            $parsed[3],
            $parsed[4],
            str_starts_with($fields[2], '*'),
            str_starts_with($fields[4], '*'),
        ];
    }

    /**
     * @return int[]
     * @throws WrongCronString
     */
    private function parseField(string $field, int $min, int $max, string $name, bool $isWeekday): array
    {
        $values = [];

        foreach (explode(',', $field) as $part) {
            if (!preg_match('/^(\*|\d+(?:-\d+)?)(?:\/(\d+))?$/', $part, $m)) {
                throw new WrongCronString("Некорректное значение поля «{$name}»: {$part}");
            }

            $range = $m[1];
            $hasStep = isset($m[2]);
            $step = $hasStep ? (int) $m[2] : 1;

            if ($hasStep && ($step < 1 || ($range !== '*' && !str_contains($range, '-')))) {
                throw new WrongCronString("Некорректный шаг в поле «{$name}»: {$part}");
            }

            if ($range === '*') {
                [$from, $to] = [$min, $max];
            } elseif (str_contains($range, '-')) {
                [$from, $to] = array_map('intval', explode('-', $range));
            } else {
                $from = $to = (int) $range;
            }

            if ($from > $to || $from < $min || $to > $max) {
                throw new WrongCronString("Значение вне диапазона {$min}-{$max} в поле «{$name}»: {$part}");
            }

            for ($v = $from; $v <= $to; $v += $step) {
                $values[$isWeekday && $v === 7 ? 0 : $v] = true;
            }
        }

        $result = array_keys($values);
        sort($result);

        return $result;
    }
}
