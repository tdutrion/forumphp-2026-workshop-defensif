<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;

final class TwigStrictnessTest extends KernelTestCase
{
    public function testAVariableThatDoesNotExistIsAnErrorNotAnEmptyString(): void
    {
        // Arrange
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);

        // Assert
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Variable "filmm" does not exist');

        // Act
        $twig->createTemplate('{{ filmm.title }}')->render(['film' => ['title' => 'Cars']]);
    }
}
