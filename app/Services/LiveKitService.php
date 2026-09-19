<?php

namespace App\Services;

use App\Models\User;

class LiveKitService
{
    /**
     * Generate a short-lived, signed LiveKit JWT access token for a room.
     */
    public function generateJoinToken(User $user, string $roomName): array
    {
        $apiKey = config('services.livekit.api_key', 'devkey');
        $apiSecret = config('services.livekit.api_secret', 'secret_key_dev_lindr_123456789');
        $wsUrl = config('services.livekit.url', 'wss://livekit.lindr.app');

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $now = time();
        $payload = [
            'iss' => $apiKey,
            'sub' => (string) $user->id,
            'name' => $user->name,
            'nbf' => $now - 5,
            'exp' => $now + 3600, // 1 hour token
            'video' => [
                'room' => $roomName,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
            ],
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $apiSecret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        $jwt = "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";

        return [
            'token' => $jwt,
            'ws_url' => $wsUrl,
            'room_name' => $roomName,
            'identity' => (string) $user->id,
            'expires_at' => date('c', $now + 3600),
        ];
    }

    /**
     * Alias for generateJoinToken for backward compatibility.
     */
    public function generateRoomToken(User $user, string $roomName): array
    {
        return $this->generateJoinToken($user, $roomName);
    }

    /**
     * Helper to encode strings as base64url.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
