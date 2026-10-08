<?php

declare(strict_types=1);

namespace App\Catalog\Sync;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

/**
 * Tells the open pages that the programme was just updated (Turbo Stream through Mercure),
 * once per language: a page listens to the topic of its own locale.
 */
class CatalogUpdatePublisher
{
    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
        #[Autowire('%kernel.enabled_locales%')]
        private array $locales,
        private ?HubInterface $hub = null,
    ) {
    }

    public function publish(array $stats): void
    {
        if (null === $this->hub) {
            return;
        }

        try {
            foreach ($this->locales as $locale) {
                $html = $this->twig->render('catalog/updated.stream.html.twig', ['stats' => $stats, 'locale' => $locale]);
                $this->hub->publish(new Update('catalog/'.$locale, $html));
            }
        } catch (\Throwable $e) {
            // The notification is a bonus: its failure must not fail the synchronization.
            $this->logger->warning('Mercure notification failed', ['error' => $e->getMessage()]);
        }
    }
}
