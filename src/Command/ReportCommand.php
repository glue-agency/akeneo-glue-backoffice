<?php

namespace GlueAgency\Backoffice\Command;

use GlueAgency\Backoffice\Client;
use GlueAgency\Backoffice\Report;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Reports the PIM to the Glue Dashboard, run by the deploy once the release is
 * live. Only the prod environment reports: any other, and a prod host without
 * the install's token, say why and report nothing. A refused report exits with
 * 1, which fails the deploy, as the Craft, Laravel and Magento reporters do:
 * the Dashboard's messages say what to fix.
 */
class ReportCommand extends Command
{
    public const NAME = 'glue:backoffice:report';

    public const DEFAULT_URL = 'https://dashboard.glue.be/';

    /**
     * @var Report
     */
    private $report;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $environment;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $url;

    /**
     * @param  string  $environment  The kernel's: only `prod` reports
     * @param  string|null  $token  GLUE_BACKOFFICE_TOKEN, the install's reporting token
     * @param  string|null  $url  GLUE_BACKOFFICE_URL, only set to try another Dashboard
     */
    public function __construct(Report $report, Client $client, string $environment, ?string $token, ?string $url)
    {
        $this->report = $report;
        $this->client = $client;
        $this->environment = $environment;
        $this->token = trim((string) $token);
        $this->url = trim((string) $url) !== '' ? trim((string) $url) : self::DEFAULT_URL;

        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Reports this PIM to the Glue Dashboard')
            ->addOption('repository-name', null, InputOption::VALUE_REQUIRED, 'The repository this PIM deploys from')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the report as JSON instead of sending it; needs no token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repositoryName = $input->getOption('repository-name');
        $repositoryName = $repositoryName === null ? null : (string) $repositoryName;

        if ($input->getOption('dry-run')) {
            try {
                $payload = $this->report->build($repositoryName, false);
            } catch (Throwable $e) {
                $output->writeln('<error>' . OutputFormatter::escape($e->getMessage()) . '</error>');

                return 1;
            }

            $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), OutputInterface::OUTPUT_RAW);

            return 0;
        }

        if ($this->environment !== 'prod') {
            $output->writeln("Running in the {$this->environment} environment, not reporting: only prod reports.");

            return 0;
        }

        if ($this->token === '') {
            $output->writeln('No Glue Dashboard token configured, not reporting.');

            return 0;
        }

        try {
            $payload = $this->report->build($repositoryName);
            [$success, $errors] = $this->client->report($this->url, $this->token, $payload);
        } catch (Throwable $e) {
            $output->writeln('<error>Could not report to the Glue Dashboard: ' . OutputFormatter::escape($e->getMessage()) . '</error>');

            return 1;
        }

        if (! $success) {
            $output->writeln('<error>The Glue Dashboard did not accept the report:</error>');

            foreach ($errors as $error) {
                $output->writeln('<error>    ' . OutputFormatter::escape($error) . '</error>');
            }

            return 1;
        }

        $output->writeln("<info>Reported Akeneo '{$payload['title']}' v'{$payload['version']}'</info>");
        $output->writeln("    Edition '{$payload['edition']}'");
        $output->writeln("    PHP v'{$payload['phpVersion']}'");
        $output->writeln('<info>Packages</info>');

        foreach ($payload['packages'] as $package) {
            $output->writeln("    Package '{$package['name']}' v'{$package['version']}'");
        }

        $output->writeln('<info>Repository</info>');
        $output->writeln("    Name '" . ($payload['repository']['name'] ?? '') . "'");

        return 0;
    }
}
