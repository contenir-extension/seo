<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\FieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Seo\Sitemap\SitemapSettings;
use Override;

use function implode;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Workflow names to leave out of the sitemap, as an editor types them:
 * seo:workflows. They are stored in lower case, separated by commas.
 *
 * @api
 */
final class WorkflowsField implements FieldTypeInterface
{
    public const string NAME = 'workflows';

    /**
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        return SitemapSettings::workflows(is_string($value) ? $value : '');
    }

    #[Override]
    public function component(): ?string
    {
        return null;
    }

    #[Override]
    public function control(): FieldType
    {
        return FieldType::Text;
    }

    #[Override]
    public function normalise(mixed $value): mixed
    {
        return implode(', ', self::names($value));
    }

    #[Override]
    public function options(ExtensionContextInterface $context): array
    {
        return [];
    }

    #[Override]
    public function validate(mixed $value): array
    {
        $problems = [];
        foreach (self::names($value) as $name) {
            if (1 === preg_match('/^[a-z][a-z0-9_-]*$/', $name)) {
                continue;
            }

            $problems[] = sprintf(
                '"%s" isn\'t a workflow name. Use names such as redirect or faq, separated by commas.',
                $name,
            );
        }

        return $problems;
    }
}
