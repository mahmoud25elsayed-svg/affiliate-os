<?php

declare(strict_types=1);

namespace App\Support\Auth;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random base32 TOTP secret (16-32 chars).
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        $alphabetLength = strlen(self::BASE32_ALPHABET);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $secret;
    }

    /**
     * Calculate 6-digit TOTP code for given secret and timestamp.
     */
    public function getOtp(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $timeSlice = (int) floor($timestamp / 30);

        $binaryKey = $this->base32Decode($secret);
        $binaryTime = pack('N*', 0).pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $binaryTime, $binaryKey, true);
        $offset = ord($hash[19]) & 0x0F;

        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binary % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit TOTP code against secret.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $currentTime = time();

        for ($i = -$window; $i <= $window; $i++) {
            $timestamp = $currentTime + ($i * 30);
            $expectedOtp = $this->getOtp($secret, $timestamp);

            if (hash_equals($expectedOtp, trim($code))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate standard otpauth URL for QR Code scanner apps.
     */
    public function getQrCodeUrl(string $issuer, string $accountName, string $secret): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Decode Base32 string to binary string.
     */
    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper(rtrim($b32, '='));
        $binaryString = '';

        $buffer = 0;
        $bufferSize = 0;

        $length = strlen($b32);
        for ($i = 0; $i < $length; $i++) {
            $pos = strpos(self::BASE32_ALPHABET, $b32[$i]);
            if ($pos === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $pos;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $binaryString .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }

        return $binaryString;
    }
}
