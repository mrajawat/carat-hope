<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\FcmNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    protected FcmNotificationService $fcmService;

    /**
     * DeviceTokenController constructor.
     *
     * @param FcmNotificationService $fcmService
     */
    public function __construct(FcmNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Store/Update a device token for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => ['required', 'string'],
            'device_type' => ['nullable', 'string', 'in:android,ios,web'],
        ]);

        $deviceToken = $this->fcmService->saveToken(
            $request->user(),
            $request->input('fcm_token'),
            $request->input('device_type', 'android')
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token stored successfully.',
            'data' => $deviceToken
        ]);
    }

    /**
     * Delete/Revoke a device token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => ['required', 'string'],
        ]);

        $this->fcmService->deleteToken($request->input('fcm_token'));

        return response()->json([
            'success' => true,
            'message' => 'Device token removed successfully.'
        ]);
    }
}
