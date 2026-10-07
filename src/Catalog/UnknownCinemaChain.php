<?php

declare(strict_types=1);

namespace App\Catalog;

final class UnknownCinemaChain extends \LogicException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('"%s" is not a cinema chain of config/packages/chains.yaml.', $id));
    }
}
