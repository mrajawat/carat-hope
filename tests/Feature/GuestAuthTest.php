<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Otp;
use App\Models\DeviceToken;
use App\Services\FcmNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuestAuthTest extends TestCase
{
    use RefreshDatabase;

    protected User $guestUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a guest user
        $this->guestUser = User::create([
            'name' => 'Jane Guest',
            'phone' => '9998887776',
            'email' => 'jane.guest@example.com',
            'password' => null,
            'is_guest' => true,
            'status' => 'active',
        ]);
    }

    /**
     * Test guest login via email sends OTP.
     */
    public function test_guest_login_via_email_sends_otp(): void
    {
        $response = $this->postJson('/api/public/guest/send-otp', [
            'email' => 'jane.guest@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure(['test_otp']);

        $this->assertDatabaseHas('otps', [
            'email' => 'jane.guest@example.com',
        ]);
    }

    /**
     * Test guest verification via email OTP.
     */
    public function test_guest_verify_email_otp_successfully(): void
    {
        $sendResponse = $this->postJson('/api/public/guest/send-otp', [
            'email' => 'jane.guest@example.com',
        ]);

        $otp = $sendResponse->json('test_otp');
        $this->assertNotNull($otp);

        $verifyResponse = $this->postJson('/api/public/guest/verify-otp', [
            'email' => 'jane.guest@example.com',
            'otp' => (string) $otp,
        ]);

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'is_guest'
                ]
            ]
        ]);

        $this->assertDatabaseMissing('otps', [
            'email' => 'jane.guest@example.com',
        ]);
    }

    /**
     * Test guest login via phone sends OTP and FCM if mock service is used.
     */
    public function test_guest_login_via_phone_sends_otp(): void
    {
        $this->mock(FcmNotificationService::class, function ($mock) {
            $mock->shouldReceive('saveToken')->andReturn(new DeviceToken());
            $mock->shouldReceive('sendToTokens')->andReturn(['success' => true]);
        });

        $response = $this->postJson('/api/public/guest/send-otp', [
            'phone' => '9998887776',
            'fcm_token' => 'fcm-guest-token',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('otps', [
            'email' => '9998887776',
        ]);
    }

    /**
     * Test guest verification via phone OTP.
     */
    public function test_guest_verify_phone_otp_successfully(): void
    {
        $sendResponse = $this->postJson('/api/public/guest/send-otp', [
            'phone' => '9998887776',
        ]);

        $otp = $sendResponse->json('test_otp');
        $this->assertNotNull($otp);

        $verifyResponse = $this->postJson('/api/public/guest/verify-otp', [
            'phone' => '9998887776',
            'otp' => (string) $otp,
        ]);

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'phone',
                    'is_guest'
                ]
            ]
        ]);

        $this->assertDatabaseMissing('otps', [
            'email' => '9998887776',
        ]);
    }

    /**
     * Test guest login fails when phone/email is not found.
     */
    public function test_guest_login_nonexistent_fails(): void
    {
        $response = $this->postJson('/api/public/guest/send-otp', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'No account found with this email. Please place an order first to track it.',
        ]);
    }
}
