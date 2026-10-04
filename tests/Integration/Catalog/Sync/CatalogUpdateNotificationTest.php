<?php

namespace App\Tests\Integration\Catalog\Sync;

use App\Catalog\Sync\CatalogSynchronizer;
use App\Catalog\Sync\CatalogSyncRunner;
use App\Catalog\Sync\CatalogUpdatePublisher;
use App\Tests\Builder\HubBuilder;
use App\Tests\Builder\PatheApiBuilder;
use App\Tests\Fake\FakePatheApi;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Twig\Environment;

final class CatalogUpdateNotificationTest extends KernelTestCase
{
    private function runnerPublishingTo(HubInterface $hub): CatalogSyncRunner
    {
        $container = self::getContainer();
        $container->get(FakePatheApi::class)->serve(PatheApiBuilder::aPatheApi()
            ->withCity('dijon', 'Dijon')
            ->withCinema('cinema-pathe-dijon', 'dijon', 47.318031, 5.029935)
            ->withFilm('digger-51293', 'Digger', 129));
        $publisher = new CatalogUpdatePublisher($container->get(Environment::class), new NullLogger(), ['en', 'fr'], $hub);

        return new CatalogSyncRunner($container->get(CatalogSynchronizer::class), $publisher, 'dijon');
    }

    public function testASyncTellsOpenPagesInTheirLanguage(): void
    {
        // Arrange
        self::bootKernel();
        $published = new \ArrayObject();
        $runner = $this->runnerPublishingTo(HubBuilder::aHub()->build($published));

        // Act
        $runner->run();

        // Assert
        $byTopic = [];
        foreach ($published as $update) {
            $byTopic[$update->getTopics()[0]] = $update->getData();
        }
        self::assertSame(['catalog/en', 'catalog/fr'], array_keys($byTopic));
        self::assertStringContainsString('target="catalog-status"', $byTopic['catalog/en']);
        self::assertStringContainsString('Programme updated', $byTopic['catalog/en']);
        self::assertStringContainsString('Programmation mise à jour', $byTopic['catalog/fr']);
    }

    public function testAnUnreachableHubDoesNotFailTheSync(): void
    {
        // Arrange
        self::bootKernel();
        $runner = $this->runnerPublishingTo(HubBuilder::aHub()->thatFails()->build(new \ArrayObject()));

        // Act
        $stats = $runner->run();

        // Assert
        self::assertSame(1, $stats['cities']);
        self::assertSame(0, $stats['errors']);
    }
}
