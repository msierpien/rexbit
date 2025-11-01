<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Integration;
use App\Models\IntegrationImportProfile;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationImportProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => Role::ADMIN]);
        $this->integration = Integration::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'prestashop',
            'config' => [
                'api_url' => 'https://test.prestashop.com',
                'api_key' => 'test-key-123',
            ],
        ]);
    }

    public function test_user_can_create_import_profile(): void
    {
        $catalog = \App\Models\ProductCatalog::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('integrations.import-profiles.store', $this->integration), [
                'name' => 'Test Import Profile',
                'catalog_id' => $catalog->id,
                'format' => 'csv',
                'source_type' => 'url',
                'source_location' => 'https://example.com/products.csv',
                'delimiter' => ',',
                'has_header' => true,
                'is_active' => true,
                'fetch_mode' => 'manual',
            ]);

        // Either redirect or created response is acceptable
        $this->assertTrue(
            $response->isRedirect() || $response->isCreated(),
            'Expected redirect or created response, got: ' . $response->status()
        );
        
        $this->assertDatabaseHas('integration_import_profiles', [
            'integration_id' => $this->integration->id,
            'name' => 'Test Import Profile',
            'catalog_id' => $catalog->id,
        ]);
    }

    public function test_user_can_update_import_profile(): void
    {
        $profile = IntegrationImportProfile::factory()->create([
            'integration_id' => $this->integration->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('integrations.import-profiles.update', [$this->integration, $profile]), [
                'name' => 'New Name',
                'catalog_id' => $profile->catalog_id,
                'format' => $profile->format,
                'source_type' => $profile->source_type,
                'source_location' => $profile->source_location,
                'delimiter' => $profile->delimiter,
                'has_header' => $profile->has_header,
                'is_active' => $profile->is_active,
                'fetch_mode' => $profile->fetch_mode,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('integration_import_profiles', [
            'id' => $profile->id,
            'name' => 'New Name',
        ]);
    }

    public function test_user_can_delete_import_profile(): void
    {
        $profile = IntegrationImportProfile::factory()->create([
            'integration_id' => $this->integration->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('integrations.import-profiles.destroy', [$this->integration, $profile]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('integration_import_profiles', [
            'id' => $profile->id,
        ]);
    }

    public function test_user_can_refresh_import_profile_headers(): void
    {
        $profile = IntegrationImportProfile::factory()->create([
            'integration_id' => $this->integration->id,
            'format' => 'csv',
            'source_type' => 'url',
            'source_location' => 'https://example.com/test.csv',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('integrations.import-profiles.refresh', [$this->integration, $profile]));

        $response->assertStatus(200);
    }

    public function test_user_can_run_import_profile(): void
    {
        $profile = IntegrationImportProfile::factory()->create([
            'integration_id' => $this->integration->id,
            'format' => 'csv',
            'source_type' => 'url',
            'source_location' => 'https://example.com/products.csv',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('integrations.import-profiles.run', [$this->integration, $profile]));

        $response->assertStatus(200);
    }

    public function test_user_cannot_create_import_profile_for_other_users_integration(): void
    {
        $otherUser = User::factory()->create(['role' => Role::USER]);
        $otherIntegration = Integration::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $catalog = \App\Models\ProductCatalog::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('integrations.import-profiles.store', $otherIntegration), [
                'name' => 'Test Import Profile',
                'catalog_id' => $catalog->id,
                'format' => 'csv',
                'source_type' => 'url',
                'source_location' => 'https://example.com/test.csv',
                'delimiter' => ',',
                'has_header' => true,
                'is_active' => false,
                'fetch_mode' => 'manual',
            ]);

        $response->assertStatus(403);
    }
}
