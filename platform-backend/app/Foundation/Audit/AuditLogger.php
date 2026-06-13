<?php

declare(strict_types=1);

namespace App\Foundation\Audit;

use App\Foundation\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Append-only audit trail for security-relevant operations (docs/14 §7,
 * docs/24). Writes are best-effort outside the caller's transaction: an
 * audit failure must never break the business operation, but is reported.
 */
final readonly class AuditLogger
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private Request $request,
    ) {
    }

    /** @param array<string, mixed> $payload Never include personal data. */
    public function log(
        string $action,
        ?int $actorUserId = null,
        array $payload = [],
        ?int $tenantId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): void {
        try {
            DB::table('audit_logs')->insert([
                'tenant_id' => $tenantId ?? ($this->currentTenant->bound() ? $this->currentTenant->id() : null),
                'actor_user_id' => $actorUserId,
                'actor_type' => null,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'ip' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
                'payload' => $payload === [] ? null : json_encode($payload),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
