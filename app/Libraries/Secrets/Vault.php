<?php

declare(strict_types=1);

namespace App\Libraries\Secrets;

use CodeIgniter\Encryption\Encryption;
use CodeIgniter\Encryption\EncrypterInterface;

/**
 * Encrypts/decrypts credential fields (Cashfree secret keys, MSG91 auth
 * keys, SMTP passwords, etc.) at rest using the app's encryption.key.
 *
 * Ciphertext is stored as base64 text so it fits plain TEXT/VARCHAR columns
 * without binary-safety concerns in MySQL.
 */
class Vault
{
    private static ?EncrypterInterface $encrypter = null;

    private static function encrypter(): EncrypterInterface
    {
        if (self::$encrypter === null) {
            self::$encrypter = (new Encryption())->initialize(config('Encryption'));
        }

        return self::$encrypter;
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return base64_encode(self::encrypter()->encrypt($plain));
    }

    public static function decrypt(?string $cipherB64): ?string
    {
        if ($cipherB64 === null || $cipherB64 === '') {
            return null;
        }

        try {
            return self::encrypter()->decrypt(base64_decode($cipherB64, true));
        } catch (\Throwable $e) {
            log_message('error', 'Vault decrypt failed: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Returns a fixed-length masked preview (e.g. "••••••••••••7F2A") for
     * display in settings screens without ever exposing the plaintext.
     */
    public static function mask(?string $plain, int $tailLength = 4): string
    {
        if ($plain === null || $plain === '') {
            return '';
        }

        $tail = substr($plain, -$tailLength);

        return str_repeat('•', 12) . strtoupper($tail);
    }
}
