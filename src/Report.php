<?php

namespace GlueAgency\Backoffice;

use RuntimeException;

/**
 * What the PIM says about itself: the report the Dashboard keeps as its
 * install's Akeneo section. The host it is served on as its title, Akeneo's
 * version and edition, PHP's version, and the packages it requires.
 *
 * The version is the one Composer installed, not Akeneo's own `version.txt`:
 * that file is missing before 7.0 and still read 7.0.85 on 2026.4.
 */
class Report
{
    public const PLATFORM = 'akeneo';

    /**
     * Akeneo's own package per edition, Enterprise first: an Enterprise PIM
     * installs the Community one too.
     */
    public const EDITIONS = [
        'akeneo/pim-enterprise-dev' => 'EE',
        'akeneo/pim-community-dev'  => 'CE',
    ];

    /**
     * What Akeneo's `.env` ships as its URL, which a host that never set its
     * own would report.
     */
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

    private const MAX_LENGTH = 255;

    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var InstallUid
     */
    private $installUid;

    /**
     * @var string|null
     */
    private $pimUrl;

    /**
     * @param  string|null  $pimUrl  AKENEO_PIM_URL, the address Akeneo puts in its links
     */
    public function __construct(Packages $packages, InstallUid $installUid, ?string $pimUrl)
    {
        $this->packages = $packages;
        $this->installUid = $installUid;
        $this->pimUrl = $pimUrl;
    }

    /**
     * @param  bool  $keepUid  False on a dry run, which leaves no trace
     * @return array<string, mixed>
     *
     * @throws RuntimeException when Akeneo is not among the installed packages
     */
    public function build(?string $repositoryName = null, bool $keepUid = true): array
    {
        [$version, $edition] = $this->akeneo();

        $payload = [
            'platform'   => self::PLATFORM,
            'title'      => self::limit(self::title($this->pimUrl)),
            'version'    => self::limit($version),
            'edition'    => $edition,
            'phpVersion' => PHP_VERSION,
            'uid'        => $keepUid ? $this->installUid->get() : $this->installUid->peek(),
            'packages'   => array_map(function (array $package) {
                return array_map([self::class, 'limit'], $package);
            }, $this->packages->collect()),
        ];

        if ($repositoryName !== null && $repositoryName !== '') {
            $payload['repository'] = ['name' => self::limit($repositoryName)];
        }

        return $payload;
    }

    /**
     * The host the PIM is served on, from its own URL. A PIM still on Akeneo's
     * shipped localhost, or with no URL at all, is named after the machine.
     */
    public static function title(?string $pimUrl): string
    {
        $host = parse_url(trim((string) $pimUrl), PHP_URL_HOST);

        if (is_string($host) && $host !== '' && ! in_array(strtolower($host), self::LOCAL_HOSTS, true)) {
            return $host;
        }

        $machine = gethostname();

        return is_string($machine) && $machine !== '' ? $machine : 'Akeneo PIM';
    }

    /**
     * Nothing the Dashboard keeps is longer.
     */
    public static function limit(string $value): string
    {
        return mb_substr($value, 0, self::MAX_LENGTH);
    }

    /**
     * Akeneo's version without the `v` its tags carry, and its edition.
     *
     * @return array{0: string, 1: string}
     */
    private function akeneo(): array
    {
        foreach (self::EDITIONS as $package => $edition) {
            $version = $this->packages->installedVersion($package);

            if ($version !== null && $version !== '') {
                return [(string) preg_replace('/^v(?=\d)/', '', $version), $edition];
            }
        }

        throw new RuntimeException('Akeneo is not among the installed Composer packages: neither ' . implode(' nor ', array_keys(self::EDITIONS)) . ' is installed.');
    }
}
