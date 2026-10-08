<?php

declare(strict_types=1);

namespace App\Api\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns API errors into application/problem+json, never exposing any technical detail.
 */
#[AsEventListener]
class ApiExceptionListener
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        // Title and detail in the language of the client (Accept-Language), like the validation errors.
        $title = $this->translator->trans('http.'.$status);
        if ('http.'.$status === $title) {
            $title = Response::$statusTexts[$status] ?? 'Error';
        }
        $problem = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
        ];
        $violations = $exception->getPrevious() instanceof ValidationFailedException ? $exception->getPrevious()->getViolations() : null;
        if (null !== $violations) {
            // #[MapQueryString] and #[MapRequestPayload]: one error per parameter, already translated by the validator.
            $problem['title'] = $this->translator->trans('api.invalid_parameters');
            $problem['errors'] = array_map(
                static fn (ConstraintViolationInterface $violation): array => ['field' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()],
                iterator_to_array($violations),
            );
        } elseif ($exception instanceof HttpExceptionInterface && '' !== $exception->getMessage()) {
            $problem['detail'] = $this->translator->trans($exception->getMessage());
        }

        // Keep the headers the error carries (Allow on a 405, WWW-Authenticate on a 401...).
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $event->setResponse(new JsonResponse($problem, $status, ['Content-Type' => 'application/problem+json'] + $headers));
    }
}
