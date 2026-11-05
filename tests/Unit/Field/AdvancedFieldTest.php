<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Field;

use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Seo\Field\AdvancedField;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\SettingsContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AdvancedFieldTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'nothing'          => [null],
            'an empty value'   => [''],
            'an empty object'  => ['{}'],
            'an empty list'    => ['[]'],
            'a share title'    => ['{"title":"House"}'],
            'a full address'   => ['{"canonical":"https://www.example.com/house"}'],
            'a plain address'  => ['{"canonical":"http://www.example.com/house"}'],
            'one robots rule'  => ['{"robots":"noindex"}'],
            'two robots rules' => ['{"robots":"NoIndex, nofollow"}'],
            'each schema'      => ['{"schema":"BlogPosting"}'],
            'the first schema' => ['{"schema":"WebPage"}'],
            'a key it ignores' => ['{"other":"x"}'],
        ];
    }

    /**
     * @return array<string, array{mixed, string|null}>
     */
    public static function normaliseProvider(): array
    {
        return [
            'nothing'          => [null, null],
            'an empty value'   => ['', null],
            'empty overrides'  => ['{"image":" "}', null],
            'not JSON, kept'   => ['{not json', '{not json'],
            'a list, kept'     => ['["x"]', '["x"]'],
            'only space'       => ['  ', null],
            'trimmed'          => ['{"image_alt":" Café ","other":"x"}', '{"image_alt":"Café"}'],
            'an image dropped' => [
                '{"title":"T","image":"/a.jpg","robots":"noindex"}',
                '{"robots":"noindex","title":"T"}',
            ],
            'only a title'     => ['{"title":"T"}', '{"title":"T"}'],
            'only an image'    => ['{"image":"/a.jpg"}', null],
            'addresses as is'  => [
                '{"canonical":"https://www.example.com/a"}',
                '{"canonical":"https://www.example.com/a"}',
            ],
        ];
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function refusedProvider(): array
    {
        $unreadable = 'The SEO overrides could not be read.';
        $canonical  = 'The canonical address must be a full address, such as https://www.example.com/about.';
        $robots     = 'The robots rule must be made of rules such as noindex and nofollow, separated by commas.';
        $schema     = 'The schema type must be one of the types the site can describe.';

        return [
            'not JSON'                    => ['{not json', $unreadable],
            'a list'                      => ['["title"]', $unreadable],
            'a number'                    => ['3', $unreadable],
            'not text'                    => [3, $unreadable],
            'a canonical path'            => ['{"canonical":"/house"}', $canonical],
            'a canonical of another kind' => ['{"canonical":"ftp://www.example.com/house"}', $canonical],
            'a script canonical'          => ['{"canonical":"javascript:alert(1)"}', $canonical],
            'an unknown robots rule'      => ['{"robots":"noindex, sometimes"}', $robots],
            'an unknown schema'           => ['{"schema":"Person"}', $schema],
        ];
    }

    #[Test]
    #[DataProvider('acceptedProvider')]
    public function acceptsWhatTheSiteCanUse(mixed $value): void
    {
        static::assertSame([], (new AdvancedField())->validate($value));
    }

    #[Test]
    public function isATextareaItsComponentEnhances(): void
    {
        $field = new AdvancedField();

        static::assertSame(['[data-seo-advanced]', FieldType::Textarea], [$field->component(), $field->control()]);
    }

    #[Test]
    public function offersNoChoices(): void
    {
        static::assertSame([], (new AdvancedField())->options(new SettingsContext()));
    }

    #[Test]
    #[DataProvider('refusedProvider')]
    public function refusesWhatTheSiteCannot(mixed $value, string $problem): void
    {
        static::assertSame([$problem], (new AdvancedField())->validate($value));
    }

    #[Test]
    public function reportsEachProblem(): void
    {
        static::assertCount(
            3,
            (new AdvancedField())->validate(
                '{"canonical":"x","robots":"x","schema":"x"}',
            ),
        );
    }

    #[Test]
    #[DataProvider('normaliseProvider')]
    public function storesTheOverridesThatHoldSomething(mixed $value, ?string $stored): void
    {
        static::assertSame($stored, (new AdvancedField())->normalise($value));
    }
}
