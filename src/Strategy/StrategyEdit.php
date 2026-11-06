<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use Contenir\Extension\Api\Section\Element;

use function implode;
use function in_array;

/**
 * The section's `<seo>` block with what a strategy form says written into it. Everything the form does not
 * speak for, such as the type, the advanced column and which roles must be filled, is carried over.
 */
final readonly class StrategyEdit
{
    /**
     * Whether the form changes a role the block does not name, which takes the conventions.
     *
     * @throws InvalidStrategy When a source is not one a block can hold.
     */
    private static function changes(SectionStrategy $current, StrategyForm $form, string $role): bool
    {
        return (
            $form->sources($role) !== $current->sourcesOf($role)
                || $form->takesContent($role) !== $current->takesContent($role)
                || ('image' === $role && $form->alt() !== $current->altSource())
        );
    }

    /**
     * The role's element with the form's sources, its alt text for an image and its content setting.
     *
     * @throws InvalidStrategy When a source is not one a block can hold.
     */
    private static function role(Element $child, StrategyForm $form): Element
    {
        $attributes = $child->attributes;
        unset($attributes['content']);
        if ('image' === $child->name) {
            unset($attributes['alt']);
            if ('' !== $form->alt()) {
                $attributes['alt'] = $form->alt();
            }
        }

        if (in_array($child->name, ['description', 'image'], strict: true) && ! $form->takesContent($child->name)) {
            $attributes['content'] = 'false';
        }

        return new Element($child->name, $attributes, implode(', ', $form->sources($child->name)), $child->children);
    }

    /**
     * @param array<array-key, mixed> $post
     *
     * @throws InvalidStrategy When the form names a schema type, a mode or a source a block can't hold.
     */
    public function apply(Element $block, SectionStrategy $current, array $post): Element
    {
        $form       = new StrategyForm($current, $post);
        $attributes = $block->attributes;
        unset($attributes['schema'], $attributes['mode']);
        if ('' !== $form->schema()) {
            $attributes['schema'] = $form->schema();
        }

        if (! $form->automatic()) {
            $attributes['mode'] = 'manual';
        }

        return new Element($block->name, $attributes, $block->text, $this->children($block, $current, $form));
    }

    /**
     * @return list<Element>
     *
     * @throws InvalidStrategy When a source is not one a block can hold.
     */
    private function children(Element $block, SectionStrategy $current, StrategyForm $form): array
    {
        $kept = [];
        $done = [];
        foreach ($block->children as $child) {
            if (! in_array($child->name, SectionStrategy::ROLES, strict: true)) {
                $kept[] = $child;

                continue;
            }

            $done[] = $child->name;
            $kept[] = self::role($child, $form);
        }

        foreach (SectionStrategy::ROLES as $role) {
            if (in_array($role, $done, strict: true) || ! self::changes($current, $form, $role)) {
                continue;
            }

            $kept[] = self::role(new Element($role), $form);
        }

        return $kept;
    }
}
