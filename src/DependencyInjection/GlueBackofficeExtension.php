<?php

namespace GlueAgency\Backoffice\DependencyInjection;

use GlueAgency\Backoffice\Client;
use GlueAgency\Backoffice\Command\ReportCommand;
use GlueAgency\Backoffice\InstallUid;
use GlueAgency\Backoffice\Packages;
use GlueAgency\Backoffice\Report;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The reporter's services, written out here rather than in a config file so
 * the bundle needs nothing the PIM may not have.
 *
 * Every value comes from the environment, and none has to be set: an unset
 * variable reads as null (`default::`), so a host without the token compiles
 * its container as before and reports nothing. That matters, because the
 * container is the whole PIM: a value it cannot resolve takes the PIM down
 * with it.
 */
class GlueBackofficeExtension extends Extension
{
    /**
     * Where the uid is kept: Akeneo's file storage, which every deploy shares
     * between releases because the catalog's files live there.
     */
    public const UID_PATH = '%kernel.project_dir%/var/file_storage/glue-backoffice-uid';

    /**
     * @param  array<int, array<string, mixed>>  $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register(Packages::class, Packages::class)
            ->setArguments(['%kernel.project_dir%'])
            ->setPublic(false);

        $container->register(InstallUid::class, InstallUid::class)
            ->setArguments([self::UID_PATH])
            ->setPublic(false);

        $container->register(Report::class, Report::class)
            ->setArguments([
                new Reference(Packages::class),
                new Reference(InstallUid::class),
                '%env(default::AKENEO_PIM_URL)%',
            ])
            ->setPublic(false);

        $container->register(Client::class, Client::class)
            ->setPublic(false);

        $container->register(ReportCommand::class, ReportCommand::class)
            ->setArguments([
                new Reference(Report::class),
                new Reference(Client::class),
                '%kernel.environment%',
                '%env(default::GLUE_BACKOFFICE_TOKEN)%',
                '%env(default::GLUE_BACKOFFICE_URL)%',
            ])
            ->addTag('console.command', ['command' => ReportCommand::NAME])
            ->setPublic(false);
    }
}
