<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use RuntimeException;

/**
 * A strategy form that says something a `<seo>` block can't hold.
 */
final class InvalidStrategy extends RuntimeException {}
