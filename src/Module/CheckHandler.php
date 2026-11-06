<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Inspect\RecordInspections;
use Contenir\Extension\Seo\Inspect\Refreshed;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;

/**
 * Asks the site about the records that need it now, for as long as a screen can
 * wait, and goes back to the Overview saying how that went.
 */
final readonly class CheckHandler implements HandlerInterface
{
    public const string NAME = 'records-check';

    /** How long the editor waits for the site. */
    public const int SECONDS = 20;

    private static function message(Refreshed $refreshed): string
    {
        return sprintf(
            'Checked %d %s with the site.%s%s',
            $refreshed->checked,
            1 === $refreshed->checked ? 'record' : 'records',
            0 === $refreshed->unanswered ? '' : sprintf(' It gave no answer for %d.', $refreshed->unanswered),
            $refreshed->finished ? '' : ' Check again for the rest.',
        );
    }

    /**
     * @throws ContainerExceptionInterface When the host's service can't be reached.
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        if ('POST' !== $request->getMethod()) {
            return HandlerResult::back();
        }

        $refreshed = (new RecordInspections($context))->refresh(self::SECONDS, RecordInspections::RECENT);

        return HandlerResult::back()->withMessage(
            0 === $refreshed->unanswered ? 'success' : 'warning',
            self::message($refreshed),
        );
    }
}
