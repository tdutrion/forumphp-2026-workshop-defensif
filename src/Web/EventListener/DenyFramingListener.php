<?php

declare(strict_types=1);

namespace App\Web\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Clickjacking protection: no page of the application can be shown in a frame. Set by the application
 * itself, so that it holds whatever serves it (Caddy, Apache on shared hosting, nginx...).
 * X-Frame-Options for older browsers, the frame-ancestors directive for the others; no other policy.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class DenyFramingListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        if (!$headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }
        $policy = (string) $headers->get('Content-Security-Policy');
        if ('' === $policy) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        } elseif (!str_contains($policy, 'frame-ancestors')) {
            $headers->set('Content-Security-Policy', rtrim($policy, '; ')."; frame-ancestors 'none'");
        }
    }
}
