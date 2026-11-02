<?php

namespace GlueAgency\Backoffice\Tests;

use GlueAgency\Backoffice\InstallUid;
use PHPUnit\Framework\TestCase;

class InstallUidTest extends TestCase
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * @var string
     */
    private $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/glue-backoffice-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/file_storage/glue-backoffice-uid');
        @rmdir($this->directory . '/file_storage');
        @rmdir($this->directory);

        parent::tearDown();
    }

    public function test_the_uid_is_made_on_the_first_report_and_kept_for_every_next_one(): void
    {
        $path = $this->directory . '/file_storage/glue-backoffice-uid';

        $first = (new InstallUid($path))->get();

        $this->assertMatchesRegularExpression(self::UUID, $first);
        $this->assertSame($first, (new InstallUid($path))->get());
        $this->assertSame($first, trim((string) file_get_contents($path)));
    }

    public function test_peeking_keeps_nothing(): void
    {
        $path = $this->directory . '/file_storage/glue-backoffice-uid';

        $this->assertMatchesRegularExpression(self::UUID, (new InstallUid($path))->peek());
        $this->assertFileDoesNotExist($path);
    }

    public function test_a_uid_that_cannot_be_kept_is_still_made(): void
    {
        // A file where its directory should be.
        touch($this->directory);

        $uid = new InstallUid($this->directory . '/file_storage/glue-backoffice-uid');

        $this->assertMatchesRegularExpression(self::UUID, $uid->get());
        $this->assertNotSame($uid->get(), $uid->get());

        unlink($this->directory);
    }
}
