<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Group;

use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\Group\Registry;
use Mvo\ContaoGroupWidget\Tests\Stubs\ArrayIteratorAggregate;
use Mvo\ContaoGroupWidget\Tests\Stubs\DummyStorage;
use Mvo\ContaoGroupWidget\Tests\Stubs\DummyStorageFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class RegistryTest extends TestCase
{
    public function testGetGroupFields(): void
    {
        $GLOBALS['TL_DCA']['tl_foo']['fields'] = [
            'foo' => [
                'inputType' => 'text',
            ],
            'my_group' => [
                'inputType' => 'group',
                'palette' => ['foo'],
            ],
            'bar' => [
            ],
        ];

        $registry = new Registry(
            $this->createMock(Environment::class),
            $this->createMock(RequestStack::class),
            $this->createMock(Connection::class),
            new ArrayIteratorAggregate()
        );

        self::assertSame(['my_group'], $registry->getGroupFields('tl_foo'));

        unset($GLOBALS['TL_DCA']);
    }

    public function testGetGroup(): void
    {
        $GLOBALS['TL_DCA']['tl_foo']['fields'] = [
            'foo' => [
                'inputType' => 'text',
            ],
            'my_group' => [
                'inputType' => 'group',
                'palette' => ['foo'],
                'min' => 1,
                'max' => 5,
                'storage' => 'dummy',
            ],
        ];

        $dummyStorageFactory = $this->createPartialMock(DummyStorageFactory::class, ['create']);
        $dummyStorageFactory
            ->method('create')
            ->willReturn(new DummyStorage())
        ;

        $registry = new Registry(
            $this->createMock(Environment::class),
            $this->createMock(RequestStack::class),
            $this->createMock(Connection::class),
            new ArrayIteratorAggregate(['dummy' => $dummyStorageFactory])
        );

        $group = $registry->getGroup('tl_foo', 123, 'my_group');

        self::assertSame('tl_foo', $group->getTable());
        self::assertSame('my_group', $group->getName());
        self::assertSame(123, $group->getRowId());
        self::assertSame(['foo'], $group->getFields());

        // Test caches groups and returns the same instance
        $group2 = $registry->getGroup('tl_foo', 123, 'my_group');

        self::assertSame($group, $group2);

        // Test contains initialized instances exactly once
        $groups = $registry->getInitializedGroups('tl_foo', 123);

        self::assertCount(1, $groups);
        self::assertSame($group, $groups[0]);

        unset($GLOBALS['TL_DCA']);
    }
}
