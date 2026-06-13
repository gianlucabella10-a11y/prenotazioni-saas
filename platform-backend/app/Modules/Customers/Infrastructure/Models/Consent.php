<?php

declare(strict_types=1);

namespace App\Modules\Customers\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only GDPR consent record: a new row per decision, never updated.
 * The latest row per kind is the current consent state.
 */
class Consent extends Model
{
    use BelongsToTenant;

    public const KIND_MARKETING_PUSH = 'marketing_push';
    public const KIND_MARKETING_EMAIL = 'marketing_email';
    public const KIND_MARKETING_SMS = 'marketing_sms';
    public const KIND_PRIVACY_POLICY = 'privacy_policy';

    public const UPDATED_AT = null;
    public const CREATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'granted' => 'bool',
            'occurred_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
