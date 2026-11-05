<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Site\SiteRequestsInterface;
use Contenir\Extension\Api\Url\RecordReference;
use Psr\Container\ContainerExceptionInterface;

use function is_string;
use function parse_url;

use const PHP_URL_PATH;

/**
 * Asks the site what a record's page puts in its head. A host that can't ask,
 * a record with no page, or a site that refuses or doesn't answer, gives nothing.
 */
final readonly class SiteInspector
{
    public const string ENDPOINT = '/api/seo/inspect';

    /** The field naming the record's path, as the endpoint reads it. */
    public const string PATH_FIELD = 'path';

    public function __construct(
        private ExtensionContextInterface $context,
    ) {}

    /**
     * @throws ContainerExceptionInterface When the host's service can't be reached.
     */
    public function head(RecordReference $record): ?SiteHead
    {
        $services = $this->context->services();
        $url      = $this->context->urls()->url($record);
        $path     = null === $url ? null : parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! $services->has(SiteRequestsInterface::class)) {
            return null;
        }

        $answer = $services->get(SiteRequestsInterface::class)->post($record, self::ENDPOINT, [
            self::PATH_FIELD => $path,
        ]);

        return null === $answer || 200 !== $answer->status ? null : SiteHead::fromJson($answer->body);
    }
}
