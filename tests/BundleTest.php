<?php

namespace GlueAgency\Backoffice\Tests;

use GlueAgency\Backoffice\Tests\Fixtures\FakePackages;
use GlueAgency\Backoffice\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The bundle as a PIM loads it. Its container is the whole PIM's: a service it
 * cannot build, or an environment variable it insists on, takes the PIM down
 * on every host, so it has to compile with none of its variables set.
 */
class BundleTest extends TestCase
{
    private const VARIABLES = ['AKENEO_PIM_URL', 'GLUE_BACKOFFICE_TOKEN', 'GLUE_BACKOFFICE_URL'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->unsetVariables();

        FakePackages::$defaults = ['akeneo/pim-community-dev' => 'v7.0.88'];
    }

    protected function tearDown(): void
    {
        $this->unsetVariables();

        FakePackages::$defaults = [];

        parent::tearDown();
    }

    public function test_the_pims_console_has_the_command_and_a_dry_run_reads_the_pim_url(): void
    {
        $this->setVariable('AKENEO_PIM_URL', 'https://woodypim.hypernode.io');

        $tester = $this->command('prod');
        $tester->execute(['--dry-run' => true, '--repository-name' => 'git@bitbucket.org:glue-team/woody_pimup.git']);

        $payload = json_decode($tester->getDisplay(), true);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame('akeneo', $payload['platform']);
        $this->assertSame('woodypim.hypernode.io', $payload['title']);
        $this->assertSame('7.0.88', $payload['version']);
        $this->assertSame(['name' => 'git@bitbucket.org:glue-team/woody_pimup.git'], $payload['repository']);
    }

    public function test_without_any_of_its_variables_the_pim_still_boots_and_reports_nothing(): void
    {
        $tester = $this->command('prod');
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame("No Glue Dashboard token configured, not reporting.\n", $tester->getDisplay());
    }

    public function test_another_environment_reports_nothing_even_with_a_token(): void
    {
        $this->setVariable('GLUE_BACKOFFICE_TOKEN', 'secret-token');

        $tester = $this->command('dev');
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame("Running in the dev environment, not reporting: only prod reports.\n", $tester->getDisplay());
    }

    public function test_the_token_and_the_dashboard_come_from_the_environment(): void
    {
        $this->setVariable('GLUE_BACKOFFICE_TOKEN', 'secret-token');
        $this->setVariable('GLUE_BACKOFFICE_URL', 'http://127.0.0.1:1/');

        $tester = $this->command('prod');
        $tester->execute([]);

        // Nothing listens there: the report was on its way with the token.
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringStartsWith('Could not report to the Glue Dashboard:', $tester->getDisplay());
    }

    private function command(string $environment): CommandTester
    {
        $kernel = new TestKernel($environment);
        $kernel->boot();

        $application = new Application();
        $application->setCommandLoader($kernel->getContainer()->get('console.command_loader'));

        return new CommandTester($application->find('glue:backoffice:report'));
    }

    private function setVariable(string $name, string $value): void
    {
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
    }

    private function unsetVariables(): void
    {
        foreach (self::VARIABLES as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }
    }
}
