<?php

namespace GlueAgency\Backoffice\Tests\Fixtures;

use GlueAgency\Backoffice\Packages;

/**
 * The packages of a PIM that has Akeneo installed, which this repository does
 * not: every version named here is installed, anything else is looked up as
 * Composer installed it here.
 */
class FakePackages extends Packages
{
    /**
     * What the next instance has installed, for one the container makes.
     *
     * @var array<string, string>
     */
    public static $defaults = [];

    /**
     * @var array<string, string>
     */
    private $versions;

    /**
     * @param  array<string, string>|null  $versions  version by package name
     */
    public function __construct(string $root, ?array $versions = null)
    {
        parent::__construct($root);

        $this->versions = $versions ?? self::$defaults;
    }

    public function installedVersion(string $name): ?string
    {
        return $this->versions[$name] ?? parent::installedVersion($name);
    }
}
