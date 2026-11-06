<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Api\Queue\TaskQueueInterface;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Contenir\Extension\Seo\Module\RecordsHandler;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function in_array;

#[Group('unit')]
#[Group('bulk')]
final class RecordsHandlerTest extends TestCase
{
    /**
     * @return array<string, array{list<class-string>, bool}>
     */
    public static function hosts(): array
    {
        return [
            'both'                    => [[RecordUpdatesInterface::class, TaskQueueInterface::class], true],
            'updates without a queue' => [[RecordUpdatesInterface::class], false],
            'a queue without updates' => [[TaskQueueInterface::class], false],
            'neither'                 => [[], false],
        ];
    }

    /**
     * @param list<class-string> $lent
     */
    #[Test]
    #[DataProvider('hosts')]
    public function canFixOnlyWhereTheHostLendsUpdatesAndAQueue(array $lent, bool $expected): void
    {
        $services = new class($lent) implements ContainerInterface {
            /**
             * @param list<class-string> $lent
             */
            public function __construct(
                private readonly array $lent,
            ) {}

            public function get(string $id): never
            {
                throw new LogicException('Nothing is fetched');
            }

            public function has(string $id): bool
            {
                return in_array($id, $this->lent, strict: true);
            }
        };

        static::assertSame($expected, RecordsHandler::canFix($services));
    }
}
