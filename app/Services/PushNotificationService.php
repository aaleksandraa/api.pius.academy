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
                Log::info("Push notification sent successfully to token: " . substr($token, 0, 20) . "...");
                return true;
            } else {
                Log::error("FCM error: " . $response->body());
                
                // Remove invalid token
                $errorCode = $response->json('error.details.0.errorCode') ?? $response->json('error.status') ?? '';
                if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'])) {
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
            
            if (!$credentials || !isset($credentials['private_key']) || !isset($credentials['client_email'])) {
                Log::error("Invalid Firebase credentials file");
                return null;
            }

            // Create JWT header
            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $headerEncoded = $this->base64UrlEncode($header);
            
            // Create JWT payload
            $now = time();
            $payload = json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);
            $payloadEncoded = $this->base64UrlEncode($payload);
            
            // Sign with private key
            $signature = '';
            $privateKey = openssl_pkey_get_private($credentials['private_key']);
            
            if (!$privateKey) {
                Log::error("Failed to load private key from credentials");
                return null;
            }
            
            openssl_sign(
                $headerEncoded . '.' . $payloadEncoded,
                $signature,
                $privateKey,
                OPENSSL_ALGO_SHA256
            );
            
            $signatureEncoded = $this->base64UrlEncode($signature);
            
            // Create JWT
            $jwt = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
            
            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $accessToken = $response->json('access_token');
                Log::info("Successfully obtained FCM access token");
                return $accessToken;
            } else {
                Log::error("Failed to get access token: " . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Access token generation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

