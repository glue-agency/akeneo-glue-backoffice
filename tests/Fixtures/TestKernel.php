<?php

namespace GlueAgency\Backoffice\Tests\Fixtures;

use GlueAgency\Backoffice\GlueBackofficeBundle;
use GlueAgency\Backoffice\Packages;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * A PIM's kernel with nothing but this bundle in it, its console wired the way
 * FrameworkBundle wires a PIM's, and Akeneo installed as far as the bundle can
 * tell (FakePackages).
 */
class TestKernel extends Kernel
{
    /**
     * @var string
     */
    private $varDir;

    public function __construct(string $environment)
    {
        parent::__construct($environment, false);

        $this->varDir = sys_get_temp_dir() . '/glue-backoffice-kernel-' . bin2hex(random_bytes(6));
    }

    public function registerBundles(): iterable
    {
        return [new GlueBackofficeBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
        });
    }

    public function getProjectDir(): string
    {
        return __DIR__ . '/pim';
    }

    public function getCacheDir(): string
    {
        return $this->varDir . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->varDir . '/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddConsoleCommandPass(), PassConfig::TYPE_BEFORE_REMOVING);

        $container->addCompilerPass(new class implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition(Packages::class)->setClass(FakePackages::class);
            }
        });
    }
}
