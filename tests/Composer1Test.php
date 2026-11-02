<?php

namespace GlueAgency\Backoffice\Tests;

use GlueAgency\Backoffice\InstallUid;
use GlueAgency\Backoffice\Report;
use GlueAgency\Backoffice\Tests\Fixtures\Composer1Packages;
use PHPUnit\Framework\TestCase;

/**
 * Brooklyn's PIM host deploys with Composer 1, which writes no
 * InstalledVersions: what it installed is read from its installed.json, a
 * plain list rather than Composer 2's `packages`.
 */
class Composer1Test extends TestCase
{
    public function test_a_pim_composer_1_installed_reports_akeneos_version_and_its_packages(): void
    {
        $uid = new InstallUid(sys_get_temp_dir() . '/glue-backoffice-unused/' . bin2hex(random_bytes(6)));
        $report = new Report(new Composer1Packages(__DIR__ . '/Fixtures/composer1'), $uid, 'https://brooklynpim.hypernode.io');

        $payload = $report->build(null, false);

        $this->assertSame(['5.0.75', 'CE'], [$payload['version'], $payload['edition']]);
        $this->assertSame([
            ['name' => 'akeneo/pim-community-dev', 'version' => 'v5.0.75', 'description' => 'Akeneo PIM, the future of catalog management is open!'],
            ['name' => 'aws/aws-sdk-php', 'version' => '3.172.0', 'description' => 'AWS SDK for PHP - Use Amazon Web Services in your PHP project'],
            ['name' => 'glue-agency/akeneo-glue-backoffice', 'version' => '1.0.1', 'description' => 'Reports an Akeneo PIM to the Glue Dashboard after each deploy'],
        ], $payload['packages']);
    }

    public function test_a_package_composer_1_did_not_install_is_not_installed(): void
    {
        $this->assertNull((new Composer1Packages(__DIR__ . '/Fixtures/composer1'))->installedVersion('akeneo/pim-enterprise-dev'));
    }
}
