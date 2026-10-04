<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * A rejected API token gets the same application/problem+json body as every other API error.
 */
class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => 401, 'detail' => 'Invalid, expired or revoked token.'],
            401,
            ['Content-Type' => 'application/problem+json', 'WWW-Authenticate' => 'Bearer error="invalid_token"'],
        );
    }
}
