<?php

namespace App\Tests\Integration\Catalog\Command;

use App\Tests\Builder\PatheApiBuilder;
use App\Tests\Fake\FakePatheApi;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncCommandTest extends KernelTestCase
{
    public function testReportsWhatWasSynchronized(): void
    {
        // Arrange
        $kernel = self::bootKernel();
        self::getContainer()->get(FakePatheApi::class)->serve(PatheApiBuilder::aPatheApi()
            ->withCity('dijon', 'Dijon')
            ->withCinema('cinema-pathe-dijon', 'dijon', 47.318031, 5.029935)
            ->withFilm('digger-51293', 'Digger', 129));
        $tester = new CommandTester((new Application($kernel))->find('catalog:sync'));

        // Act
        $tester->execute(['--city' => ['dijon']]);

        // Assert
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 cities, 1 cinemas, 1 films', $tester->getDisplay());
    }

    public function testStopsCleanlyWhenPatheBlocksUs(): void
    {
        // Arrange
        $kernel = self::bootKernel();
        self::getContainer()->get(FakePatheApi::class)->serve(PatheApiBuilder::aPatheApi()->failing('cities', 403, '{"error":"Error from IP 1.2.3.4"}'));
        $tester = new CommandTester((new Application($kernel))->find('catalog:sync'));

        // Act
        $tester->execute(['--city' => ['dijon']]);

        // Assert
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Pathé refused the request', $tester->getDisplay());
    }
}
