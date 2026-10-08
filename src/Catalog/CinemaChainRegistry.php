<?php

declare(strict_types=1);

namespace App\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The cinema chains of config/packages/chains.yaml, built once: a country that does not exist or a time
 * zone with a typo stops the application at the first use of the registry, not in the middle of a synchronization.
 */
final class CinemaChainRegistry
{
    /** @var array<string, CinemaChain> */
    private array $chains = [];
    /** @var list<PlannedCinemaChain> */
    private array $planned = [];

    /**
     * @param array<string, array{name: string, country: string, timezone: string, language: string}> $chains
     * @param list<array{name: string, countries: list<string>}>                                      $planned
     *
     * @throws \InvalidArgumentException|\DateInvalidTimeZoneException on a chain that cannot exist
     */
    public function __construct(
        #[Autowire('%app.chains%')]
        array $chains,
        #[Autowire('%app.chains_planned%')]
        array $planned,
    ) {
        foreach ($chains as $id => $chain) {
            $this->chains[$id] = new CinemaChain($id, $chain['name'], new CountryCode($chain['country']), new \DateTimeZone($chain['timezone']), $chain['language']);
        }
        foreach ($planned as $chain) {
            $this->planned[] = new PlannedCinemaChain($chain['name'], array_map(static fn (string $code): CountryCode => new CountryCode($code), $chain['countries']));
        }
    }

    /**
     * @throws UnknownCinemaChain
     */
    public function get(string $id): CinemaChain
    {
        return $this->chains[$id] ?? throw new UnknownCinemaChain($id);
    }

    /**
     * @return list<CinemaChain> the chains the catalog is fed from
     */
    public function supported(): array
    {
        return array_values($this->chains);
    }

    /**
     * @return list<PlannedCinemaChain>
     */
    public function planned(): array
    {
        return $this->planned;
    }
}
