<?php

namespace GlueAgency\Backoffice;

/**
 * The PIM's own uid, which every report carries. Akeneo has none it reports,
 * so one is made on the first report and kept in the PIM's file storage, which
 * a deploy shares between releases.
 *
 * The Dashboard knows the PIM by its token, not by this uid: it never looks an
 * install up by it. So a uid that cannot be kept is no reason to fail a deploy,
 * and the report goes out with a new one.
 */
class InstallUid
{
    /**
     * @var string
     */
    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * The kept uid, or a new one kept from here on.
     */
    public function get(): string
    {
        $uid = $this->read();

        if ($uid !== '') {
            return $uid;
        }

        $uid = self::uuid();

        $this->write($uid);

        return $uid;
    }

    /**
     * The kept uid, or a new one that is not kept: what a dry run reports.
     */
    public function peek(): string
    {
        $uid = $this->read();

        return $uid !== '' ? $uid : self::uuid();
    }

    /**
     * A random (version 4) UUID.
     */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function read(): string
    {
        if (! is_file($this->path)) {
            return '';
        }

        return trim((string) @file_get_contents($this->path));
    }

    private function write(string $uid): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @file_put_contents($this->path, $uid . PHP_EOL);
    }
}
