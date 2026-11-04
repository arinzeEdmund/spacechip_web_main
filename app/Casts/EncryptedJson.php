<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts an array stored in a JSON column. The ciphertext is stored as a JSON string so the column stays valid
 * JSON. Legacy plaintext arrays are still readable.
 */
class EncryptedJson implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode((string) $value, true);
        if (is_string($decoded)) {
            try {
                return json_decode(Crypt::decryptString($decoded), true);
            } catch (DecryptException) {
                return null;
            }
        }

        return $decoded; // legacy plaintext array
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value === null ? null : json_encode(Crypt::encryptString(json_encode($value, JSON_UNESCAPED_UNICODE)));
    }
}
