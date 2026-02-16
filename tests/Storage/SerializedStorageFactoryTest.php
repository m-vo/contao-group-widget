<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Storage;

use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\Group\Group;
use Mvo\ContaoGroupWidget\Storage\SerializedStorageFactory;
use PHPUnit\Framework\TestCase;

class SerializedStorageFactoryTest extends TestCase
{
    public function testName(): void
    {
        self::assertSame('serialized', SerializedStorageFactory::getName());
    }

    public function testCreate(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('quoteIdentifier')
            ->willReturnCallback(static fn (string $value): string => $value)
        ;

        $connection
            ->method('fetchOne')
            ->with('SELECT my_group from tl_foo WHERE id = ?', [5])
            ->willReturn(serialize([
                1 => ['data' => null],
                2 => ['data' => null],
                3 => ['data' => null],
            ]))
        ;

        $factory = new SerializedStorageFactory($connection);

        $group = $this->createMock(Group::class);
        $group
            ->method('getName')
            ->willReturn('my_group')
        ;

        $group
            ->method('getTable')
            ->willReturn('tl_foo')
        ;

        $group
            ->method('getRowId')
            ->willReturn(5)
        ;

        $group
            ->method('getFields')
            ->willReturn(['data'])
        ;

        $instance = $factory->create($group);

        self::assertSame([1, 2, 3], $instance->getElements());
    }
}
