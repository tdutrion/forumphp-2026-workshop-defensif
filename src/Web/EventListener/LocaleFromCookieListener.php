<?php

declare(strict_types=1);

namespace App\Web\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The language chosen with the switch of the top menu (cookie) wins over the Accept-Language header.
 * Runs after the router (32) and before the LocaleListener (16), which honours "_locale".
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final class LocaleFromCookieListener
{
    public const string COOKIE = 'locale';

    public function __construct(#[Autowire('%kernel.enabled_locales%')] private array $locales)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->cookies->getString(self::COOKIE);
        if (!$event->isMainRequest() || $request->attributes->has('_locale') || !in_array($locale, $this->locales, true)) {
            return;
        }
        $request->attributes->set('_locale', $locale);
    }
}
