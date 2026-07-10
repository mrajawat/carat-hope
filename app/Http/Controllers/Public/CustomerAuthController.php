<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Otp;
use App\Services\FcmNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:6',
            'name' => 'required|string',
            'email' => 'nullable|email|unique:users,email',
            'shipping_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
        ]);

        $user = User::create([
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'name' => $request->name,
            'email' => $request->email,
            'shipping_address' => $request->shipping_address,
            'permanent_address' => $request->permanent_address,
            'status' => 'active',
        ]);

        if ($user->email) {
            // Generate 6 digit OTP
            $otpCode = rand(100000, 999999);

            // Store OTP in database
            Otp::updateOrCreate(
                ['email' => $user->email],
                [
                    'otp_code' => Hash::make((string) $otpCode),
                    'expires_at' => Carbon::now()->addMinutes(15),
                ]
            );

            // Send OTP to email
            try {
                \Illuminate\Support\Facades\Mail::raw("Your email verification code is: {$otpCode}", function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Email Verification OTP');
                });
            } catch (\Exception $e) {
                Log::error('Failed to send email verification OTP during registration', [
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }

            $response = [
                'success' => true,
                'message' => 'Registration successful. Please verify the OTP sent to your email.',
                'requires_email_verification' => true,
                'email' => $user->email,
            ];

            if (config('app.env') !== 'production' || config('app.debug')) {
                $response['test_otp'] = $otpCode;
            }

            return response()->json($response, 200);
        }

        $token = $user->createToken('customer-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'shipping_address' => $user->shipping_address,
                    'permanent_address' => $user->permanent_address,
                    'status' => $user->status,
                ]
            ]
        ], 201);
    }

    public function verifyRegisterEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        $otpRecord = Otp::where('email', $request->email)->first();

        if (!$otpRecord || Carbon::now()->isAfter($otpRecord->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired or is invalid.',
            ], 400);
        }

        if (!Hash::check($request->otp, $otpRecord->otp_code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Mark email as verified
        $user->update([
            'email_verified_at' => Carbon::now()
        ]);

        // Delete OTP record
        $otpRecord->delete();

        $token = $user->createToken('customer-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'shipping_address' => $user->shipping_address,
                    'permanent_address' => $user->permanent_address,
                    'status' => $user->status,
                ]
            ]
        ]);
    }

    public function resendRegisterOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.',
            ], 404);
        }

        if ($user->email_verified_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This email address is already verified.',
            ], 400);
        }

        // Generate 6 digit OTP
        $otpCode = rand(100000, 999999);

        // Store OTP in database
        Otp::updateOrCreate(
            ['email' => $user->email],
            [
                'otp_code' => Hash::make((string) $otpCode),
                'expires_at' => Carbon::now()->addMinutes(15),
            ]
        );

        // Send OTP to email
        try {
            \Illuminate\Support\Facades\Mail::raw("Your email verification code is: {$otpCode}", function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Email Verification OTP');
            });
        } catch (\Exception $e) {
            Log::error('Failed to send email verification OTP during resend-otp', [
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
        }

        $response = [
            'success' => true,
            'message' => 'OTP has been resent to your email.',
        ];

        if (config('app.env') !== 'production' || config('app.debug')) {
            $response['test_otp'] = $otpCode;
        }

        return response()->json($response, 200);
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required_without:email|string',
            'email' => 'required_without:phone|email',
            'fcm_token' => 'nullable|string',
            'device_type' => 'nullable|string|in:android,ios,web',
        ]);

        if ($request->filled('phone')) {
            $user = User::where('phone', $request->phone)->first();
            if (!$user) {
                throw ValidationException::withMessages([
                    'phone' => ['No account found with this phone number.'],
                ]);
            }
            $otpKey = $request->phone;
        } else {
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                throw ValidationException::withMessages([
                    'email' => ['No account found with this email address.'],
                ]);
            }
            $otpKey = $request->email;
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact support.'
            ], 403);
        }

        // Save FCM token if provided in request
        if ($request->filled('fcm_token')) {
            try {
                $fcmService = app(FcmNotificationService::class);
                $fcmService->saveToken(
                    $user,
                    $request->input('fcm_token'),
                    $request->input('device_type', 'android')
                );
            } catch (\Exception $e) {
                Log::error('FCM Token registration failed during customer login', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Generate 6 digit OTP
        $otpCode = rand(100000, 999999);

        // Store OTP in database
        Otp::updateOrCreate(
            ['email' => $otpKey],
            [
                'otp_code' => Hash::make((string) $otpCode),
                'expires_at' => Carbon::now()->addMinutes(15),
            ]
        );

        // Send OTP via FCM (if user has device tokens)
        $tokens = $user->deviceTokens()->pluck('fcm_token')->toArray();
        $fcmSent = false;
        if (!empty($tokens)) {
            try {
                $fcmService = app(FcmNotificationService::class);
                $fcmService->sendToTokens(
                    $tokens,
                    'Login OTP',
                    "Your verification OTP is: {$otpCode}",
                    ['otp' => (string) $otpCode]
                );
                $fcmSent = true;
            } catch (\Exception $e) {
                Log::error('Failed to send login OTP via FCM', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send OTP via Email if logging in via email or user has an email
        $emailSent = false;
        if ($user->email && ($request->filled('email') || !$fcmSent)) {
            try {
                \Illuminate\Support\Facades\Mail::raw("Your verification OTP is: {$otpCode}", function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Login OTP');
                });
                $emailSent = true;
            } catch (\Exception $e) {
                Log::error('Failed to send login OTP via Email', [
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $message = 'OTP sent successfully.';
        if ($fcmSent && $emailSent) {
            $message = 'OTP sent to your registered device(s) via FCM and your email.';
        } elseif ($fcmSent) {
            $message = 'OTP sent to your registered device(s) via FCM.';
        } elseif ($emailSent) {
            $message = 'OTP sent to your email address.';
        }

        $response = [
            'success' => true,
            'message' => $message,
            'requires_otp' => true,
        ];

        // Only return OTP in response for local/testing environments
        if (config('app.env') !== 'production' || config('app.debug')) {
            $response['test_otp'] = $otpCode;
            $response['message'] = 'OTP generated successfully (returned in response for testing).';
        }

        return response()->json($response);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required_without:email|string',
            'email' => 'required_without:phone|email',
            'otp' => 'required|string',
        ]);

        $otpKey = $request->filled('phone') ? $request->phone : $request->email;

        $otpRecord = Otp::where('email', $otpKey)->first();

        if (!$otpRecord || Carbon::now()->isAfter($otpRecord->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired or is invalid.',
            ], 400);
        }

        if (!Hash::check($request->otp, $otpRecord->otp_code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 400);
        }

        if ($request->filled('phone')) {
            $user = User::where('phone', $request->phone)->first();
        } else {
            $user = User::where('email', $request->email)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact support.'
            ], 403);
        }

        // Delete OTP record
        $otpRecord->delete();

        // Generate Sanctum token
        $token = $user->createToken('customer-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'shipping_address' => $user->shipping_address,
                    'permanent_address' => $user->permanent_address,
                    'status' => $user->status,
                ]
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully.'
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'shipping_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'shipping_address' => $request->shipping_address,
            'permanent_address' => $request->permanent_address,
        ]);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }
}
