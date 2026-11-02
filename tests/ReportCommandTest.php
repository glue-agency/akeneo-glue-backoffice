<?php

namespace GlueAgency\Backoffice\Tests;

use GlueAgency\Backoffice\Command\ReportCommand;
use GlueAgency\Backoffice\InstallUid;
use GlueAgency\Backoffice\Report;
use GlueAgency\Backoffice\Tests\Fixtures\FakeClient;
use GlueAgency\Backoffice\Tests\Fixtures\FakePackages;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * What a deploy runs once the release is live: the report it sends, and what
 * makes it fail the deploy (a refusal, a Dashboard out of reach) or report
 * nothing at all (any environment but prod, no token).
 */
class ReportCommandTest extends TestCase
{
    /**
     * @var string
     */
    private $uidPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uidPath = sys_get_temp_dir() . '/glue-backoffice-' . bin2hex(random_bytes(6)) . '/glue-backoffice-uid';
    }

    protected function tearDown(): void
    {
        @unlink($this->uidPath);
        @rmdir(dirname($this->uidPath));

        parent::tearDown();
    }

    public function test_it_reports_the_pim_with_the_installs_token(): void
    {
        $client = new FakeClient();

        $tester = $this->tester($client);
        $tester->execute(['--repository-name' => 'git@bitbucket.org:glue-team/woody_pimup.git']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("Reported Akeneo 'woodypim.hypernode.io' v'7.0.88'", $tester->getDisplay());
        $this->assertStringContainsString("    Edition 'CE'", $tester->getDisplay());
        $this->assertStringContainsString("    Package 'akeneo/pim-community-dev' v'v7.0.88'", $tester->getDisplay());
        $this->assertStringContainsString("    Name 'git@bitbucket.org:glue-team/woody_pimup.git'", $tester->getDisplay());

        $this->assertCount(1, $client->sent);
        $this->assertSame('https://dashboard.invalid/', $client->sent[0]['url']);
        $this->assertSame('secret-token', $client->sent[0]['token']);
        $this->assertSame('akeneo', $client->sent[0]['payload']['platform']);
        $this->assertSame(trim((string) file_get_contents($this->uidPath)), $client->sent[0]['payload']['uid']);
    }

    public function test_without_a_dashboard_url_it_reports_to_glues(): void
    {
        $client = new FakeClient();

        $this->tester($client, 'prod', 'secret-token', null)->execute([]);

        $this->assertSame('https://dashboard.glue.be/', $client->sent[0]['url']);
    }

    public function test_a_dry_run_prints_the_report_sends_nothing_and_keeps_no_uid(): void
    {
        $client = new FakeClient();

        $tester = $this->tester($client, 'dev', null);
        $tester->execute(['--dry-run' => true]);

        $payload = json_decode($tester->getDisplay(), true);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame('7.0.88', $payload['version']);
        $this->assertArrayNotHasKey('repository', $payload);
        $this->assertSame([], $client->sent);
        $this->assertFileDoesNotExist($this->uidPath);
    }

    public function test_any_environment_but_prod_reports_nothing(): void
    {
        $client = new FakeClient();

        $tester = $this->tester($client, 'staging');
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame("Running in the staging environment, not reporting: only prod reports.\n", $tester->getDisplay());
        $this->assertSame([], $client->sent);
    }

    public function test_without_a_token_nothing_is_reported(): void
    {
        $client = new FakeClient();

        $tester = $this->tester($client, 'prod', '  ');
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame("No Glue Dashboard token configured, not reporting.\n", $tester->getDisplay());
        $this->assertSame([], $client->sent);
    }

    public function test_a_refused_report_prints_every_message_and_fails_the_deploy(): void
    {
        $tester = $this->tester(new FakeClient([false, ['version: The version field is required.', 'woodypim.hypernode.io: This install is manual on the Dashboard.']]));
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame(
            "The Glue Dashboard did not accept the report:\n    version: The version field is required.\n    woodypim.hypernode.io: This install is manual on the Dashboard.\n",
            $tester->getDisplay(),
        );
    }

    public function test_a_dashboard_out_of_reach_fails_the_deploy(): void
    {
        $tester = $this->tester(new FakeClient([true, []], new RuntimeException('Connection refused')));
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame("Could not report to the Glue Dashboard: Connection refused\n", $tester->getDisplay());
    }

    public function test_a_pim_without_akeneo_installed_fails_the_deploy_and_says_so(): void
    {
        $client = new FakeClient();

        $tester = $this->tester($client, 'prod', 'secret-token', 'https://dashboard.invalid/', []);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Akeneo is not among the installed Composer packages', $tester->getDisplay());
        $this->assertSame([], $client->sent);
    }

    /**
     * @param  array<string, string>  $installed
     */
    private function tester(
        FakeClient $client,
        string $environment = 'prod',
        ?string $token = 'secret-token',
        ?string $url = 'https://dashboard.invalid/',
        array $installed = ['akeneo/pim-community-dev' => 'v7.0.88']
    ): CommandTester {
        $packages = new FakePackages(__DIR__ . '/Fixtures/pim', $installed);
        $report = new Report($packages, new InstallUid($this->uidPath), 'https://woodypim.hypernode.io');

        return new CommandTester(new ReportCommand($report, $client, $environment, $token, $url));
    }
}
