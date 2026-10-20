<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo;

use function constant;
use function defined;
use function getenv;
use function is_string;

/**
 * The environment the CMS runs in, as APPLICATION_ENV names it:
 * production, staging, development and so on. A CMS that names none is
 * taken to be production.
 *
 * @api
 */
final readonly class Environment
{
    public const string PRODUCTION = 'production';

    public string $name;

    /**
     * @param ?string $name The environment's name; null reads APPLICATION_ENV
     */
    public function __construct(?string $name = null)
    {
        $this->name = $name ?? self::detect();
    }

    /**
     * @mago-expect analysis:mixed-assignment The constant is defined by the CMS; its type is checked here.
     */
    private static function detect(): string
    {
        $name = defined('APPLICATION_ENV') ? constant('APPLICATION_ENV') : getenv('APPLICATION_ENV');

        return is_string($name) && '' !== $name ? $name : self::PRODUCTION;
    }

    public function isProduction(): bool
    {
        return self::PRODUCTION === $this->name;
    }
}
