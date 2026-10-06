<?php

namespace App\Tests\Builder;

use App\Account\Entity\LinkedAccount;
use App\Account\Entity\User;

final class UserBuilder
{
    private ?string $email = 'ada@example.org';
    private string $displayName = 'Ada';
    /** @var list<array{0: string, 1: string, 2: bool}> provider, provider user id, email verified */
    private array $connections = [];

    public static function aUser(): self
    {
        return new self();
    }

    public function withEmail(?string $email): self
    {
        $clone = clone $this;
        $clone->email = $email;

        return $clone;
    }

    public function named(string $displayName): self
    {
        $clone = clone $this;
        $clone->displayName = $displayName;

        return $clone;
    }

    public function linkedTo(string $provider, string $providerUserId, bool $emailVerified = true): self
    {
        $clone = clone $this;
        $clone->connections[] = [$provider, $providerUserId, $emailVerified];

        return $clone;
    }

    public function build(): User
    {
        $user = (new User())->setEmail($this->email)->setDisplayName($this->displayName);
        $connections = [] === $this->connections ? [['local', $this->email ?? 'ada', true]] : $this->connections;
        foreach ($connections as [$provider, $providerUserId, $emailVerified]) {
            $user->addLinkedAccount((new LinkedAccount())
                ->setProvider($provider)
                ->setProviderUserId($providerUserId)
                ->setEmail($this->email)
                ->setEmailVerified($emailVerified));
        }

        return $user;
    }
}
