<?php
class JWT {

    private static $secret = 'campmart_jwt_secret_key_2026_v1';
    private static $algo = 'HS256';

    public static function encode($payload) {
        $header = self::base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => self::$algo
        ]));

        $payload['iat'] = $payload['iat'] ?? time();
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", self::$secret, true)
        );

        return "$header.$payloadEncoded.$signature";
    }

    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", self::$secret, true)
        );

        if (!hash_equals($expectedSig, $signature)) {
            return false;
        }

        $payloadData = json_decode(self::base64UrlDecode($payload), true);
        if (!$payloadData) {
            return false;
        }

        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return false;
        }

        return $payloadData;
    }

    public static function generateToken($userId, $role, $expiryHours = 24) {
        return self::encode([
            'user_id' => (int) $userId,
            'role' => $role,
            'exp' => time() + ($expiryHours * 3600),
            'iat' => time(),
            'type' => 'access'
        ]);
    }

    public static function generateRefreshToken($userId, $expiryDays = 30) {
        return self::encode([
            'user_id' => (int) $userId,
            'exp' => time() + ($expiryDays * 86400),
            'iat' => time(),
            'type' => 'refresh'
        ]);
    }

    public static function getTokenFromHeader() {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }

        return false;
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
