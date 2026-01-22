<?php

declare(strict_types=1);

namespace Plugs\Security\OAuth\Drivers;

use Plugs\Security\OAuth\AbstractProvider;
use Plugs\Security\OAuth\SocialUser;

class GoogleProvider extends AbstractProvider
{
    protected array $scopes = ['openid', 'profile', 'email'];
    protected string $scopeSeparator = ' ';

    protected function getAuthUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode($this->scopeSeparator, $this->scopes),
            'state' => $state,
            'response_type' => 'code',
            'access_type' => 'offline',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
    }

    protected function getTokenUrl(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): SocialUser
    {
        $socialUser = new SocialUser();
        $socialUser->id = (string) $user['sub'];
        $socialUser->nickname = $user['name'];
        $socialUser->name = $user['name'];
        $socialUser->email = $user['email'];
        $socialUser->avatar = $user['picture'] ?? '';
        $socialUser->setRaw($user);

        return $socialUser;
    }
}
