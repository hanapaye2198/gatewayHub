<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MerchantLogoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_merchant_can_upload_logo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $response = $this->actingAs($user)->post(route('merchant.logo'), [
            'logo' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'merchant' => [
                'name',
                'logo',
                'theme_color',
            ],
        ]);

        $path = $user->merchant->fresh()->logo_path;
        $this->assertIsString($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_logo_upload_rejects_non_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($user)->post(route('merchant.logo'), [
            'logo' => $file,
        ]);

        $response->assertInvalid(['logo']);
    }

    public function test_guest_cannot_upload_logo(): void
    {
        $response = $this->post(route('merchant.logo'), []);

        $response->assertRedirect();
    }
}
