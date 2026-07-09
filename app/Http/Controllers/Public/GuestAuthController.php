<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Otp;
use App\Services\FcmNotificationService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class GuestAuthController extends Controller
{
    public function sendOtp(Request $request)
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
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this phone number. Please place an order first to track it.',
                ], 404);
            }
            $otpKey = $request->phone;
        } else {
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email. Please place an order first to track it.',
                ], 404);
            }
            $otpKey = $request->email;
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
                Log::error('FCM Token registration failed during guest login', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Generate 6 digit OTP
        $otpCode = rand(100000, 999999);

        Otp::updateOrCreate(
            ['email' => $otpKey],
            [
                'otp_code' => Hash::make((string) $otpCode),
                'expires_at' => Carbon::now()->addMinutes(15),
            ]
        );

        // Send OTP via FCM if user has device tokens
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
                Log::error('Failed to send guest login OTP via FCM', [
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
                Log::error('Failed to send guest login OTP via Email', [
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

        // Delete OTP
        $otpRecord->delete();

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'is_guest' => $user->is_guest,
                ],
                'token' => $token
            ]
        ]);
    }
}
