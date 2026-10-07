<?php

declare(strict_types=1);

namespace App\Security;

/**
 * An email that the provider does not vouch for: it is displayed, it proves nothing.
 */
final readonly class UnverifiedEmail extends Email
{
}
