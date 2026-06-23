<?php

namespace App\Support\Traccar;

/**
 * Matches org.traccar.helper.Hashing (PBKDF2-HMAC-SHA1).
 *
 * @see https://github.com/traccar/traccar/blob/master/src/main/java/org/traccar/helper/Hashing.java
 */
final class TraccarPassword
{
    public const ITERATIONS = 1000;

    public const SALT_SIZE = 24;

    public const HASH_SIZE = 24;

    /**
     * @return array{hash: string, salt: string} Uppercase hex (Traccar convention).
     */
    public static function createHash(string $plainPassword): array
    {
        $salt = random_bytes(self::SALT_SIZE);
        $hash = hash_pbkdf2(
            'sha1',
            $plainPassword,
            $salt,
            self::ITERATIONS,
            self::HASH_SIZE,
            true
        );

        return [
            'hash' => strtoupper(bin2hex($hash)),
            'salt' => strtoupper(bin2hex($salt)),
        ];
    }

    public static function validate(string $plainPassword, string $hashHex, string $saltHex): bool
    {
        $hash = @hex2bin($hashHex);
        $salt = @hex2bin($saltHex);

        if ($hash === false || $salt === false || strlen($hash) !== self::HASH_SIZE) {
            return false;
        }

        $derived = hash_pbkdf2(
            'sha1',
            $plainPassword,
            $salt,
            self::ITERATIONS,
            self::HASH_SIZE,
            true
        );

        return hash_equals($hash, $derived);
    }

    /**
     * Laravel bcrypt ($2y$...) cannot be converted to Traccar hex PBKDF2.
     */
    public static function looksLikeLaravelBcrypt(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, '$2y$');
    }

    /**
     * Traccar stores 48-char hex for 24-byte hash/salt.
     */
    public static function looksLikeTraccarHex(?string $value): bool
    {
        return is_string($value)
            && strlen($value) === self::HASH_SIZE * 2
            && ctype_xdigit($value);
    }
}
