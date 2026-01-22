<?php

declare(strict_types=1);

namespace Plugs\Security\OAuth;

use GuzzleHttp\Client;
use RuntimeException;

abstract class AbstractProvider
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUrl;
    protected array $scopes = [];
    protected string $scopeSeparator = ',';
    protected array $parameters = [];
    protected $httpClient;

    public function __construct(string $clientId, string $clientSecret, string $redirectUrl)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl = $redirectUrl;
    }

    abstract protected function getAuthUrl(string $state): string;
    abstract protected function getTokenUrl(): string;
    abstract protected function getUserByToken(string $token): array;
    abstract protected function mapUserToObject(array $user): SocialUser;

    public function redirect(): void
    {
        $state = bin2hex(random_bytes(16));
        // In a real app, store state in session to verify later
        // Session::put('oauth_state', $state); 

        $url = $this->getAuthUrl($state);
        header('Location: ' . $url);
        exit;
    }

    public function user(): SocialUser
    {
        if (isset($_GET['error'])) {
            throw new RuntimeException("OAuth Error: " . $_GET['error']);
        }

        if (!isset($_GET['code'])) {
            throw new RuntimeException("No authorization code found.");
        }

        // Validate state here if strictly implementing OAuth2

        $token = $this->getAccessToken($_GET['code']);
        $user = $this->getUserByToken($token);

        return $this->mapUserToObject($user)->setToken($token);
    }

    protected function getAccessToken(string $code): string
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => $this->getTokenFields($code),
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['error'])) {
            throw new RuntimeException("Failed to get access token: " . ($data['error_description'] ?? $data['error']));
        }

        return $data['access_token'];
    }

    protected function getTokenFields(string $code): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
            'grant_type' => 'authorization_code',
        ];
    }

    public function scopes(array $scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }

    public function with(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    protected function getHttpClient(): Client
    {
        if (!$this->httpClient) {
            $this->httpClient = new Client();
        }

        return $this->httpClient;
    }
}
