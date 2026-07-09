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

class CustomerFcmOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Customer',
            'phone' => '9876543210',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
    }

    /**
     * Test successful login initiation which registers FCM token and triggers FCM OTP.
     */
    public function test_login_initiates_otp_and_sends_fcm_notification(): void
    {
        $this->mock(FcmNotificationService::class, function ($mock) {
            $mock->shouldReceive('saveToken')
                ->once()
                ->with(\Mockery::on(function ($u) {
                    return $u->id === $this->user->id;
                }), 'test-fcm-token-123', 'android')
                ->andReturnUsing(function ($user, $token, $type) {
                    return DeviceToken::create([
                        'user_id' => $user->id,
                        'fcm_token' => $token,
                        'device_type' => $type,
                        'last_used_at' => now(),
                    ]);
                });

            $mock->shouldReceive('sendToTokens')
                ->once()
                ->with(
                    ['test-fcm-token-123'],
                    'Login OTP',
                    \Mockery::pattern('/Your verification OTP is: \d{6}/'),
                    \Mockery::on(function ($data) {
                        return isset($data['otp']) && preg_match('/^\d{6}$/', $data['otp']);
                    })
                )
                ->andReturn([
                    'success' => true,
                    'sent_count' => 1,
                    'failed_count' => 0,
                    'invalid_tokens_removed' => 0
                ]);
        });

        $payload = [
            'phone' => '9876543210',
            'fcm_token' => 'test-fcm-token-123',
            'device_type' => 'android',
        ];

        $response = $this->postJson('/api/public/login/send-otp', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'requires_otp',
            'test_otp'
        ]);

        $response->assertJson([
            'success' => true,
            'requires_otp' => true,
        ]);

        $this->assertDatabaseHas('otps', [
            'email' => '9876543210',
        ]);
    }

    /**
     * Test login with non-existent phone number fails.
     */
    public function test_login_with_non_existent_phone_fails(): void
    {
        $payload = [
            'phone' => '1111111111',
        ];

        $response = $this->postJson('/api/public/login/send-otp', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);

        $this->assertDatabaseMissing('otps', [
            'email' => '1111111111',
        ]);
    }

    /**
     * Test verifying OTP successfully.
     */
    public function test_verify_otp_successfully(): void
    {
        $this->mock(FcmNotificationService::class, function ($mock) {
            $mock->shouldReceive('saveToken')->andReturn(new DeviceToken());
            $mock->shouldReceive('sendToTokens')->andReturn(['success' => true]);
        });

        $loginResponse = $this->postJson('/api/public/login/send-otp', [
            'phone' => '9876543210',
        ]);

        $otpCode = $loginResponse->json('test_otp');
        $this->assertNotNull($otpCode);

        $verifyResponse = $this->postJson('/api/public/login/verify-otp', [
            'phone' => '9876543210',
            'otp' => (string) $otpCode,
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
                    'email',
                ]
            ]
        ]);

        $verifyResponse->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseMissing('otps', [
            'email' => '9876543210',
        ]);
    }

    /**
     * Test verifying incorrect OTP fails.
     */
    public function test_verify_otp_incorrect_fails(): void
    {
        $this->mock(FcmNotificationService::class, function ($mock) {
            $mock->shouldReceive('saveToken')->andReturn(new DeviceToken());
            $mock->shouldReceive('sendToTokens')->andReturn(['success' => true]);
        });

        $this->postJson('/api/public/login/send-otp', [
            'phone' => '9876543210',
        ]);

        $verifyResponse = $this->postJson('/api/public/login/verify-otp', [
            'phone' => '9876543210',
            'otp' => '000000',
        ]);

        $verifyResponse->assertStatus(400);
        $verifyResponse->assertJson([
            'success' => false,
            'message' => 'Invalid OTP.',
        ]);
    }

    /**
     * Test verifying expired OTP fails.
     */
    public function test_verify_otp_expired_fails(): void
    {
        $this->mock(FcmNotificationService::class, function ($mock) {
            $mock->shouldReceive('saveToken')->andReturn(new DeviceToken());
            $mock->shouldReceive('sendToTokens')->andReturn(['success' => true]);
        });

        $loginResponse = $this->postJson('/api/public/login/send-otp', [
            'phone' => '9876543210',
        ]);

        $otpCode = $loginResponse->json('test_otp');

        Carbon::setTestNow(Carbon::now()->addMinutes(16));

        $verifyResponse = $this->postJson('/api/public/login/verify-otp', [
            'phone' => '9876543210',
            'otp' => (string) $otpCode,
        ]);

        $verifyResponse->assertStatus(400);
        $verifyResponse->assertJson([
            'success' => false,
            'message' => 'OTP has expired or is invalid.',
        ]);

        Carbon::setTestNow();
    }

    /**
     * Test registration with email triggers OTP and requires email verification.
     */
    public function test_registration_with_email_requires_verification(): void
    {
        $payload = [
            'phone' => '9999999999',
            'password' => 'password123',
            'name' => 'Jane Registrant',
            'email' => 'jane@example.com',
        ];

        $response = $this->postJson('/api/public/register', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'requires_email_verification' => true,
            'email' => 'jane@example.com',
        ]);
        $response->assertJsonStructure(['test_otp']);

        $this->assertDatabaseHas('users', [
            'phone' => '9999999999',
            'email' => 'jane@example.com',
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('otps', [
            'email' => 'jane@example.com',
        ]);
    }

    /**
     * Test successful email verification after registration.
     */
    public function test_verify_registration_email_successfully(): void
    {
        $payload = [
            'phone' => '9999999999',
            'password' => 'password123',
            'name' => 'Jane Registrant',
            'email' => 'jane@example.com',
        ];

        $registerResponse = $this->postJson('/api/public/register', $payload);
        $otp = $registerResponse->json('test_otp');

        $verifyResponse = $this->postJson('/api/public/register/verify-email', [
            'email' => 'jane@example.com',
            'otp' => (string) $otp,
        ]);

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'phone',
                    'email',
                ]
            ]
        ]);

        $this->assertNotNull(User::where('email', 'jane@example.com')->first()->email_verified_at);
        $this->assertDatabaseMissing('otps', [
            'email' => 'jane@example.com',
        ]);
    }

    /**
     * Test registration without email directly returns token.
     */
    public function test_registration_without_email_directly_returns_token(): void
    {
        $payload = [
            'phone' => '8888888888',
            'password' => 'password123',
            'name' => 'No Email Registrant',
        ];

        $response = $this->postJson('/api/public/register', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'phone',
                ]
            ]
        ]);

        $response->assertJsonMissing(['requires_email_verification']);
    }

    /**
     * Test successful login initiation via email.
     */
    public function test_login_via_email_initiates_otp_successfully(): void
    {
        $payload = [
            'email' => 'john@example.com',
        ];

        $response = $this->postJson('/api/public/login/send-otp', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'requires_otp' => true,
        ]);
        $response->assertJsonStructure(['test_otp']);

        $this->assertDatabaseHas('otps', [
            'email' => 'john@example.com',
        ]);
    }

    /**
     * Test successful verification of email login.
     */
    public function test_verify_email_login_successfully(): void
    {
        $loginResponse = $this->postJson('/api/public/login/send-otp', [
            'email' => 'john@example.com',
        ]);

        $otp = $loginResponse->json('test_otp');
        $this->assertNotNull($otp);

        $verifyResponse = $this->postJson('/api/public/login/verify-otp', [
            'email' => 'john@example.com',
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
                    'email',
                ]
            ]
        ]);

        $this->assertDatabaseMissing('otps', [
            'email' => 'john@example.com',
        ]);
    }

    /**
     * Test login with non-existent email address fails.
     */
    public function test_login_with_non_existent_email_fails(): void
    {
        $payload = [
            'email' => 'nonexistent@example.com',
        ];

        $response = $this->postJson('/api/public/login/send-otp', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}
