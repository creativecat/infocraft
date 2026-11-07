<?php
declare(strict_types=1);

namespace Infocraft;

use RuntimeException;

final class SsoM365
{
    public static function enabled(): bool
    {
        return (bool) Config::get('sso.enabled')
            && (string) Config::get('sso.tenant_id', '') !== ''
            && (string) Config::get('sso.client_id', '') !== '';
    }

    public static function authorizeUrl(string $state): string
    {
        return self::baseUrl() . '/authorize?' . http_build_query([
            'client_id' => (string) Config::get('sso.client_id'),
            'response_type' => 'code',
            'redirect_uri' => (string) Config::get('sso.redirect_uri'),
            'scope' => 'openid profile',
            'state' => $state,
            'response_mode' => 'query',
        ]);
    }

    /** @return array<string, mixed> */
    public static function handleCallback(string $code): array
    {
        $response = self::httpPost(self::baseUrl() . '/token', [
            'client_id' => (string) Config::get('sso.client_id'),
            'client_secret' => (string) Config::get('sso.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => (string) Config::get('sso.redirect_uri'),
        ]);
        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['id_token']) || !is_string($data['id_token'])) {
            throw new RuntimeException('Die Token-Antwort von Microsoft war ungültig.');
        }
        return self::decodeIdToken($data['id_token']);
    }

    /** @return array<string, mixed> */
    private static function decodeIdToken(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            throw new RuntimeException('Das ID-Token hat ein unerwartetes Format.');
        }
        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $claims = json_decode((string) base64_decode($payload, true), true);
        if (!is_array($claims)) {
            throw new RuntimeException('Das ID-Token konnte nicht gelesen werden.');
        }
        return $claims;
    }

    /** @param array<string, string> $data */
    private static function httpPost(string $url, array $data): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Die PHP-cURL-Erweiterung wird für SSO benötigt.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
        ]);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($result === false) {
            throw new RuntimeException('Verbindung zu Microsoft fehlgeschlagen: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('Microsoft hat die Anfrage mit HTTP ' . $status . ' abgelehnt. Konfiguration prüfen (docs/sso-m365.md).');
        }
        return (string) $result;
    }

    private static function baseUrl(): string
    {
        return 'https://login.microsoftonline.com/' . (string) Config::get('sso.tenant_id') . '/oauth2/v2.0';
    }
}
