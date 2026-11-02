<?php

namespace GlueAgency\Backoffice\Tests;

use GlueAgency\Backoffice\Client;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The report on the wire, against a Dashboard on PHP's built-in server
 * (Fixtures/dashboard.php): form-encoded, with the token as a bearer header,
 * and every answer read the way the Craft, Laravel and Magento reporters read
 * it.
 */
class ClientTest extends TestCase
{
    /**
     * @var resource|null
     */
    private static $server;

    /**
     * @var string
     */
    private static $url;

    /**
     * @var string
     */
    private static $record;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$record = sys_get_temp_dir() . '/glue-backoffice-request-' . bin2hex(random_bytes(6)) . '.json';
        self::$url = "http://127.0.0.1:{$port}";

        $command = sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg(__DIR__ . '/Fixtures/dashboard.php'));
        $environment = array_merge(getenv(), ['GLUE_TEST_RECORD' => self::$record]);

        self::$server = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $environment);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port);

            if ($connection) {
                fclose($connection);

                return;
            }

            usleep(50000);
        }

        self::fail('The built-in server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }

        @unlink(self::$record);

        parent::tearDownAfterClass();
    }

    public function test_an_accepted_report_is_posted_form_encoded_with_the_token(): void
    {
        $payload = [
            'platform'   => 'akeneo',
            'title'      => 'woodypim.hypernode.io',
            'version'    => '7.0.88',
            'packages'   => [['name' => 'akeneo/pim-community-dev', 'version' => 'v7.0.88', 'description' => '']],
            'repository' => ['name' => 'git@bitbucket.org:glue-team/woody_pimup.git'],
        ];

        $answer = (new Client())->report(self::$url . '/ok/', 'secret-token', $payload);

        $request = json_decode((string) file_get_contents(self::$record), true);

        $this->assertSame([true, []], $answer);
        $this->assertSame('POST', $request['method']);
        $this->assertSame('/ok/actions/backoffice/report', $request['path']);
        $this->assertSame('Bearer secret-token', $request['authorization']);
        $this->assertSame('application/json', $request['accept']);
        $this->assertSame('application/x-www-form-urlencoded', $request['contentType']);
        $this->assertSame($payload, $request['form']);
    }

    public function test_a_refusal_comes_back_as_every_message_with_its_field(): void
    {
        $this->assertSame(
            [false, ['version: The version field is required.', 'woodypim.hypernode.io: This install is manual on the Dashboard.']],
            (new Client())->report(self::$url . '/refuse', 'secret-token', ['platform' => 'akeneo']),
        );
    }

    public function test_an_answer_that_is_not_the_dashboards_comes_back_as_it_is(): void
    {
        $this->assertSame(
            [false, ['Unexpected response (HTTP 502): <html><body>Bad Gateway</body></html>']],
            (new Client())->report(self::$url . '/broken', 'secret-token', ['platform' => 'akeneo']),
        );
    }

    public function test_a_dashboard_out_of_reach_throws(): void
    {
        $this->expectException(RuntimeException::class);

        (new Client())->report('http://127.0.0.1:1/', 'secret-token', ['platform' => 'akeneo']);
    }

    public function test_a_message_without_errors_is_read_as_one(): void
    {
        $this->assertSame(['Unauthorized.'], Client::errors(401, ['message' => 'Unauthorized.'], '{"message":"Unauthorized."}'));
    }
}
