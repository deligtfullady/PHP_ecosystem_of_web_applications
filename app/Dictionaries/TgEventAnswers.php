<?php

declare(strict_types=1);

namespace App\Dictionaries;

final class TgEventAnswers
{
    public const HINT = 'Чтобы создать напоминание, введите /start';
    public const ASK_NAME = 'Укажите название события';
    public const ASK_RECEIVER = 'Укажите ID пользователя';
    public const ASK_TEXT = 'Укажите текст напоминания';
    public const ASK_CRON = 'Укажите cron-расписание, например: * * * * *';
    public const SAVED = 'Я записал Ваше событие. Для нового события введите /start';
    public const WRONG_RECEIVER = 'ID пользователя должен быть целым числом.';
    public const WRONG_TEXT = 'Текст не может быть пустым или длиннее 4000 символов.';
    public const WRONG_NAME = 'Название не может быть пустым или длиннее 100 символов.';
    public const WRONG_CRON = 'Неверное cron-расписание.';

    /** Вопросы по шагам диалога (индекс = сколько ответов уже принято). */
    public const QUESTIONS = [
        self::ASK_NAME,
        self::ASK_RECEIVER,
        self::ASK_TEXT,
        self::ASK_CRON,
    ];
}
