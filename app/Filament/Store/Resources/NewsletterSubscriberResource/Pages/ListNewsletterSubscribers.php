<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\NewsletterSubscriberResource\Pages;

use App\Filament\Store\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label(__('تصدير المشتركين (CSV)'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): StreamedResponse {
                    $headers = [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                        'Content-Disposition' => 'attachment; filename="newsletter_subscribers_'.date('Y_m_d').'.csv"',
                    ];

                    return response()->stream(function () {
                        $handle = fopen('php://output', 'w');
                        if ($handle === false) {
                            return;
                        }

                        // UTF-8 BOM for Arabic support in Excel
                        fwrite($handle, "\xEF\xBB\xBF");
                        fputcsv($handle, [__('البريد الإلكتروني'), __('الاسم'), __('المصدر'), __('تاريخ الاشتراك')]);

                        NewsletterSubscriber::query()
                            ->where('is_active', true)
                            ->chunk(100, function ($subscribers) use ($handle) {
                                foreach ($subscribers as $subscriber) {
                                    fputcsv($handle, [
                                        $subscriber->email,
                                        $subscriber->name ?? '',
                                        $subscriber->source,
                                        $subscriber->subscribed_at->toDateTimeString(),
                                    ]);
                                }
                            });

                        fclose($handle);
                    }, 200, $headers);
                }),
        ];
    }
}
