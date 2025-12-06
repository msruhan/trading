<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use RuntimeException;

class EncryptionService
{
    private string $key;
    private string $cipher = 'aes-256-gcm';
    private int $tagLength = 16;

    public function __construct()
    {
        $key = Config::get('app.credentials_key') ?: env('CREDENTIALS_ENCRYPTION_KEY');
        
        if (!$key) {
            // Fallback to app key if no specific credentials key is set
            $key = Config::get('app.key');
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }
        } else {
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }
        }

        if (strlen($key) !== 32) {
            $key = hash('sha256', $key, true);
        }

        $this->key = $key;
    }

    /**
     * Encrypt data using AES-256-GCM.
     */
    public function encrypt(string $data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            $this->tagLength
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Combine IV + Tag + Encrypted data
        $combined = $iv . $tag . $encrypted;
        
        return base64_encode($combined);
    }

    /**
     * Decrypt data.
     */
    public function decrypt(string $data): string
    {
        $combined = base64_decode($data);
        
        if ($combined === false) {
            throw new RuntimeException('Invalid encrypted data format');
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        
        // Extract IV, Tag, and Encrypted data
        $iv = substr($combined, 0, $ivLength);
        $tag = substr($combined, $ivLength, $this->tagLength);
        $encrypted = substr($combined, $ivLength + $this->tagLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * Generate a new encryption key.
     */
    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }
}

