<?php

namespace GlueAgency\Backoffice;

use RuntimeException;

/**
 * Sends a report as the Dashboard has always taken one: form-encoded, to
 * /actions/backoffice/report, with the install's token. It answers
 * `{"success":true}`, or a 400 with the reasons it refused, field by field.
 *
 * Through PHP's curl extension, which Akeneo requires, rather than an HTTP
 * library: each Akeneo release pins its own, Akeneo 5.0 an exact Guzzle 6.
 */
class Client
{
    private const TIMEOUT = 30;

    /**
     * Whether the Dashboard accepted the report, and every message it sent back.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: bool, 1: array<int, string>}
     *
     * @throws RuntimeException when the Dashboard cannot be reached
     */
    public function report(string $url, string $token, array $payload): array
    {
        [$status, $raw] = $this->post(rtrim($url, '/') . '/actions/backoffice/report', $token, http_build_query($payload, '', '&'));

        $body = json_decode($raw, true);

        if ($status >= 200 && $status < 300 && is_array($body) && ($body['success'] ?? false) === true) {
            return [true, []];
        }

        return [false, self::errors($status, $body, $raw)];
    }

    /**
     * Every message, prefixed with the field it is about; the response itself
     * when it holds none, as a proxy's error page would.
     *
     * @param  mixed  $body
     * @return array<int, string>
     */
    public static function errors(int $status, $body, string $raw): array
    {
        $messages = [];

        if (is_array($body) && isset($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $field => $fieldMessages) {
                foreach ((array) $fieldMessages as $message) {
                    $messages[] = is_string($field) ? $field . ': ' . $message : (string) $message;
                }
            }
        }

        if (! $messages && is_array($body) && isset($body['message']) && is_string($body['message'])) {
            $messages[] = $body['message'];
        }

        if (! $messages) {
            $messages[] = sprintf('Unexpected response (HTTP %d): %s', $status, mb_substr(trim($raw), 0, 200));
        }

        return $messages;
    }

    /**
     * The status and body the Dashboard answered with.
     *
     * @return array{0: int, 1: string}
     *
     * @throws RuntimeException when the Dashboard cannot be reached
     */
    protected function post(string $url, string $token, string $body): array
    {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        // A handle closes itself from PHP 8 on, and PHP 8.5 deprecates closing it.
        if (PHP_VERSION_ID < 80000) {
            curl_close($curl);
        }

        if (! is_string($response)) {
            throw new RuntimeException($error !== '' ? $error : 'No response from ' . $url);
        }

        return [$status, $response];
    }
}
