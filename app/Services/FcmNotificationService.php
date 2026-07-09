<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\MulticastSendReport;

class FcmNotificationService
{
    protected Messaging $messaging;

    /**
     * FcmNotificationService constructor.
     *
     * @param Messaging $messaging
     */
    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Register or update a device token for a user.
     *
     * @param User $user
     * @param string $fcmToken
     * @param string $deviceType
     * @return DeviceToken
     */
    public function saveToken(User $user, string $fcmToken, string $deviceType = 'android'): DeviceToken
    {
        // If the token is already registered to another user, updateOrCreate will
        // re-assign it to the new user and update the last used timestamp.
        return DeviceToken::updateOrCreate(
            ['fcm_token' => $fcmToken],
            [
                'user_id' => $user->id,
                'device_type' => $deviceType,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Delete/Revoke a device token.
     *
     * @param string $fcmToken
     * @return bool
     */
    public function deleteToken(string $fcmToken): bool
    {
        return (bool) DeviceToken::where('fcm_token', $fcmToken)->delete();
    }

    /**
     * Send a notification to a specific user on all their registered devices.
     *
     * @param User|int $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToUser($user, string $title, string $body, array $data = []): array
    {
        if (is_numeric($user)) {
            $user = User::find($user);
        }

        if (!$user) {
            Log::warning('FCM: User not found.', ['user' => $user]);
            return [
                'success' => false,
                'message' => 'User not found',
                'sent_count' => 0
            ];
        }

        $tokens = $user->deviceTokens()->pluck('fcm_token')->toArray();

        if (empty($tokens)) {
            Log::info('FCM: No device tokens found for user.', ['user_id' => $user->id]);
            return [
                'success' => true,
                'message' => 'No active device tokens found for this user',
                'sent_count' => 0
            ];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send a notification to multiple device tokens.
     *
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No tokens provided',
                'sent_count' => 0
            ];
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $report = $this->messaging->sendMulticast($message, $tokens);

            $this->handleSendReport($report);

            return [
                'success' => true,
                'sent_count' => $report->successes()->count(),
                'failed_count' => $report->failures()->count(),
                'invalid_tokens_removed' => count($report->invalidTokens()) + count($report->unknownTokens()),
            ];
        } catch (Exception $e) {
            Log::error('FCM: Multicast sending failed.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'sent_count' => 0
            ];
        }
    }

    /**
     * Send a notification to a specific topic.
     *
     * @param string $topic
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        try {
            $message = CloudMessage::new()
                ->withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $result = $this->messaging->send($message);

            return [
                'success' => true,
                'message' => 'Notification sent to topic successfully',
                'result' => $result
            ];
        } catch (Exception $e) {
            Log::error('FCM: Sending to topic failed.', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Subscribe device tokens to a topic.
     *
     * @param string $topic
     * @param array|string $tokens
     * @return array
     */
    public function subscribeToTopic(string $topic, $tokens): array
    {
        $tokens = (array) $tokens;
        if (empty($tokens)) {
            return ['success' => false, 'message' => 'No tokens provided'];
        }

        try {
            $result = $this->messaging->subscribeToTopic($topic, $tokens);
            return [
                'success' => true,
                'result' => $result
            ];
        } catch (Exception $e) {
            Log::error('FCM: Topic subscription failed.', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Unsubscribe device tokens from a topic.
     *
     * @param string $topic
     * @param array|string $tokens
     * @return array
     */
    public function unsubscribeFromTopic(string $topic, $tokens): array
    {
        $tokens = (array) $tokens;
        if (empty($tokens)) {
            return ['success' => false, 'message' => 'No tokens provided'];
        }

        try {
            $result = $this->messaging->unsubscribeFromTopic($topic, $tokens);
            return [
                'success' => true,
                'result' => $result
            ];
        } catch (Exception $e) {
            Log::error('FCM: Topic unsubscription failed.', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle the multicast send report to clean up stale/invalid tokens.
     *
     * @param MulticastSendReport $report
     * @return void
     */
    protected function handleSendReport(MulticastSendReport $report): void
    {
        $invalidTokens = array_merge(
            $report->invalidTokens(),
            $report->unknownTokens()
        );

        if (!empty($invalidTokens)) {
            Log::info('FCM: Cleaning up invalid and unknown device tokens.', [
                'count' => count($invalidTokens),
                'tokens' => $invalidTokens
            ]);

            DeviceToken::whereIn('fcm_token', $invalidTokens)->delete();
        }
    }
}
