<?php

namespace GlueAgency\Backoffice\Tests;

use Composer\InstalledVersions;
use GlueAgency\Backoffice\InstallUid;
use GlueAgency\Backoffice\Report;
use GlueAgency\Backoffice\Tests\Fixtures\FakePackages;
use PHPUnit\Framework\TestCase;

/**
 * The report the Dashboard keeps as an install's Akeneo section.
 */
class ReportTest extends TestCase
{
    public function test_it_reports_akeneos_version_as_composer_installed_it_without_the_v(): void
    {
        $payload = $this->report(['akeneo/pim-community-dev' => 'v5.0.75'])->build(null, false);

        $this->assertSame('akeneo', $payload['platform']);
        $this->assertSame('5.0.75', $payload['version']);
        $this->assertSame('CE', $payload['edition']);
        $this->assertSame(PHP_VERSION, $payload['phpVersion']);
    }

    /**
     * Akeneo's own version.txt read 7.0.85 on 2026.4: Composer's version is the
     * one that names the release.
     */
    public function test_a_calendar_release_reads_as_its_tag(): void
    {
        $this->assertSame('2026.4', $this->report(['akeneo/pim-community-dev' => 'v2026.4'])->build(null, false)['version']);
    }

    public function test_an_enterprise_pim_reports_the_enterprise_edition_and_its_version(): void
    {
        $payload = $this->report([
            'akeneo/pim-enterprise-dev' => 'v7.0.70',
            'akeneo/pim-community-dev'  => 'v7.0.88',
        ])->build(null, false);

        $this->assertSame(['7.0.70', 'EE'], [$payload['version'], $payload['edition']]);
    }

    public function test_the_packages_are_the_ones_the_pim_requires_directly(): void
    {
        $packages = $this->report(['akeneo/pim-community-dev' => 'v7.0.88'])->build(null, false)['packages'];

        $this->assertSame(['akeneo/pim-community-dev', 'symfony/console', 'glue-agency/not-installed'], array_column($packages, 'name'));
        $this->assertSame('v7.0.88', $packages[0]['version']);
        $this->assertSame(InstalledVersions::getPrettyVersion('symfony/console'), $packages[1]['version']);
        $this->assertNotSame('', $packages[1]['description']);
        $this->assertSame(['name' => 'glue-agency/not-installed', 'version' => 'dev', 'description' => ''], $packages[2]);
    }

    public function test_the_title_is_the_host_the_pim_is_served_on(): void
    {
        $this->assertSame('woodypim.hypernode.io', Report::title('https://woodypim.hypernode.io/'));
        $this->assertSame('pim.brooklyn.be', Report::title(' http://pim.brooklyn.be:8080/user/login '));
    }

    /**
     * Akeneo's `.env` ships http://localhost:8080, which says nothing about
     * which PIM reported.
     */
    public function test_a_pim_without_a_url_of_its_own_is_named_after_the_machine(): void
    {
        $machine = gethostname() ?: 'Akeneo PIM';

        $this->assertSame($machine, Report::title('http://localhost:8080'));
        $this->assertSame($machine, Report::title(null));
        $this->assertSame($machine, Report::title('not a url'));
    }

    public function test_a_repository_is_only_sent_when_given(): void
    {
        $report = $this->report(['akeneo/pim-community-dev' => 'v7.0.88']);

        $this->assertSame(['name' => 'git@bitbucket.org:glue-team/woody_pimup.git'], $report->build('git@bitbucket.org:glue-team/woody_pimup.git', false)['repository']);
        $this->assertArrayNotHasKey('repository', $report->build('', false));
    }

    public function test_every_value_is_cut_to_what_the_dashboard_keeps(): void
    {
        $payload = $this->report(['akeneo/pim-community-dev' => 'v7.0.88'])->build(str_repeat('r', 300), false);

        $this->assertSame(255, mb_strlen($payload['repository']['name']));
    }

    /**
     * @param  array<string, string>  $installed
     */
    private function report(array $installed): Report
    {
        $uid = new InstallUid(sys_get_temp_dir() . '/glue-backoffice-unused/' . bin2hex(random_bytes(6)));

        return new Report(new FakePackages(__DIR__ . '/Fixtures/pim', $installed), $uid, 'https://woodypim.hypernode.io');
    }
}
