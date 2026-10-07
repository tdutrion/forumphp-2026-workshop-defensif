<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A rejected API token gets the same application/problem+json body as every other API error.
 */
class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['type' => 'about:blank', 'title' => $this->translator->trans('http.401'), 'status' => 401, 'detail' => $this->translator->trans('error.token_invalid')],
            401,
            ['Content-Type' => 'application/problem+json', 'WWW-Authenticate' => 'Bearer error="invalid_token"'],
        );
    }
}
