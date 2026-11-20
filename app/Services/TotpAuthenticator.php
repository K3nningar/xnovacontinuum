<?php

namespace App\Services;

class TotpAuthenticator
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $bytes = random_bytes((int) ceil($length * 5 / 8));
        $encoded = '';
        $buffer = 0;
        $bits = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;

            while ($bits >= 5 && strlen($encoded) < $length) {
                $bits -= 5;
                $encoded .= self::BASE32[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $encoded;
    }

    public function currentCounter(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? now()->timestamp, 30);
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->codeAt($secret, $this->currentCounter($timestamp));
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? now()->timestamp, 30);

        return hash_equals($this->codeAt($secret, $counter), $code);
    }

    public function provisioningUri(string $secret, string $email): string
    {
        $label = rawurlencode('XNOVA:'.$email);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => 'XNOVA',
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    private function codeAt(string $secret, int $counter): string
    {
        $key = $this->decodeBase32($secret);
        $message = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $buffer = 0;
        $bits = 0;
        $decoded = '';

        foreach (str_split($secret) as $character) {
            $value = strpos(self::BASE32, $character);
            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $decoded;
    }
}
