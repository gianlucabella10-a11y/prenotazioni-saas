<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Modules\AppFactory\Infrastructure\Models\BetaFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class BetaFeedbackTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    public function test_authenticated_tester_submits_feedback_scoped_to_tenant(): void
    {
        $env = $this->provisionBookableTenant();
        ['user' => $user] = $this->createCustomerUser($env['tenant']);

        $this->postJson('/api/v1/me/feedback', [
            'message' => 'Crash all\'apertura della scheda servizi',
            'app_version' => '1.0.0+3',
            'platform' => 'android',
        ], $this->authHeaders($user, $env['tenant']))->assertCreated();

        $row = $this->bypassTenancy(fn (): BetaFeedback => BetaFeedback::query()->firstOrFail());
        self::assertSame($env['tenant']->id, $row->tenant_id);
        self::assertSame($user->id, $row->user_id);
        self::assertSame('android', $row->platform);
    }

    public function test_feedback_requires_a_message(): void
    {
        $env = $this->provisionBookableTenant();
        ['user' => $user] = $this->createCustomerUser($env['tenant']);

        $this->postJson('/api/v1/me/feedback', ['message' => ''], $this->authHeaders($user, $env['tenant']))
            ->assertStatus(422);
    }

    public function test_feedback_requires_authentication(): void
    {
        $env = $this->provisionBookableTenant();

        $this->postJson('/api/v1/me/feedback', ['message' => 'ciao'], $this->tenantKeyHeaders($env['tenant']))
            ->assertUnauthorized();
    }
}
