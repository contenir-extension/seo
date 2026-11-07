<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Bulk;

use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Record\RecordUpdateResult;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Override;

use function array_map;

/**
 * Record updates that answer every update with the same result and save nothing.
 */
final readonly class RefusingUpdates implements RecordUpdatesInterface
{
    public function __construct(
        private ActionResult $result,
    ) {}

    #[Override]
    public function update(array $updates): array
    {
        return array_map(
            fn($update): RecordUpdateResult => new RecordUpdateResult($update->record, $this->result),
            $updates,
        );
    }
}
