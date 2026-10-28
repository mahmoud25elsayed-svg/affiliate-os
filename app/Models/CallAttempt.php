<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\CallCenter\Enums\CallOutcome;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $order_id
 * @property int $user_id
 * @property int $attempt_number
 * @property string $outcome
 * @property string|null $notes
 * @property Carbon|null $scheduled_callback_at
 * @property int|null $duration_seconds
 * @property string|null $recording_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read User $agent
 */
class CallAttempt extends Model
{
    use BelongsToTenant;

    protected $table = 'call_attempts';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'user_id',
        'attempt_number',
        'outcome',
        'notes',
        'scheduled_callback_at',
        'duration_seconds',
        'recording_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'duration_seconds' => 'integer',
            'scheduled_callback_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getOutcomeEnum(): ?CallOutcome
    {
        return CallOutcome::tryFrom($this->outcome);
    }
}
