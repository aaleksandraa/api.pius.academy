<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PushNotificationService
{
    private string $projectId = 'pius-academy';
    private string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = storage_path('firebase-credentials.json');
    }

    /**
     * Send push notification to all users
     */
    public function sendToAll(string $title, string $body, array $data = []): void
    {
        $tokens = PushToken::where('is_active', true)->pluck('token')->toArray();
        
        if (empty($tokens)) {
            Log::info('No active push tokens found');
            return;
        }

        Log::info('Sending push to ' . count($tokens) . ' tokens');

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * Send push notification to specific user
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = PushToken::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();
        
        if (empty($tokens)) {
            Log::info("No active push tokens found for user {$userId}");
            return;
        }

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * Send push notification to a specific token via FCM HTTP v1 API
     */
    protected function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            Log::warning('Could not get FCM access token');
            return false;
        }

        try {
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'default',
                            'default_sound' => true,
                            'default_vibrate_timings' => true,
                            'notification_priority' => 'PRIORITY_HIGH',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ],
                        ],
                    ],
                    'data' => array_map('strval', $data),
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $message);

            if ($response->successful()) {
                Log::info("Push notification sent successfully");
                return true;
            } else {
                Log::error("FCM error: " . $response->body());
                
                // Remove invalid token
                $error = $response->json('error.details.0.errorCode') ?? '';
                if (in_array($error, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                    PushToken::where('token', $token)->delete();
                    Log::info("Removed invalid token");
                }
                
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Push notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get OAuth2 access token for FCM
     */
    protected function getAccessToken(): ?string
    {
        // Cache the token for 50 minutes (tokens last 60 minutes)
        return Cache::remember('fcm_access_token', 3000, function () {
            return $this->generateAccessToken();
        });
    }

    /**
     * Generate new OAuth2 access token using service account
     */
    protected function generateAccessToken(): ?string
    {
        if (!file_exists($this->credentialsPath)) {
            Log::error("Firebase credentials file not found: {$this->credentialsPath}");
            return null;
        }

        try {
            $credentials = json_decode(file_get_contents($this->credentialsPath), true);
            
            // Create JWT
            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            
            $now = time();
            $claims = [
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];
            $payload = base64_encode(json_encode($claims));
            
            // Sign with private key
            $privateKey = openssl_pkey_get_private($credentials['private_key']);
            $signature = '';
            openssl_sign("$header.$payload", $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $signature = base64_encode($signature);
            
            // URL-safe base64
            $jwt = str_replace(['+', '/', '='], ['-', '_', ''], "$header.$payload.$signature");
            
            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            } else {
                Log::error("Failed to get access token: " . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Access token generation error: " . $e->getMessage());
            return null;
        }
    }
}
