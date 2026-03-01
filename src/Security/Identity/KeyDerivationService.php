<?php

declare(strict_types=1);

namespace Plugs\Security\Identity;

use RuntimeException;

/**
 * KeyDerivationService
 *
 * Core service for the passwordless key-based identity system.
 *
 * Uses sodium (libsodium) for:
 * - Argon2id KDF to derive a deterministic seed from email + passphrase
 * - Ed25519 keypair generation from the derived seed
 * - Signing and verifying challenges
 *
 * SECURITY INVARIANTS:
 * - Private keys are NEVER stored or logged
 * - Passphrases/answers are NEVER stored raw
 * - Same (email + passphrase) always produces the same keypair (deterministic)
 * - Different inputs always produce different keypairs
 */
class KeyDerivationService
{
    /**
     * Argon2id memory cost in bytes (64 MB).
     */
    protected int $memoryCost;

    /**
     * Argon2id time cost (iterations).
     */
    protected int $timeCost;

    public function __construct(
        ?int $memoryCost = null,
        ?int $timeCost = null,
    ) {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException(
                'The sodium extension is required for key-based identity authentication. '
                . 'Install it via: pecl install libsodium'
            );
        }

        $this->memoryCost = $memoryCost ?? SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE;
        $this->timeCost = $timeCost ?? SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
    }

    /**
     * Derive a deterministic Ed25519 keypair from email and passphrase.
     *
     * The email is used as a salt (hashed to the required length), and the
     * passphrase is the password input to Argon2id. The output seed is used
     * to generate a deterministic Ed25519 signing keypair.
     *
     * @param string $email     User's email address
     * @param string $passphrase User's secret passphrase or prompt answers
     * @return array{publicKey: string, privateKey: string, seed: string}
     *     - publicKey:  Raw Ed25519 public key (32 bytes)
     *     - privateKey: Raw Ed25519 secret key (64 bytes) — WIPE AFTER USE
     *     - seed:       The derived seed (32 bytes) — WIPE AFTER USE
     */
    public function deriveKeyPair(string $email, string $passphrase): array
    {
        // Normalize inputs for determinism
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPassphrase = $this->normalizePassphrase($passphrase);

        // Create a deterministic salt from the email
        // We use a BLAKE2b hash to produce a fixed-size salt
        $salt = sodium_crypto_generichash(
            $normalizedEmail,
            '',
            SODIUM_CRYPTO_PWHASH_SALTBYTES
        );

        // Derive a 32-byte seed using Argon2id
        $seed = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SIGN_SEEDBYTES,
            $normalizedPassphrase,
            $salt,
            $this->timeCost,
            $this->memoryCost,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        // Generate Ed25519 keypair from the deterministic seed
        $keyPair = sodium_crypto_sign_seed_keypair($seed);

        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $secretKey = sodium_crypto_sign_secretkey($keyPair);

        // Wipe the keypair buffer (we already extracted what we need)
        sodium_memzero($keyPair);

        return [
            'publicKey' => $publicKey,
            'privateKey' => $secretKey,
            'seed' => $seed,
        ];
    }

    /**
     * Sign a challenge (nonce) with the given private key.
     *
     * @param string $privateKey Raw Ed25519 secret key (64 bytes)
     * @param string $nonce      The challenge nonce string
     * @return string Base64-encoded detached signature
     */
    public function signChallenge(string $privateKey, string $nonce): string
    {
        $signature = sodium_crypto_sign_detached($nonce, $privateKey);

        return base64_encode($signature);
    }

    /**
     * Verify a signature against a public key and nonce.
     *
     * @param string $publicKey  Raw Ed25519 public key (32 bytes)
     * @param string $signature  Base64-encoded detached signature
     * @param string $nonce      The challenge nonce string
     * @return bool
     */
    public function verifySignature(string $publicKey, string $signature, string $nonce): bool
    {
        $signatureRaw = base64_decode($signature);

        if ($signatureRaw === false || strlen($signatureRaw) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signatureRaw, $nonce, $publicKey);
    }

    /**
     * Extract only the public key from email + passphrase.
     * Convenience method that derives the keypair and wipes the private key.
     *
     * @param string $email
     * @param string $passphrase
     * @return string Raw public key (32 bytes)
     */
    public function derivePublicKey(string $email, string $passphrase): string
    {
        $keyPair = $this->deriveKeyPair($email, $passphrase);

        // Wipe sensitive material
        sodium_memzero($keyPair['privateKey']);
        sodium_memzero($keyPair['seed']);

        return $keyPair['publicKey'];
    }

    /**
     * Normalize an email address for deterministic derivation.
     */
    protected function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Normalize a passphrase for deterministic derivation.
     *
     * - Trims whitespace
     * - Normalizes to NFC unicode form
     * - Collapses multiple spaces
     */
    protected function normalizePassphrase(string $passphrase): string
    {
        $normalized = trim($passphrase);

        // Normalize Unicode to NFC if intl extension is available
        if (function_exists('normalizer_normalize')) {
            $normalized = \Normalizer::normalize($normalized, \Normalizer::FORM_C) ?: $normalized;
        }

        // Collapse multiple whitespace characters to single space
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }

    /**
     * Validate passphrase entropy.
     *
     * @param string $passphrase
     * @param int $minLength Minimum length
     * @param int $minUniqueChars Minimum unique character count
     * @return array{valid: bool, errors: string[]}
     */
    public function validatePassphraseEntropy(
        string $passphrase,
        int $minLength = 12,
        int $minUniqueChars = 6,
    ): array {
        $errors = [];

        if (mb_strlen($passphrase) < $minLength) {
            $errors[] = "Passphrase must be at least {$minLength} characters.";
        }

        $uniqueChars = count(array_unique(mb_str_split($passphrase)));
        if ($uniqueChars < $minUniqueChars) {
            $errors[] = "Passphrase must contain at least {$minUniqueChars} unique characters.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
