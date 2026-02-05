<?php
/**
 * Apple Maps Server API Geocoder
 *
 * Provides server-side geocoding using Apple Maps Server API.
 * This replaces unreliable client-side MapKit JS geocoding.
 *
 * Usage:
 *   $geocoder = new AppleMapsGeocoder();
 *   $result = $geocoder->geocode('1625 K Street NW, Washington DC 20006');
 *   if ($result['success']) {
 *       echo $result['latitude'] . ', ' . $result['longitude'];
 *   }
 *
 * @see https://developer.apple.com/documentation/applemapsserverapi/geocode_an_address
 */

class AppleMapsGeocoder {

    // Apple Maps Server API endpoints
    private const TOKEN_URL = 'https://maps-api.apple.com/v1/token';
    private const GEOCODE_URL = 'https://maps-api.apple.com/v1/geocode';

    // Credentials
    private $privateKeyPath;
    private $keyId;
    private $teamId;

    // Cached access token
    private static $accessToken = null;
    private static $tokenExpiry = 0;

    /**
     * Constructor - loads credentials from configuration
     */
    public function __construct() {
        // Configuration - update these paths/values as needed
        $this->privateKeyPath = __DIR__ . '/../Mapkit/AuthKey_W82U6J3RMG.p8';
        $this->keyId = 'W82U6J3RMG';
        $this->teamId = 'J8KMJSSRTH';

        if (!file_exists($this->privateKeyPath)) {
            throw new Exception("Apple Maps private key not found: " . $this->privateKeyPath);
        }
    }

    /**
     * Geocode an address to coordinates
     *
     * @param string $address Full address to geocode
     * @param string|null $fallbackZip ZIP code for fallback lookup
     * @return array Result with success, latitude, longitude, accuracy, etc.
     */
    public function geocode($address, $fallbackZip = null) {
        $address = trim($address);

        if (empty($address)) {
            return $this->errorResult('Empty address provided');
        }

        try {
            // Get or refresh access token
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                return $this->errorResult('Failed to obtain access token');
            }

            // Try geocoding the full address
            $result = $this->callGeocodeAPI($accessToken, $address);

            if ($result['success']) {
                // If we got CITY or REGION accuracy and have a ZIP, check if ZIP centroid is better
                if ($fallbackZip && in_array($result['accuracy'], ['CITY', 'REGION'])) {
                    $zipResult = $this->callGeocodeAPI($accessToken, $fallbackZip);
                    if ($zipResult['success']) {
                        // Use ZIP centroid instead - it's typically more geographically constrained
                        $zipResult['accuracy'] = 'ZIP_ONLY';
                        $zipResult['zipPreferred'] = true;
                        return $zipResult;
                    }
                }
                return $result;
            }

            // If full address failed and we have a zip, try simpler address formats
            if ($fallbackZip) {
                // Try extracting just street + city + state + zip
                $simpleAddress = $this->simplifyAddress($address, $fallbackZip);
                if ($simpleAddress !== $address) {
                    $result = $this->callGeocodeAPI($accessToken, $simpleAddress);
                    if ($result['success']) {
                        $result['simplified'] = true;
                        // Check if we still only got CITY/REGION - prefer ZIP if so
                        if (in_array($result['accuracy'], ['CITY', 'REGION'])) {
                            $zipResult = $this->callGeocodeAPI($accessToken, $fallbackZip);
                            if ($zipResult['success']) {
                                $zipResult['accuracy'] = 'ZIP_ONLY';
                                $zipResult['zipPreferred'] = true;
                                return $zipResult;
                            }
                        }
                        return $result;
                    }
                }

                // Last resort: geocode just the zip code
                $result = $this->callGeocodeAPI($accessToken, $fallbackZip);
                if ($result['success']) {
                    $result['accuracy'] = 'ZIP_ONLY';
                    $result['zipFallback'] = true;
                    return $result;
                }
            }

            return $this->errorResult('Geocoding failed for address: ' . $address);

        } catch (Exception $e) {
            error_log("AppleMapsGeocoder error: " . $e->getMessage());
            return $this->errorResult($e->getMessage());
        }
    }

    /**
     * Call the Apple Maps Geocode API
     */
    private function callGeocodeAPI($accessToken, $query) {
        $url = self::GEOCODE_URL . '?q=' . urlencode($query);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return $this->errorResult('Curl error: ' . $curlError);
        }

        if ($httpCode !== 200) {
            // Token might be expired, clear cache
            if ($httpCode === 401) {
                self::$accessToken = null;
                self::$tokenExpiry = 0;
            }
            return $this->errorResult('API returned HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);

        if (empty($data['results'])) {
            return $this->errorResult('No results found');
        }

        $result = $data['results'][0];

        // Determine accuracy from structuredAddress
        $accuracy = $this->determineAccuracy($result['structuredAddress'] ?? []);

        return [
            'success' => true,
            'latitude' => $result['coordinate']['latitude'],
            'longitude' => $result['coordinate']['longitude'],
            'accuracy' => $accuracy,
            'name' => $result['name'] ?? '',
            'formattedAddress' => implode(', ', $result['formattedAddressLines'] ?? []),
            'country' => $result['country'] ?? '',
            'countryCode' => $result['countryCode'] ?? '',
            'raw' => $result
        ];
    }

    /**
     * Get access token (cached for 25 minutes)
     */
    private function getAccessToken() {
        // Check if we have a valid cached token (with 5 min buffer)
        if (self::$accessToken && self::$tokenExpiry > time() + 300) {
            return self::$accessToken;
        }

        // Generate new JWT
        $jwt = $this->generateJWT();

        if (!$jwt) {
            return null;
        }

        // Exchange JWT for access token
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $jwt],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Apple Maps token request failed with HTTP " . $httpCode . ": " . $response);
            return null;
        }

        $data = json_decode($response, true);

        if (empty($data['accessToken'])) {
            error_log("Apple Maps token response missing accessToken: " . $response);
            return null;
        }

        // Cache the token
        self::$accessToken = $data['accessToken'];
        self::$tokenExpiry = time() + ($data['expiresInSeconds'] ?? 1800);

        return self::$accessToken;
    }

    /**
     * Generate JWT for Apple Maps Server API authentication
     */
    private function generateJWT() {
        $privateKey = file_get_contents($this->privateKeyPath);

        if (!$privateKey) {
            error_log("Could not read Apple Maps private key");
            return null;
        }

        // Create header
        $header = json_encode([
            'alg' => 'ES256',
            'kid' => $this->keyId,
            'typ' => 'JWT'
        ]);
        $headerB64 = $this->base64UrlEncode($header);

        // Create payload (no origin for server API)
        $payload = json_encode([
            'iss' => $this->teamId,
            'iat' => time(),
            'exp' => time() + 1800
        ]);
        $payloadB64 = $this->base64UrlEncode($payload);

        // Sign with ES256
        $dataToSign = $headerB64 . '.' . $payloadB64;
        $signature = '';
        $success = openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            error_log("JWT signing failed: " . openssl_error_string());
            return null;
        }

        $signatureB64 = $this->base64UrlEncode($signature);

        return $dataToSign . '.' . $signatureB64;
    }

    /**
     * Base64 URL encode (JWT-safe)
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Try to simplify an address that might have building names
     *
     * Examples:
     *   "LGBTQ* Center 230 West Side Dining, Stony Brook NY 11794"
     *   → "Stony Brook NY 11794"
     */
    private function simplifyAddress($address, $zip) {
        // If address contains city/state before zip, extract that portion
        // Pattern: City, ST ZIP or City ST ZIP
        if (preg_match('/([A-Za-z\s]+),?\s*([A-Z]{2})\s*' . preg_quote($zip) . '/i', $address, $matches)) {
            return trim($matches[1]) . ', ' . $matches[2] . ' ' . $zip;
        }

        // Just return city/state/zip if we can't parse
        return $zip;
    }

    /**
     * Determine geocoding accuracy from Apple Maps structuredAddress
     *
     * @param array $structuredAddress The structuredAddress from API response
     * @return string Accuracy level: ROOFTOP, STREET, CITY, REGION, or COUNTRY
     */
    private function determineAccuracy($structuredAddress) {
        // Most precise: has street number
        if (!empty($structuredAddress['subThoroughfare'])) {
            return 'ROOFTOP';
        }

        // Has street name but no number
        if (!empty($structuredAddress['thoroughfare'])) {
            return 'STREET';
        }

        // Has city/locality only
        if (!empty($structuredAddress['locality'])) {
            return 'CITY';
        }

        // Has state/region only
        if (!empty($structuredAddress['administrativeArea'])) {
            return 'REGION';
        }

        // Country only or unknown
        return 'COUNTRY';
    }

    /**
     * Create error result array
     */
    private function errorResult($message) {
        return [
            'success' => false,
            'error' => $message,
            'latitude' => null,
            'longitude' => null,
            'accuracy' => null
        ];
    }

    /**
     * Calculate x, y, z coordinates for distance calculations
     *
     * @param float $latitude
     * @param float $longitude
     * @return array ['x' => float, 'y' => float, 'z' => float]
     */
    public static function calculateXYZ($latitude, $longitude) {
        $latRad = deg2rad($latitude);
        $lonRad = deg2rad($longitude);

        return [
            'x' => cos($lonRad) * cos($latRad),
            'y' => sin($lonRad) * cos($latRad),
            'z' => sin($latRad)
        ];
    }
}
