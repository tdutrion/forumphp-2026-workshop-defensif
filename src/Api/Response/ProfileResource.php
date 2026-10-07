<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Account\Entity\User;

final readonly class ProfileResource
{
    /**
     * @param list<string> $providers the providers the user signs in with
     */
    public function __construct(
        public string $id,
        public ?string $displayName,
        public ?string $email,
        public array $providers,
    ) {
    }

    public static function fromUser(User $user): self
    {
        $providers = [];
        foreach ($user->getLinkedAccounts() as $linkedAccount) {
            $providers[] = $linkedAccount->getProvider();
        }

        return new self($user->getUserIdentifier(), $user->getDisplayName(), $user->getEmail(), $providers);
    }
}
