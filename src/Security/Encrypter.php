<?php

declare(strict_types=1);

namespace Plugs\Security;

/*
|--------------------------------------------------------------------------
| Encrypter Class
|--------------------------------------------------------------------------
|
| This class is for encrypting and decrypting data using OpenSSL.
*/

class Encrypter
{
    private $key;
    private $cipher = 'aes-256-gcm';

    public function __construct(string $key)
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('Encryption key must be 32 bytes');
        }

        $this->key = $key;
    }

    public function encrypt($data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $tag = '';

        $encrypted = openssl_encrypt(
            json_encode($data, JSON_THROW_ON_ERROR),
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (!$encrypted) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $encrypted)
    {
        $data = base64_decode($encrypted, true);

        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);

        if (strlen($data) < $ivLength + 16) {
            throw new \RuntimeException('Encrypted data is too short');
        }

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, 16);
        $ciphertext = substr($data, $ivLength + 16);

        $decrypted = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed — data may be tampered');
        }

        return json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
    }
}
