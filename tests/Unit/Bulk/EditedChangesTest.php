<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\Change;
use Contenir\Extension\Seo\Bulk\EditedChanges;
use Contenir\Extension\Seo\Bulk\FixKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function str_repeat;

#[Group('unit')]
#[Group('bulk')]
final class EditedChangesTest extends TestCase
{
    private static function change(int $id, FixKind $kind = FixKind::WriteDescriptions): Change
    {
        return new Change(
            new RecordReference('s', $id),
            "House {$id}",
            'Projects',
            $kind,
            'meta_description',
            null,
            'Original.',
        );
    }

    #[Test]
    public function cutsTheEditedTextToTheLongestAllowed(): void
    {
        $edited = EditedChanges::of([self::change(1)], ['s|1' => str_repeat('é', EditedChanges::MAX_LENGTH + 20)]);

        static::assertSame(str_repeat('é', EditedChanges::MAX_LENGTH), $edited[0]->after);
    }

    #[Test]
    public function dropsAChangeWhoseEditedTextIsBlank(): void
    {
        static::assertSame([], EditedChanges::of([self::change(1)], ['s|1' => '   ']));
    }

    #[Test]
    public function goesOnToTheChangesAfterOneNoOneEdited(): void
    {
        $edited = EditedChanges::of([self::change(1), self::change(2)], ['s|2' => 'Second.']);

        static::assertSame(
            ['Original.', 'Second.'],
            array_map(static fn(Change $change): string => $change->after, $edited),
        );
    }

    #[Test]
    public function goesOnToTheChangesAfterOneThatCannotBeEdited(): void
    {
        $edited = EditedChanges::of(
            [self::change(1, FixKind::HideFromSearch), self::change(2)],
            ['s|2' => 'Second.'],
        );

        static::assertSame(
            ['Original.', 'Second.'],
            array_map(static fn(Change $change): string => $change->after, $edited),
        );
    }

    #[Test]
    public function keepsAChangeNoOneEditedAsItWas(): void
    {
        $edited = EditedChanges::of([self::change(1)], []);

        static::assertSame(['Original.'], array_map(static fn(Change $change): string => $change->after, $edited));
    }

    #[Test]
    public function leavesAChangeThatCannotBeEditedAlone(): void
    {
        $edited = EditedChanges::of([self::change(1, FixKind::HideFromSearch)], ['s|1' => 'Typed.']);

        static::assertSame(['Original.'], array_map(static fn(Change $change): string => $change->after, $edited));
    }

    #[Test]
    public function takesTheEditedTextOfAWrittenDescription(): void
    {
        $edited = EditedChanges::of([self::change(1)], ['s|1' => '  Rewritten.  ']);

        static::assertSame(['Rewritten.'], array_map(static fn(Change $change): string => $change->after, $edited));
    }
}
