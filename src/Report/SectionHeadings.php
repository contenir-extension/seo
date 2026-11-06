<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Record\RecordsInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * What the CMS calls each section whose records the add-on reports on, by the section's path.
 */
final class SectionHeadings
{
    /**
     * @return array<string, string>
     *
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws NotFoundExceptionInterface When the host lends no records.
     */
    public static function of(ExtensionContextInterface $context): array
    {
        $titles = [];
        foreach ($context->services()->get(RecordsInterface::class)->sections() as $section) {
            $titles[$section->section] = $section->heading ?? SectionNames::plural($section->title);
        }

        return $titles;
    }
}
