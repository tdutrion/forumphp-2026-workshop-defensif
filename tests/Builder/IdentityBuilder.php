<?php

namespace App\Tests\Builder;

use App\Security\UserInfo;

final class IdentityBuilder
{
    private string $id = '42';
    private ?string $email = 'ada@example.org';
    private bool $emailVerified = true;
    private string $name = 'Ada';

    public static function anIdentity(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function withEmail(?string $email): self
    {
        $clone = clone $this;
        $clone->email = $email;

        return $clone;
    }

    public function unverified(): self
    {
        $clone = clone $this;
        $clone->emailVerified = false;

        return $clone;
    }

    public function build(): UserInfo
    {
        return new UserInfo($this->id, $this->email, $this->emailVerified && null !== $this->email, $this->name);
    }
}
