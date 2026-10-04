<?php

namespace App\Tests\Builder;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

/**
 * Builds a Mercure hub that records the published updates instead of sending them.
 */
final class HubBuilder
{
    private bool $failing = false;

    public static function aHub(): self
    {
        return new self();
    }

    public function thatFails(): self
    {
        $clone = clone $this;
        $clone->failing = true;

        return $clone;
    }

    /**
     * @param \ArrayObject<int, Update> $published receives every published update
     */
    public function build(\ArrayObject $published): HubInterface
    {
        $failing = $this->failing;

        return new MockHub(
            'https://localhost/.well-known/mercure',
            new StaticTokenProvider('test-token'),
            static function (Update $update) use ($failing, $published): string {
                if ($failing) {
                    throw new \RuntimeException('hub unreachable');
                }
                $published[] = $update;

                return 'urn:uuid:test';
            },
        );
    }
}
