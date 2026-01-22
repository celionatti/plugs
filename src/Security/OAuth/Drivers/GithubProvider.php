<?php

declare(strict_types=1);

namespace Plugs\Security\OAuth\Drivers;

use Plugs\Security\OAuth\AbstractProvider;
use Plugs\Security\OAuth\SocialUser;

class GithubProvider extends AbstractProvider
{
    protected array $scopes = ['read:user', 'user:email'];

    protected function getAuthUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode($this->scopeSeparator, $this->scopes),
            'state' => $state,
        ]);

        return 'https://github.com/login/oauth/authorize?' . $query;
    }

    protected function getTokenUrl(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get('https://api.github.com/user', [
            'headers' => [
                'Authorization' => 'token ' . $token,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        $user = json_decode((string) $response->getBody(), true);

        // Fetch email if not public
        if (empty($user['email'])) {
            $user['email'] = $this->getEmailByToken($token);
        }

        return $user;
    }

    protected function getEmailByToken(string $token): ?string
    {
        $response = $this->getHttpClient()->get('https://api.github.com/user/emails', [
            'headers' => [
                'Authorization' => 'token ' . $token,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        $emails = json_decode((string) $response->getBody(), true);

        foreach ($emails as $email) {
            if ($email['primary'] && $email['verified']) {
                return $email['email'];
            }
        }

        return null;
    }

    protected function mapUserToObject(array $user): SocialUser
    {
        $socialUser = new SocialUser();
        $socialUser->id = (string) $user['id'];
        $socialUser->nickname = $user['login'];
        $socialUser->name = $user['name'] ?? $user['login'];
        $socialUser->email = $user['email'] ?? '';
        $socialUser->avatar = $user['avatar_url'];
        $socialUser->setRaw($user);

        return $socialUser;
    }
}
