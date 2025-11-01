<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Integration;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => Role::ADMIN]);
    }

    public function test_user_can_view_integrations_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('integrations.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Integrations/Index'));
    }

    public function test_user_can_create_integration(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('integrations.store'), [
                'name' => 'Test Prestashop',
                'type' => 'prestashop',
                'config' => [
                    'api_url' => 'https://test.prestashop.com',
                    'api_key' => 'test-key-123',
                ],
            ]);

        $response->assertRedirect(route('integrations.index'));
        $this->assertDatabaseHas('integrations', [
            'name' => 'Test Prestashop',
            'type' => 'prestashop',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_test_integration_connection(): void
    {
        $integration = Integration::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'prestashop',
            'config' => [
                'api_url' => 'https://test.prestashop.com',
                'api_key' => 'test-key-123',
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('integrations.test', $integration));

        $response->assertStatus(200);
    }

    public function test_user_can_update_integration(): void
    {
        $integration = Integration::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('integrations.update', $integration), [
                'name' => 'New Name',
                'type' => $integration->type->value,
                'config' => $integration->config,
            ]);

        $response->assertRedirect(route('integrations.index'));
        $this->assertDatabaseHas('integrations', [
            'id' => $integration->id,
            'name' => 'New Name',
        ]);
    }

    public function test_user_can_delete_integration(): void
    {
        $integration = Integration::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('integrations.destroy', $integration));

        $response->assertRedirect(route('integrations.index'));
        $this->assertDatabaseMissing('integrations', [
            'id' => $integration->id,
        ]);
    }

    public function test_user_cannot_access_other_users_integration(): void
    {
        $otherUser = User::factory()->create(['role' => Role::USER]);
        $integration = Integration::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('integrations.edit', $integration));

        $response->assertStatus(403);
    }
}
