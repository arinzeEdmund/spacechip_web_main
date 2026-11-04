<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a text column at rest (AES-256 via APP_KEY). Reads tolerate legacy plaintext rows so the rollout needs
 * no downtime; run `php artisan data:encrypt-messages` once to encrypt what is already stored.
 */
class EncryptedText implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return $value; // legacy plaintext
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value === null || $value === '' ? $value : Crypt::encryptString((string) $value);
    }
}
