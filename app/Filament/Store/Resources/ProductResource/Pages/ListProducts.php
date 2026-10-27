<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\Pages;

use App\Filament\Store\Resources\ProductResource;
use App\Jobs\Catalog\ExportProductsJob;
use App\Jobs\Catalog\ImportProductsJob;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة منتج جديد'),

            Action::make('import_csv')
                ->label('استيراد CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->schema([
                    FileUpload::make('csv_file')
                        ->label('ملف المنتجات (CSV)')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->required()
                        ->disk('local')
                        ->directory('imports'),
                ])
                ->action(function (array $data) {
                    /** @var Tenant|null $tenant */
                    $tenant = null;
                    if (function_exists('tenancy') && tenancy()->initialized) {
                        /** @var Tenant|null $tenant */
                        $tenant = tenancy()->tenant;
                    } elseif (function_exists('tenant') && tenant('id')) {
                        /** @var Tenant|null $tenant */
                        $tenant = Tenant::find(tenant('id'));
                    }

                    if (! $tenant) {
                        Notification::make()->title('لم يتم العثور على المتجر')->danger()->send();

                        return;
                    }

                    $filePath = Storage::disk('local')->path((string) $data['csv_file']);
                    $job = new ImportProductsJob($filePath, $tenant);
                    $report = app()->call([$job, 'handle']);

                    $title = sprintf('تم استيراد %d منتج بنجاح (فشل %d)', $report['imported'], $report['failed']);

                    $notification = Notification::make()->title($title);
                    if ($report['failed'] > 0) {
                        $notification->warning()->body(implode("\n", array_slice($report['errors'], 0, 5)));
                    } else {
                        $notification->success();
                    }
                    $notification->send();
                }),

            Action::make('export_csv')
                ->label('تصدير CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    /** @var Tenant|null $tenant */
                    $tenant = null;
                    if (function_exists('tenancy') && tenancy()->initialized) {
                        /** @var Tenant|null $tenant */
                        $tenant = tenancy()->tenant;
                    } elseif (function_exists('tenant') && tenant('id')) {
                        /** @var Tenant|null $tenant */
                        $tenant = Tenant::find(tenant('id'));
                    }

                    $job = new ExportProductsJob($tenant ?? Tenant::firstOrFail());
                    $csv = $job->handle();

                    return response()->streamDownload(function () use ($csv) {
                        echo $csv;
                    }, 'products-export-'.now()->format('Y-m-d').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
        ];
    }
}
