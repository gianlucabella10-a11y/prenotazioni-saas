<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Foundation\Tenancy\TenantRegistry;
use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Domain\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class WhiteLabelConfigTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    public function test_config_returns_theme_features_and_locations(): void
    {
        $env = $this->provisionBookableTenant();

        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->assertOk()
            ->assertJsonPath('tenant_status', 'active')
            ->assertJsonPath('config_version', 1)
            ->assertJsonPath('features.waitlist', true)
            ->assertJsonPath('locations.0.uuid', $env['location']->uuid)
            ->assertJsonStructure(['theme' => ['colors' => ['primary', 'on_primary']]])
            ->assertHeader('ETag');
    }

    public function test_config_exposes_contacts_social_and_opening_hours(): void
    {
        $env = $this->provisionBookableTenant();

        // Keys always present (null-safe), so the client can render or hide.
        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->assertOk()
            ->assertJsonStructure([
                'logo_url',
                'contacts' => ['phone', 'email', 'website'],
                'social' => ['instagram_url', 'facebook_url', 'maps_url'],
                'locations' => [['opening_hours']],
            ]);

        // Brand-level contacts/social surface once configured.
        $this->bypassTenancy(function (): void {
            BrandProfile::query()->firstOrFail()->forceFill([
                'contact_email' => 'info@demo.it',
                'instagram_url' => 'https://instagram.com/demo',
                'whatsapp_number' => '+393331234567',
                'whatsapp_message' => 'Ciao',
            ])->save();
        });

        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->assertOk()
            ->assertJsonPath('contacts.email', 'info@demo.it')
            ->assertJsonPath('social.instagram_url', 'https://instagram.com/demo')
            ->assertJsonPath('social.whatsapp.number', '+393331234567');
    }

    public function test_config_exposes_template_skin(): void
    {
        $env = $this->provisionBookableTenant();

        // Senza App Project → template di default.
        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->assertOk()
            ->assertJsonPath('template', 'default');

        // Con App Project → il template scelto arriva nel config.
        $adminId = $this->bypassTenancy(fn () => User::factory()->superAdmin()->create()->id);
        app(AllocateAppIdentifiers::class)
            ->execute($env['tenant'], 'barber_dark', $adminId);

        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->assertOk()
            ->assertJsonPath('template', 'barber_dark')
            ->assertJsonPath('layout', 'hero_dark')
            ->assertJsonPath('font_style', 'oswald');
    }

    public function test_etag_revalidation_returns_304(): void
    {
        $env = $this->provisionBookableTenant();

        $etag = $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->headers->get('ETag');

        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']) + [
            'If-None-Match' => $etag,
        ])->assertStatus(304);
    }

    public function test_brand_update_bumps_version_and_busts_etag(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->createTenantAdmin($env['tenant']);

        $etagBefore = $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->headers->get('ETag');

        $this->putJson('/api/v1/manage/brand', [
            'app_name' => 'Barber Deluxe',
            'primary_color' => '#0F172A',
        ], $this->authHeaders($admin, $env['tenant']))
            ->assertOk()
            ->assertJsonPath('data.app_name', 'Barber Deluxe')
            ->assertJsonPath('data.config_version', 2);

        $fresh = $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']) + [
            'If-None-Match' => $etagBefore,
        ])->assertOk(); // 200, not 304: the version changed

        self::assertSame('Barber Deluxe', $fresh->json('app_name'));
        self::assertSame('#0F172A', $fresh->json('theme.colors.primary'));
    }

    public function test_low_contrast_palette_is_rejected(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->createTenantAdmin($env['tenant']);

        $this->putJson('/api/v1/manage/brand', [
            'primary_color' => '#FEFEFE', // white-on-white with on_primary #FFFFFF
        ], $this->authHeaders($admin, $env['tenant']))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'insufficient_contrast');
    }

    public function test_suspended_tenant_gets_courtesy_payload_not_an_error(): void
    {
        $env = $this->provisionBookableTenant();

        $this->bypassTenancy(function () use ($env): void {
            $env['tenant']->forceFill(['status' => TenantStatus::Suspended, 'suspended_at' => now()])->save();
        });
        app(TenantRegistry::class)->forget($env['tenant']->id);

        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->assertOk()
            ->assertJsonPath('tenant_status', 'suspended')
            ->assertJsonPath('message_key', 'service_suspended')
            ->assertJsonMissingPath('locations');

        // But booking endpoints are blocked (docs/08 Flusso 9).
        $this->getJson('/api/v1/catalog/services', $this->tenantKeyHeaders($env['tenant']))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'tenant_not_operating');
    }
}
