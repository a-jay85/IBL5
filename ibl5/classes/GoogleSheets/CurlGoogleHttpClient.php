<?php

declare(strict_types=1);

namespace GoogleSheets;

use GoogleSheets\Contracts\GoogleHttpClientInterface;

/**
 * Plain ext-curl implementation of the Google HTTP seam.
 */
final class CurlGoogleHttpClient implements GoogleHttpClientInterface
{
    /**
     * @param array<int, string> $headers
     * @return array{status: int, body: string}
     */
    public function request(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        $method = strtoupper($method);
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
        } elseif ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($response === false || $error !== '') {
            throw new GoogleHttpException('cURL error: ' . $error);
        }

        return [
            'status' => $status,
            'body' => is_string($response) ? $response : '',
        ];
    }
}
