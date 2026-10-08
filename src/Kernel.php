<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        // UX Turbo 3.x still registers the deprecated turbo_stream_listen() renderers (removed in 4.0)
        // next to the turbo_stream_from() ones. The application only uses turbo_stream_from():
        // dropping them keeps the deprecation log clean. Runs after the bundle's own pass.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach (array_keys($container->findTaggedServiceIds('turbo.renderer.stream_listen')) as $id) {
                    $container->removeDefinition($id);
                }
            }
        });
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
