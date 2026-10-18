<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dictionaries\TgEventAnswers;
use App\Exceptions\WrongCronString;
use App\Telegram\TelegramApi;
use Psr\SimpleCache\CacheInterface;

/**
 * Диалог в Telegram для создания напоминания:
 *   /start → название → ID пользователя → текст → cron-расписание → событие сохранено.
 * offset getUpdates, история сообщений и состояние диалогов лежат в кэше (Redis).
 */
class TgEvents
{
    public const OFFSET_KEY = 'tg_messages:offset';
    public const HISTORY_KEY = 'tg_messages:old_messages';
    public const DIALOGS_KEY = 'tg_messages:dialogs';
    private const HISTORY_LIMIT = 100;

    public function __construct(
        private TelegramApi $telegramApi,
        private EventSaver $eventSaver,
        private MessageSender $sender,
        private CacheInterface $cache,
        private Cron $cron,
    ) {
    }

    /**
     * Забирает новые сообщения, продвигает диалоги и отвечает пользователям.
     *
     * @return array<int|string, string[]> новые сообщения по chat_id (для вывода в консоль)
     */
    public function handle(): array
    {
        $offset = (int) $this->cache->get(self::OFFSET_KEY, 0);
        $received = $this->telegramApi->getMessages($offset);

        $history = $this->cache->get(self::HISTORY_KEY, []);
        $dialogs = $this->cache->get(self::DIALOGS_KEY, []);

        foreach ($received['result'] as $chatId => $messages) {
            $history[$chatId] = array_slice(
                array_merge($history[$chatId] ?? [], $messages),
                -self::HISTORY_LIMIT
            );

            $answer = $this->processMessages((string) $chatId, $messages, $dialogs);

            $this->sender->sendMessage((string) $chatId, $answer);
        }

        // Сохраняем в конце: если что-то упало — Telegram отдаст сообщения повторно (at-least-once)
        $this->cache->set(self::HISTORY_KEY, $history);
        $this->cache->set(self::DIALOGS_KEY, $dialogs);
        $this->cache->set(self::OFFSET_KEY, $received['offset']);

        return $received['result'];
    }

    /**
     * @param string[] $messages
     * @param array<int|string, string[]> $dialogs принятые ответы по chat_id (ключа нет — диалог не начат)
     */
    private function processMessages(string $chatId, array $messages, array &$dialogs): string
    {
        $answer = TgEventAnswers::HINT;

        foreach ($messages as $message) {
            $text = trim($message);

            if (preg_match('#^/start(@\w+)?$#i', $text) === 1) { // допускаем /start@имя_бота из групп
                $dialogs[$chatId] = [];
                $answer = TgEventAnswers::ASK_NAME;
                continue;
            }

            if (!isset($dialogs[$chatId])) {
                $answer = TgEventAnswers::HINT;
                continue;
            }

            $step = count($dialogs[$chatId]);
            $error = $this->validate($step, $text);
            if ($error !== null) {
                $answer = $error . "\n" . TgEventAnswers::QUESTIONS[$step];
                continue;
            }

            $dialogs[$chatId][] = $text;

            if (count($dialogs[$chatId]) === count(TgEventAnswers::QUESTIONS)) {
                [$name, $receiver, $eventText, $cron] = $dialogs[$chatId];
                $this->eventSaver->save($name, $receiver, $eventText, $cron);
                unset($dialogs[$chatId]);
                $answer = TgEventAnswers::SAVED;
                continue;
            }

            $answer = TgEventAnswers::QUESTIONS[count($dialogs[$chatId])];
        }

        return $answer;
    }

    private function validate(int $step, string $text): ?string
    {
        return match ($step) {
            0 => ($text === '' || mb_strlen($text) > 100) ? TgEventAnswers::WRONG_NAME : null,
            1 => preg_match('/^-?\d+$/', $text) === 1 ? null : TgEventAnswers::WRONG_RECEIVER,
            2 => ($text === '' || mb_strlen($text) > 4000) ? TgEventAnswers::WRONG_TEXT : null,
            3 => $this->validateCron($text),
            default => null,
        };
    }

    private function validateCron(string $text): ?string
    {
        try {
            $this->cron->validate($text);
        } catch (WrongCronString $e) {
            return TgEventAnswers::WRONG_CRON . ' ' . $e->getMessage();
        }

        return null;
    }
}
