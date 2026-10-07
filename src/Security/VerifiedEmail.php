<?php

namespace App\Security;

/**
 * An email that the provider guarantees the user owns: the only one an account may be found or linked by.
 */
final readonly class VerifiedEmail extends Email
{
    #[\NoDiscard]
    public function unverified(): UnverifiedEmail
    {
        return new UnverifiedEmail($this->value);
    }
}
