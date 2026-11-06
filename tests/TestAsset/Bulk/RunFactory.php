<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Bulk;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Bulk\Run;
use Contenir\Extension\Seo\Bulk\RunChange;

/**
 * Runs for tests, built without a form or a host.
 */
final class RunFactory
{
    public const string SECTION = 'library.projects.project';

    public const string ADVANCED = 'library.news.article';

    /**
     * @mago-expect lint:excessive-parameter-list A change is built for a test by naming only what the test is about.
     */
    public static function change(
        int $id = 1,
        string $status = RunChange::PENDING,
        ?string $before = null,
        string $after = 'A weatherboard house.',
        string $section = self::SECTION,
        string $column = 'meta_description',
        ?string $key = null,
    ): RunChange {
        return new RunChange(
            new RecordReference($section, $id),
            "House {$id}",
            'Projects',
            $column,
            $before,
            $after,
            $status,
            key: $key,
        );
    }

    /**
     * @param list<RunChange>|null $changes One waiting change to a description unless given
     */
    public static function make(
        string $id = 'run-1',
        int $at = 1_000,
        string $state = Run::QUEUED,
        ?array $changes = null,
        ?int $undoneAt = null,
    ): Run {
        return new Run(
            $id,
            $at,
            'Ann Editor',
            FixKind::WriteDescriptions,
            $changes ?? [self::change()],
            $state,
            $undoneAt,
        );
    }
}
