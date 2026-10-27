<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('test_auditable_models', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });
});

class TestAuditableModel extends Model
{
    use Auditable;

    protected $table = 'test_auditable_models';

    protected $fillable = ['name', 'email'];
}

test('auditable models automatically record created, updated, and deleted logs', function () {
    // 1. Created
    $model = TestAuditableModel::create([
        'name' => 'Original Name',
        'email' => 'test@example.com',
    ]);

    expect(AuditLog::count())->toBe(1);
    $createLog = AuditLog::first();
    expect($createLog->action)->toBe('created')
        ->and($createLog->subject_type)->toBe(TestAuditableModel::class)
        ->and($createLog->subject_id)->toBe((string) $model->id)
        ->and($createLog->after['name'])->toBe('Original Name');

    // 2. Updated
    $model->update(['name' => 'Updated Name']);

    expect(AuditLog::count())->toBe(2);
    $updateLog = AuditLog::latest('id')->first();
    expect($updateLog->action)->toBe('updated')
        ->and($updateLog->before['name'])->toBe('Original Name')
        ->and($updateLog->after['name'])->toBe('Updated Name');

    // 3. Deleted
    $model->delete();

    expect(AuditLog::count())->toBe(3);
    $deleteLog = AuditLog::latest('id')->first();
    expect($deleteLog->action)->toBe('deleted');
});

test('AuditLogger records custom action with actor and tenant context', function () {
    $user = User::factory()->create();

    $log = AuditLogger::log(
        action: 'password_reset_requested',
        subject: $user,
        before: null,
        after: ['reason' => 'user_request'],
        actor: $user,
        tenantId: 'tenant_alpha'
    );

    expect($log->action)->toBe('password_reset_requested')
        ->and($log->actor_id)->toBe((string) $user->id)
        ->and($log->actor_type)->toBe(User::class)
        ->and($log->tenant_id)->toBe('tenant_alpha')
        ->and($log->created_at)->not->toBeNull();
});
