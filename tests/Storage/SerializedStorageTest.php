<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Storage;

use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\Group\Group;
use Mvo\ContaoGroupWidget\Storage\SerializedStorage;
use PHPUnit\Framework\TestCase;

class SerializedStorageTest extends TestCase
{
    public function testGetElements(): void
    {
        $storage = new SerializedStorage($this->getConnectionMock(), $this->getGroupMock());

        self::assertEquals([1, 2, 3], $storage->getElements());
    }

    public function testCreateElement(): void
    {
        $storage = new SerializedStorage($this->getConnectionMock(), $this->getGroupMock());

        self::assertEquals(4, $storage->createElement());
    }

    public function testRemoveElement(): void
    {
        $connection = $this->getConnectionMock();
        $connection
            ->expects(self::once())
            ->method('update')
            ->with(
                'tl_foo',
                ['my_group' => 'a:2:{i:1;a:1:{s:4:"data";s:3:"foo";}i:3;a:1:{s:4:"data";N;}}'],
                ['id' => 5]
            )
        ;

        $storage = new SerializedStorage($connection, $this->getGroupMock());

        $storage->removeElement(2);
        $storage->persist();

        // Should not write to database twice
        $storage->persist();
    }

    public function testRemove(): void
    {
        $connection = $this->getConnectionMock();
        $connection
            ->expects(self::never())
            ->method('update')
        ;

        $storage = new SerializedStorage($connection, $this->getGroupMock());
        $storage->getElements();

        // Expect nop (is handled by DC_Table)
        $storage->remove();

        $storage->persist();
    }

    public function testGetField(): void
    {
        $storage = new SerializedStorage($this->getConnectionMock(), $this->getGroupMock());

        self::assertEquals('bar', $storage->getField(2, 'data'));
    }

    public function testSetField(): void
    {
        $connection = $this->getConnectionMock();
        $connection
            ->expects(self::once())
            ->method('update')
            ->with(
                'tl_foo',
                ['my_group' => 'a:3:{i:1;a:1:{s:4:"data";s:3:"foo";}i:2;a:1:{s:4:"data";s:6:"foobar";}i:3;a:1:{s:4:"data";N;}}'],
                ['id' => 5]
            )
        ;

        $storage = new SerializedStorage($connection, $this->getGroupMock());

        $storage->setField(2, 'data', 'foobar');

        self::assertEquals('foobar', $storage->getField(2, 'data'));

        $storage->persist();
    }

    public function testOrderElements(): void
    {
        $connection = $this->getConnectionMock();
        $connection
            ->expects(self::once())
            ->method('update')
            ->with(
                'tl_foo',
                ['my_group' => 'a:3:{i:2;a:1:{s:4:"data";s:3:"bar";}i:1;a:1:{s:4:"data";s:3:"foo";}i:3;a:1:{s:4:"data";N;}}'],
                ['id' => 5]
            )
        ;

        $storage = new SerializedStorage($connection, $this->getGroupMock());

        $storage->orderElements([2, 1, 3]);

        $storage->persist();
    }

    private function getGroupMock()
    {
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

        return $group;
    }

    private function getConnectionMock()
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
                1 => ['data' => 'foo'],
                2 => ['data' => 'bar'],
                3 => ['data' => null],
            ]))
        ;

        return $connection;
    }
}
