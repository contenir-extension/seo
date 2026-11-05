<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Api\Section\Element;
use Contenir\Seo\Sections\SeoBlock;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function trim;

/**
 * A section's `<seo>` block as the host hands it over, read as the site
 * reads its admin.xml: each child names a role and the columns that fill it,
 * first non-empty first (`<description>meta_description, abstract</description>`),
 * an `alt` attribute names the columns of its alt text, `required="true"`
 * makes the role one a record must fill, and `<advanced>` names the column
 * of the record's overrides. Roles a block leaves out take the conventions.
 */
final class SeoDeclaration
{
    /**
     * The columns the block names itself, leaving out the conventions it takes.
     *
     * @return list<string>
     */
    public static function named(Element $seo): array
    {
        $named = [];
        foreach ($seo->children as $child) {
            $named[] = self::columns($child->text);
            $named[] = self::columns($child->attribute('alt', '') ?? '');
        }

        return array_values(array_unique(array_merge(...$named)));
    }

    public static function read(Element $seo): SeoBlock
    {
        $roles    = SeoBlock::CONVENTIONS;
        $required = [];
        $advanced = null;
        foreach ($seo->children as $child) {
            if ('advanced' === $child->name) {
                $advanced = self::columns($child->text)[0] ?? null;

                continue;
            }

            $roles[$child->name] = self::columns($child->text);
            $alt                 = $child->attribute('alt');
            if (null !== $alt) {
                $roles["{$child->name}@alt"] = self::columns($alt);
            }

            if ('true' === $child->attribute('required')) {
                $required[] = $child->name;
            }
        }

        $schema = trim($seo->attribute('schema', '') ?? '');

        return new SeoBlock(
            trim($seo->attribute('type', '') ?? ''),
            '' === $schema ? null : $schema,
            $roles,
            $required,
            $advanced,
        );
    }

    /**
     * @return list<string>
     */
    private static function columns(string $list): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', $list)),
            static fn(string $column): bool => '' !== $column,
        ));
    }
}
