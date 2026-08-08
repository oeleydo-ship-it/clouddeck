<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Validates uploaded PEM material before it is stored or shipped to a managed host.
 */
final class CustomSslValidator
{
    /**
     * @return array{fullchain: string, private_key: string, expires_at: Carbon, domains: list<string>}
     */
    public function validate(string $fullchain, string $privateKey): array
    {
        $fullchain = trim($fullchain);
        $privateKey = trim($privateKey);

        if (! str_contains($fullchain, 'BEGIN CERTIFICATE')) {
            throw ValidationException::withMessages(['fullchain' => 'Upload a PEM certificate (full chain).']);
        }
        if (! preg_match('/BEGIN (?:RSA |EC )?PRIVATE KEY/', $privateKey)) {
            throw ValidationException::withMessages(['private_key' => 'Upload a PEM private key.']);
        }

        $cert = @openssl_x509_read($fullchain);
        if ($cert === false) {
            throw ValidationException::withMessages(['fullchain' => 'The certificate PEM could not be parsed.']);
        }

        $key = @openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw ValidationException::withMessages(['private_key' => 'The private key PEM could not be parsed.']);
        }

        if (! @openssl_x509_check_private_key($cert, $key)) {
            throw ValidationException::withMessages([
                'private_key' => 'The private key does not match the certificate.',
            ]);
        }

        $parsed = @openssl_x509_parse($cert);
        if (! is_array($parsed) || empty($parsed['validTo_time_t'])) {
            throw ValidationException::withMessages(['fullchain' => 'Unable to read certificate expiry.']);
        }

        $expiresAt = Carbon::createFromTimestamp((int) $parsed['validTo_time_t']);
        if ($expiresAt->isPast()) {
            throw ValidationException::withMessages(['fullchain' => 'This certificate has already expired.']);
        }

        return [
            'fullchain' => $fullchain,
            'private_key' => $privateKey,
            'expires_at' => $expiresAt,
            'domains' => $this->domainsFromCertificate($parsed),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<string>
     */
    private function domainsFromCertificate(array $parsed): array
    {
        $domains = [];
        $cn = data_get($parsed, 'subject.CN');
        if (is_string($cn) && $cn !== '') {
            $domains[] = $cn;
        }

        $san = data_get($parsed, 'extensions.subjectAltName');
        if (is_string($san)) {
            foreach (explode(',', $san) as $entry) {
                $entry = trim($entry);
                if (str_starts_with($entry, 'DNS:')) {
                    $domains[] = substr($entry, 4);
                }
            }
        }

        return array_values(array_unique($domains));
    }
}
