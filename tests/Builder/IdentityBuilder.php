<?php

namespace App\Tests\Builder;

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

    /**
     * @return array ['id', 'email', 'emailVerified', 'name']
     */
    public function build(): array
    {
        return ['id' => $this->id, 'email' => $this->email, 'emailVerified' => $this->emailVerified, 'name' => $this->name];
    }
}
