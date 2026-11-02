<?php

namespace GlueAgency\Backoffice;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Adds `glue:backoffice:report` to the PIM's console, which the deploy runs
 * once the release is live. Everything it needs comes from the PIM's `.env`.
 */
class GlueBackofficeBundle extends Bundle
{
}
