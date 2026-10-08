<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
    // The scripts have no .php extension.
    ->append([__DIR__.'/bin/console', __DIR__.'/bin/phpunit'])
;

return (new PhpCsFixer\Config())
    // declare_strict_types is risky (it changes how scalars are coerced): allowed on purpose.
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder)
;
