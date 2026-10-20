<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Field;

use Contenir\Extension\Api\Field\FieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Seo\Field\BingTokenField;
use Contenir\Extension\Seo\Field\GoogleTokenField;
use Contenir\Extension\Seo\Field\MetaTokenField;
use Contenir\Extension\Seo\Field\PinterestTokenField;
use Contenir\Extension\Seo\Field\RobotsRulesField;
use Contenir\Extension\Seo\Field\WorkflowsField;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\SettingsContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FieldTypesTest extends TestCase
{
    /**
     * @return array<string, array{FieldTypeInterface, string}>
     */
    public static function tokenProblems(): array
    {
        return [
            'Google'    => [new GoogleTokenField(), 'Google Search Console'],
            'Bing'      => [new BingTokenField(), 'Bing Webmaster Tools'],
            'Pinterest' => [new PinterestTokenField(), 'Pinterest'],
            'Meta'      => [new MetaTokenField(), 'Meta (Facebook and Instagram)'],
        ];
    }

    /**
     * @return array<string, array{FieldTypeInterface, string, FieldType}>
     */
    public static function types(): array
    {
        return [
            'Google'       => [new GoogleTokenField(), 'google-verification', FieldType::Text],
            'Bing'         => [new BingTokenField(), 'bing-verification', FieldType::Text],
            'Pinterest'    => [new PinterestTokenField(), 'pinterest-verification', FieldType::Text],
            'Meta'         => [new MetaTokenField(), 'meta-verification', FieldType::Text],
            'workflows'    => [new WorkflowsField(), 'workflows', FieldType::Text],
            'robots rules' => [new RobotsRulesField(), 'robots-rules', FieldType::Textarea],
        ];
    }

    #[DataProvider('types')]
    #[Test]
    public function drawsWithABuiltInControlAndNoChoices(
        FieldTypeInterface $type,
        string $name,
        FieldType $control,
    ): void {
        static::assertSame(
            [$name, $control, null, []],
            [$type::NAME, $type->control(), $type->component(), $type->options(new SettingsContext())],
        );
    }

    #[DataProvider('tokenProblems')]
    #[Test]
    public function explainsATokenThatIsNotOne(FieldTypeInterface $type, string $service): void
    {
        static::assertSame(
            [
                "This doesn't look like a {$service} verification token. Paste the content value of the meta tag"
                    . " {$service} gives you, or the whole tag.",
            ],
            $type->validate('not a token'),
        );
    }

    #[Test]
    public function keepsTheTokenOfAPastedTag(): void
    {
        $type = new GoogleTokenField();

        static::assertSame(
            ['abc-123', '', [], []],
            [
                $type->normalise('<meta name="google-site-verification" content="abc-123" />'),
                $type->normalise(['not', 'text']),
                $type->validate(' abc-123 '),
                $type->validate(null),
            ],
        );
    }

    #[Test]
    public function readsWorkflowNamesAndRefusesOthers(): void
    {
        $type = new WorkflowsField();

        static::assertSame(
            [
                'redirect, faq, news-item',
                '',
                [],
                [
                    '"2fa" isn\'t a workflow name. Use names such as redirect or faq, separated by commas.',
                    '"faq?" isn\'t a workflow name. Use names such as redirect or faq, separated by commas.',
                ],
                [],
            ],
            [
                $type->normalise(" Redirect,FAQ\nnews-item, redirect "),
                $type->normalise(3),
                $type->validate('redirect, faq_pages'),
                $type->validate('2fa, redirect, faq?'),
                $type->validate(null),
            ],
        );
    }

    #[Test]
    public function writesRobotsRulesAsRobotsTxtSpellsThem(): void
    {
        $type = new RobotsRulesField();

        static::assertSame(
            [
                "Disallow: /search\n\nUser-agent: GPTBot\nDisallow: /",
                '',
                [],
                [
                    'Line 2 isn\'t a robots.txt rule. Start each line with User-agent:, Allow:, Disallow:, Crawl-delay:'
                        . ' or Sitemap:, or with # for a comment.',
                ],
            ],
            [
                $type->normalise("disallow: /search\n\nuser-agent: GPTBot\nDISALLOW: /\n"),
                $type->normalise(false),
                $type->validate(null),
                $type->validate("Disallow: /search\nNoindex: /"),
            ],
        );
    }
}
