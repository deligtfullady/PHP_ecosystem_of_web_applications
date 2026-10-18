<?php

declare(strict_types=1);

namespace App\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQ implements Queue
{
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;
    private ?AMQPMessage $lastMessage = null;

    public function __construct(
        private string $queueName,
        string $host = '127.0.0.1',
        int $port = 5672,
        string $user = 'guest',
        string $password = 'guest',
    ) {
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password);
        $this->channel = $this->connection->channel();
        // durable=true: очередь переживёт перезапуск брокера
        $this->channel->queue_declare($this->queueName, false, true, false, false);
    }

    public function sendMessage($message): void
    {
        $this->channel->basic_publish(
            new AMQPMessage((string) $message, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]),
            '',
            $this->queueName
        );
    }

    public function getMessage(): ?string
    {
        $message = $this->channel->basic_get($this->queueName, false); // no_ack=false: подтверждаем вручную
        if ($message === null) {
            return null;
        }

        $this->lastMessage = $message;

        return $message->getBody();
    }

    public function ackLastMessage(): void
    {
        if ($this->lastMessage !== null) {
            $this->channel->basic_ack($this->lastMessage->getDeliveryTag());
            $this->lastMessage = null;
        }
    }

    public function rejectLastMessage(): void
    {
        if ($this->lastMessage !== null) {
            $this->channel->basic_nack($this->lastMessage->getDeliveryTag(), false, true);
            $this->lastMessage = null;
        }
    }

    public function __destruct()
    {
        try {
            $this->channel->close();
            $this->connection->close();
        } catch (\Throwable) {
            // соединение уже закрыто — ничего страшного
        }
    }
}
