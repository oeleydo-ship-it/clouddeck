<?php

namespace App\Billing\Stripe;

final class StripeWebhookVerifier
{
    public function valid(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key && $value) {
                $parts[$key][] = $value;
            }
        }
        $timestamp = (int) data_get($parts, 't.0');
        $signatures = (array) data_get($parts, 'v1', []);
        if (! $timestamp || abs(time() - $timestamp) > $tolerance || $signatures === []) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return collect($signatures)->contains(fn (string $candidate) => hash_equals($expected, $candidate));
    }
}
