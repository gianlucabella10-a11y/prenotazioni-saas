<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** TOTP secret (encrypted at rest) or recovery codes for one user. */
class MfaCredential extends Model
{
    public const TYPE_TOTP = 'totp';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
