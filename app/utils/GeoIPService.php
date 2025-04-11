<?php

namespace App\Utils;

use App\Config\Services;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GeoIPService
{
    private const API_ENDPOINT = 'https://ipinfo.io/';
    private ?string $apiToken;
    private Client $httpClient;

    public function __construct(?Client $httpClient = null)
    {
        $this->apiToken = Services::getIpinfoToken();
        $this->httpClient = $httpClient ?? new Client();
    }

    /**
     * Looks up geolocation data for a given IP address.
     *
     * @param string $ipAddress The IP address to look up.
     * @return array|null An array containing geolocation data or null on failure.
     *                    Example successful return:
     *                    [
     *                        'ip' => '8.8.8.8',
     *                        'hostname' => 'dns.google',
     *                        'city' => 'Mountain View',
     *                        'region' => 'California',
     *                        'country' => 'US',
     *                        'loc' => '37.4056,-122.0775',
     *                        'org' => 'AS15169 Google LLC',
     *                        'postal' => '94043',
     *                        'timezone' => 'America/Los_Angeles'
     *                    ]
     */
    public function lookup(string $ipAddress): ?array
    {
        if (empty($this->apiToken)) {
            // Log error: Missing API Token
            error_log('GeoIPService Error: ipinfo.io API token is not configured.');
            return null;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            error_log("GeoIPService Error: Invalid IP address format '{$ipAddress}'.");
            return null;
        }

        // Avoid looking up private/reserved IP ranges
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
             error_log("GeoIPService Info: Skipping lookup for private/reserved IP address '{$ipAddress}'.");
            return null; // Or return default data if needed
        }

        $url = self::API_ENDPOINT . $ipAddress . '/json';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                ],
                'timeout' => 5, // Set a reasonable timeout
                 'connect_timeout' => 3,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                // Basic validation of expected fields
                if (isset($data['ip']) && isset($data['country'])) {
                    return $data;
                } else {
                    error_log("GeoIPService Error: Unexpected response format from ipinfo.io for IP '{$ipAddress}'.");
                    return null;
                }
            } else {
                // Log error: Non-200 status code
                 error_log("GeoIPService Error: Received status code " . $response->getStatusCode() . " from ipinfo.io for IP '{$ipAddress}'.");
                return null;
            }
        } catch (RequestException $e) {
            // Log error: Request failed
            error_log("GeoIPService Error: Failed to connect to ipinfo.io for IP '{$ipAddress}'. Error: " . $e->getMessage());
            return null;
        }
    }
} 