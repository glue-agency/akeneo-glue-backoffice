<?php

namespace GlueAgency\Backoffice\Tests\Fixtures;

use GlueAgency\Backoffice\Packages;

/**
 * The packages of a PIM Composer 1 installed: no InstalledVersions, only the
 * plain list it writes to vendor/composer/installed.json.
 */
class Composer1Packages extends Packages
{
    protected function hasRuntimeApi(): bool
    {
        return false;
    }
}
