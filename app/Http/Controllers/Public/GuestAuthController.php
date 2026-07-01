<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class GuestAuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email. Please place an order first to track it.',
            ], 404);
        }

        // Generate 6 digit OTP
        $otpCode = rand(100000, 999999);

        Otp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp_code' => Hash::make((string) $otpCode),
                'expires_at' => Carbon::now()->addMinutes(15),
            ]
        );

        $response = [
            'success' => true,
            'message' => 'OTP sent successfully to your email.',
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
                    'email' => $user->email,
                    'is_guest' => $user->is_guest,
                ],
                'token' => $token
            ]
        ]);
    }
}
