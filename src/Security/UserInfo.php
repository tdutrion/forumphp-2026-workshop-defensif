<?php

namespace App\Security;

/**
 * Who a provider says the user is, in the same format whatever the provider.
 */
final readonly class UserInfo
{
    /**
     * @param string      $id            identifier at the provider
     * @param string|null $email         null when the provider gives none
     * @param bool        $emailVerified whether the provider guarantees that the user owns the email
     */
    public function __construct(
        public string $id,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('A provider identifies its users.');
        }
        if ($emailVerified && null === $email) {
            throw new \InvalidArgumentException('There is no email to verify.');
        }
    }

    /**
     * The same identity, whose email proves nothing: for a provider that accepts any name typed by anyone.
     */
    #[\NoDiscard('UserInfo is immutable: withUnverifiedEmail() returns the identity with an unverified email.')]
    public function withUnverifiedEmail(): self
    {
        return clone ($this, ['emailVerified' => false]);
    }
}
