<?php

namespace App\Security;

/**
 * Who a provider says the user is, in the same format whatever the provider.
 */
final readonly class UserInfo
{
    /**
     * @param string                             $id    identifier at the provider
     * @param VerifiedEmail|UnverifiedEmail|null $email null when the provider gives none
     */
    public function __construct(
        public string $id,
        public VerifiedEmail|UnverifiedEmail|null $email,
        public ?string $name,
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('A provider identifies its users.');
        }
    }

    /**
     * The same identity, whose email proves nothing: for a provider that accepts any name typed by anyone.
     */
    #[\NoDiscard('UserInfo is immutable: withUnverifiedEmail() returns the identity with an unverified email.')]
    public function withUnverifiedEmail(): self
    {
        return clone ($this, ['email' => $this->email instanceof VerifiedEmail ? $this->email->unverified() : $this->email]);
    }
}
