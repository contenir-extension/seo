<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Event\EventInterface;
use Contenir\Extension\Api\Event\Record\AbstractRecordPreEvent;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Listener\ListenerInterface;
use Contenir\Extension\Seo\Section\Hidden;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Contenir\Extension\Seo\SeoSettings;
use Override;

/**
 * The save check a section's `<seo>` block binds: it notes a record's
 * missing title, description or image, a title longer than search results
 * show once the site's title template adds the site name, a description
 * outside the length they show, and a title another record of the section
 * already has, unless either of the two is hidden from search. A note is a warning, and the save goes ahead, unless the
 * block marks the role `required`, when a missing value holds the save.
 * It reads the values as they are about to be written, so it never waits on
 * the site.
 */
final readonly class CheckMeta implements ListenerInterface
{
    public const string NAME = 'check-meta';

    /**
     * @throws RowsRefusedException When the section's title columns can't be read for duplicates.
     * @throws UnknownSettingException When the host has no Search Appearance settings.
     */
    #[Override]
    public function handle(EventInterface $event, ExtensionContextInterface $context): void
    {
        if (! $event instanceof AbstractRecordPreEvent) {
            return;
        }

        $block = SeoBlocks::of($context)->one($event->section);
        if (null === $block) {
            return;
        }

        $seo    = SeoDeclaration::read($block->element);
        $values = new MetaValues($block, $event->values(), $seo);
        $notes  = new Notes($event, $seo);
        foreach ((new MetaIssues($seo, (new SeoSettings())->config($context)->title))->of($values) as $issue) {
            $issue->missing
                ? $notes->missing($issue->role, $issue->field, $issue->message)
                : $notes->warn($issue->field, $issue->message);
        }

        $title = $values->text('title');
        if (null !== $title && null === Hidden::reason($seo, $event->values())) {
            $notes->warn(
                $values->field('title'),
                (new TitleTaken(
                    $context->sections(),
                    $seo,
                    SeoSectionBlock::known($block, [Hidden::column($seo)]),
                ))->note(
                    $event->section,
                    SeoSectionBlock::known($block, [
                        ...(null === $seo->advanced ? [] : [$seo->advanced]),
                        ...($seo->roles['title'] ?? []),
                    ]),
                    $title,
                    $event->id,
                    $seo->advanced,
                ),
            );
        }
    }
}
