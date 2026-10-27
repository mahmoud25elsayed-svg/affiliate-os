<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Location\Governorate;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $customer_id
 * @property string $name
 * @property string $phone
 * @property int $governorate_id
 * @property string $city
 * @property string $address_line
 * @property string|null $postal_code
 * @property string|null $notes
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerAddress extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_addresses';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'governorate_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Governorate, $this>
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    public function setAsDefault(): void
    {
        static::query()
            ->where('customer_id', $this->customer_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
