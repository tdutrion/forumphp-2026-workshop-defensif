<?php

declare(strict_types=1);

namespace App\Security;

use App\Account\ApiTokenService;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authenticates API calls with a personal token (Authorization: Bearer header).
 */
class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private ApiTokenService $apiTokenService)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $user = $this->apiTokenService->findUserByToken($accessToken);
        if (null === $user) {
            throw new BadCredentialsException('error.token_invalid');
        }

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
