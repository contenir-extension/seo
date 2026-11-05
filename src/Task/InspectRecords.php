<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Task;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Schedule\ScheduledTaskInterface;
use Contenir\Extension\Seo\Inspect\RecordInspections;
use Override;
use Psr\Container\ContainerExceptionInterface;

/**
 * Keeps the report's answers from the site up to date in the background.
 */
final readonly class InspectRecords implements ScheduledTaskInterface
{
    public const string NAME = 'inspect-records';

    /** How long a run may ask the site for. */
    public const int SECONDS = 50;

    /**
     * @throws ContainerExceptionInterface When the host's service can't be reached.
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    #[Override]
    public function run(ExtensionContextInterface $context): void
    {
        (new RecordInspections($context))->refresh(self::SECONDS);
    }
}
