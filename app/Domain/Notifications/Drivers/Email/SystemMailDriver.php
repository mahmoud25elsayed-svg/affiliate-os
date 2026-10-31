<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Drivers\Email;

use App\Domain\Notifications\Contracts\NotificationDriverInterface;
use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\DTOs\NotificationResult;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SystemMailDriver implements NotificationDriverInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function send(NotificationMessage $message, array $config = []): NotificationResult
    {
        $subject = $message->subject ?: __('إشعار من المتجر');
        $fromAddress = (string) ($config['from_address'] ?? config('mail.from.address', 'noreply@affiliate-os.com'));
        $fromName = (string) ($config['from_name'] ?? $message->senderId ?? config('mail.from.name', 'AffiliateOS'));

        try {
            Mail::raw($message->content, function (Message $mail) use ($message, $subject, $fromAddress, $fromName): void {
                $mail->to($message->recipient)
                    ->subject($subject)
                    ->from($fromAddress, $fromName);
            });

            return NotificationResult::success(
                externalId: uniqid('mail_'),
                costMinor: 1, // 0.01 EGP per email
                rawResponse: ['status' => 'sent', 'to' => $message->recipient]
            );
        } catch (Throwable $e) {
            return NotificationResult::failure(
                'Email sending exception: '.$e->getMessage()
            );
        }
    }

    public function getProviderName(): string
    {
        return 'system_mail';
    }
}
