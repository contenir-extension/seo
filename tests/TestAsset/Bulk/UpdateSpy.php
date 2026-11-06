<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Bulk;

use Closure;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Override;

/**
 * Record updates that run $before as each call starts and then pass it to the host's own.
 */
final readonly class UpdateSpy implements RecordUpdatesInterface
{
    /**
     * @param Closure(): void $before
     */
    public function __construct(
        private RecordUpdatesInterface $real,
        private Closure $before,
    ) {}

    #[Override]
    public function update(array $updates): array
    {
        ($this->before)();

        return $this->real->update($updates);
    }
}
