<?php

declare(strict_types=1);

namespace Plugs\Security\OAuth;

class SocialUser
{
    public string $id;
    public string $nickname;
    public string $name;
    public string $email;
    public string $avatar; // Ensure this property exists
    public ?string $token;
    public array $user; // Raw user data

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function setRaw(array $user): self
    {
        $this->user = $user;

        return $this;
    }
}
