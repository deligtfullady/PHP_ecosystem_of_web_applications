<?php

declare(strict_types=1);

namespace Tests\Providers;

use App\Dictionaries\TgEventAnswers;

final class TgDialogProvider
{
    /**
     * [сообщения пользователя за один опрос, ожидаемый ответ бота]
     * Каждый случай начинается «с чистого листа» (диалог не начат).
     *
     * @return array<string, array{0: string[], 1: string}>
     */
    public static function singleBatchAnswers(): array
    {
        return [
            '/start — спросить название' => [['/start'], TgEventAnswers::ASK_NAME],
            '/start с именем бота' => [['/start@my_bot'], TgEventAnswers::ASK_NAME],
            'регистр /START неважен' => [['/START'], TgEventAnswers::ASK_NAME],
            'название → спросить ID' => [['/start', 'Встреча'], TgEventAnswers::ASK_RECEIVER],
            'ID → спросить текст' => [['/start', 'Встреча', '123'], TgEventAnswers::ASK_TEXT],
            'текст → спросить cron' => [['/start', 'Встреча', '123', 'Созвон'], TgEventAnswers::ASK_CRON],
            'без /start — подсказка' => [['привет'], TgEventAnswers::HINT],
            'повторный /start сбрасывает диалог' => [['/start', 'Встреча', '/start'], TgEventAnswers::ASK_NAME],
        ];
    }
}
