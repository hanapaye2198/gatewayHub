<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCredentialsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_card_explains_encrypted_visibility(): void
    {
        $user = User::factory()->create();
        $user->merchant->forceFill(['api_key' => 'test-api-key-1234'])->save();

        $response = $this->actingAs($user)->get(route('dashboard.api-credentials'));

        $response->assertOk();
        $response->assertSee('API keys are encrypted at rest.');
        $response->assertSee('****1234');
        $response->assertSee('toggleShowApiKey');
    }
}
