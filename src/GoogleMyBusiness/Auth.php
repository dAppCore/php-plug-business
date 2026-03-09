<?php

declare(strict_types=1);

namespace Core\Plug\Business\GoogleMyBusiness;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Contract\Refreshable;
use Core\Plug\Response;

/**
 * Google My Business OAuth 2.0 authentication.
 */
class Auth implements Authenticable, Refreshable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private string $clientId;

    private string $clientSecret;

    private string $redirectUrl;

    private array $scope = [
        'https://www.googleapis.com/auth/business.manage',
    ];

    public function __construct(string $clientId = '', string $clientSecret = '', string $redirectUrl = '')
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl = $redirectUrl;
    }

    public static function identifier(): string
    {
        return 'googlemybusiness';
    }

    public static function name(): string
    {
        return 'Google My Business';
    }

    /**
     * Get OAuth URL.
     */
    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode(' ', $this->scope),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ];

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    /**
     * Exchange code for access token.
     *
     * @param  array  $params  ['code' => string]
     */
    public function requestAccessToken(array $params): array
    {
        if (! isset($params['code'])) {
            return ['error' => 'Authorization code is required'];
        }

        $response = $this->http()
            ->asForm()
            ->post(self::TOKEN_URL, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUrl,
                'grant_type' => 'authorization_code',
                'code' => $params['code'],
            ]);

        if (! $response->successful()) {
            $error = $response->json();

            return ['error' => $error['error_description'] ?? $error['error'] ?? 'Token exchange failed'];
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_in' => now('UTC')->addSeconds($data['expires_in'] ?? 3600)->timestamp,
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => $data['scope'] ?? '',
        ];
    }

    /**
     * Refresh the access token.
     */
    public function refresh(): Response
    {
        $refreshToken = $this->token['refresh_token'] ?? null;

        if (! $refreshToken) {
            return $this->error('No refresh token available');
        }

        $response = $this->http()
            ->asForm()
            ->post(self::TOKEN_URL, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        return $this->fromHttp($response, function ($data) use ($refreshToken) {
            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $refreshToken, // Google doesn't return new refresh token
                'expires_in' => now('UTC')->addSeconds($data['expires_in'] ?? 3600)->timestamp,
                'token_type' => $data['token_type'] ?? 'Bearer',
            ];
        });
    }

    public function getAccount(): Response
    {
        return (new Read)->withToken($this->token)->me();
    }

    /**
     * Google Business Profile URL.
     */
    public static function externalAccountUrl(string $placeId): string
    {
        if ($placeId) {
            return "https://www.google.com/maps/place/?q=place_id:{$placeId}";
        }

        return 'https://business.google.com';
    }
}
