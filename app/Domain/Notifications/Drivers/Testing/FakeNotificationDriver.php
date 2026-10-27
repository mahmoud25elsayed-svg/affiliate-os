<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Drivers\Testing;

use App\Domain\Notifications\Contracts\NotificationDriverInterface;
use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\DTOs\NotificationResult;
use RuntimeException;

class FakeNotificationDriver implements NotificationDriverInterface
{
    /**
     * @var list<NotificationMessage>
     */
    protected array $sentMessages = [];

    protected bool $shouldFail = false;

    protected string $failureReason = 'Simulated provider error';

    protected int $defaultCostMinor = 25; // 0.25 EGP

    /**
     * @param  array<string, mixed>  $config
     */
    public function send(NotificationMessage $message, array $config = []): NotificationResult
    {
        if ($this->shouldFail) {
            return NotificationResult::failure(
                errorMessage: $this->failureReason,
                rawResponse: ['status' => 'error', 'reason' => $this->failureReason]
            );
        }

        $this->sentMessages[] = $message;

        return NotificationResult::success(
            externalId: 'fake_msg_'.uniqid(),
            costMinor: $this->defaultCostMinor,
            rawResponse: ['status' => 'delivered', 'id' => uniqid()]
        );
    }

    public function getProviderName(): string
    {
        return 'fake';
    }

    public function shouldFail(bool $fail = true, string $reason = 'Simulated provider error'): self
    {
        $this->shouldFail = $fail;
        $this->failureReason = $reason;

        return $this;
    }

    /**
     * @return list<NotificationMessage>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function getSentCount(): int
    {
        return count($this->sentMessages);
    }

    public function reset(): void
    {
        $this->sentMessages = [];
        $this->shouldFail = false;
        $this->failureReason = 'Simulated provider error';
    }

    /**
     * @param  callable(NotificationMessage): bool  $callback
     */
    public function assertSent(callable $callback): void
    {
        $found = false;
        foreach ($this->sentMessages as $message) {
            if ($callback($message)) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new RuntimeException('Failed asserting that a matching notification was sent.');
        }
    }
}
