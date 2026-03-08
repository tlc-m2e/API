<?php

namespace TLC\Hook\Services;

class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    public static function encrypt(string $data): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');
        if (empty($key)) {
            return $data;
        }

        $ivlen = openssl_cipher_iv_length(self::CIPHER);
        // Use a deterministic IV based on the plaintext and key, to allow for DB lookups
        // This makes the encryption deterministic (same input produces same output).
        $iv = substr(hash('sha256', $data . $key, true), 0, $ivlen);

        // Ensure key is 32 bytes for AES-256
        $hashedKey = hash('sha256', $key, true);

        $tag = '';
        $ciphertext_raw = openssl_encrypt($data, self::CIPHER, $hashedKey, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        return base64_encode($iv . $tag . $ciphertext_raw);
    }

    public static function decrypt(string $ciphertext): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');
        if (empty($key)) {
            return $ciphertext;
        }

        $c = base64_decode($ciphertext);
        if ($c === false) {
            return $ciphertext; // Invalid base64
        }
        $ivlen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($c) < $ivlen + self::TAG_LENGTH) {
            return $ciphertext; // Invalid format
        }

        $iv = substr($c, 0, $ivlen);
        $tag = substr($c, $ivlen, self::TAG_LENGTH);
        $ciphertext_raw = substr($c, $ivlen + self::TAG_LENGTH);

        $hashedKey = hash('sha256', $key, true);

        $decrypted = openssl_decrypt($ciphertext_raw, self::CIPHER, $hashedKey, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted !== false ? $decrypted : $ciphertext;
    }
}
