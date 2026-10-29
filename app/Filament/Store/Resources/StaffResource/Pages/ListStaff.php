<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StaffResource\Pages;

use App\Filament\Store\Resources\StaffResource;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->before(function (): void {
                    if (tenancy()->initialized) {
                        \App\Support\Billing\Limits::ensure('staff', tenant());
                    }
                })
                ->mutateFormDataUsing(function (array $data): array {
                    if (tenancy()->initialized) {
                        $data['tenant_id'] = tenant('id');
                    }

                    return $data;
                })
                ->after(function (User $record, array $data): void {
                    if (! empty($data['role']) && tenancy()->initialized) {
                        TenantPermissionMatrix::assignRole($record, (string) $data['role'], (string) tenant('id'));
                    }
                }),
        ];
    }
}
