<?php

declare(strict_types=1);

namespace Plugs\Payment\Traits;

use Plugs\Payment\Exceptions\GatewayException;

trait HasHttpCalls
{
    /**
     * Make a request to the external API.
     *
     * @param string $url
     * @param array $data
     * @param string $method
     * @param array $headers
     * @return array
     * @throws GatewayException
     */
    protected function makeHttpRequest(string $url, array $data = [], string $method = 'POST', array $headers = []): array
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $contentType = $this->getHeaderValue($headers, 'Content-Type');

            if (str_contains((string) $contentType, 'application/json')) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new GatewayException("cURL Error: " . $curlError, 0);
        }

        $result = json_decode((string) $response, true) ?? [];

        if ($httpCode >= 400) {
            $message = $result['message'] ?? $result['error']['message'] ?? $result['error'] ?? 'API Request failed';
            throw new GatewayException($message, $httpCode, $result);
        }

        return $result;
    }

    /**
     * Get a specific header value from an array of headers.
     *
     * @param array $headers
     * @param string $name
     * @return string|null
     */
    private function getHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (stripos($header, $name . ':') === 0) {
                return trim(substr($header, strlen($name) + 1));
            }
        }
        return null;
    }
}
