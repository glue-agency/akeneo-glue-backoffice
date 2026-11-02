<?php

namespace GlueAgency\Backoffice;

use Composer\InstalledVersions;
use ReflectionClass;
use Throwable;

/**
 * What Composer installed for the PIM. Its packages are the ones the PIM
 * requires directly: its root composer.json's `require`, not its whole lock
 * file, each at the version installed, with the description its own
 * composer.json gives. Akeneo itself is among them, as
 * `akeneo/pim-community-dev`. `require-dev` stays out: it never reaches
 * production.
 */
class Packages
{
    /**
     * @var string
     */
    private $root;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private $installed;

    /**
     * @param  string  $root  The directory holding the PIM's composer.json
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    /**
     * @return array<int, array{name: string, version: string, description: string}>
     */
    public function collect(): array
    {
        $composer = self::readJson($this->root . '/composer.json');
        $installed = $this->installed();
        $packages = [];

        foreach (array_keys((array) ($composer['require'] ?? [])) as $name) {
            $name = (string) $name;

            if (self::isPlatformRequirement($name)) {
                continue;
            }

            $packages[] = [
                'name'        => $name,
                'version'     => $this->installedVersion($name) ?? 'dev',
                'description' => (string) ($installed[$name]['description'] ?? ''),
            ];
        }

        return $packages;
    }

    /**
     * The version Composer installed of one package, as its tag reads
     * (`v5.0.75`), or null when it is not installed.
     */
    public function installedVersion(string $name): ?string
    {
        try {
            $version = InstalledVersions::isInstalled($name) ? InstalledVersions::getPrettyVersion($name) : null;
        } catch (Throwable $e) {
            // Not installed as far as Composer knows.
            return null;
        }

        return $version ? (string) $version : null;
    }

    /**
     * PHP, an extension (ext-intl), a library (lib-curl) or Composer's own API:
     * what the server provides rather than a package, and the only names
     * without a vendor.
     */
    public static function isPlatformRequirement(string $name): bool
    {
        return strpos($name, '/') === false;
    }

    /**
     * Composer's own record of what it installed, by name, for descriptions:
     * the installed.json beside InstalledVersions.
     *
     * @return array<string, array<string, mixed>>
     */
    private function installed(): array
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        $data = self::readJson(dirname((string) (new ReflectionClass(InstalledVersions::class))->getFileName()) . '/installed.json');
        $packages = $data['packages'] ?? $data;
        $this->installed = [];

        foreach ((array) $packages as $package) {
            if (is_array($package) && isset($package['name'])) {
                $this->installed[(string) $package['name']] = $package;
            }
        }

        return $this->installed;
    }

    /**
     * @return array<mixed>
     */
    private static function readJson(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
