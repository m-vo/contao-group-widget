<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Group;

use Mvo\ContaoGroupWidget\EventListener\GroupWidgetListener;
use Mvo\ContaoGroupWidget\Group\Group;
use Mvo\ContaoGroupWidget\Storage\StorageInterface;
use Mvo\ContaoGroupWidget\Tests\Stubs\DummyStorage;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Twig\Environment;

class GroupTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA']);

        parent::tearDown();
    }

    /**
     * @dataProvider provideDefinitions
     */
    public function testCreatesGroup(array $definition, \Closure $assertionCallback): void
    {
        $definition['inputType'] = 'group';

        $GLOBALS['TL_DCA']['tl_foo']['fields'] = [
            'my_group' => $definition,
            'foo' => [
                'inputType' => 'text',
                'eval' => ['tl_class' => 'w50', 'mandatory' => true],
            ],
        ];

        $group = new Group(
            $this->createMock(ContainerInterface::class),
            'tl_foo',
            123,
            'my_group'
        );

        $assertionCallback($group);
    }

    public function provideDefinitions(): \Generator
    {
        yield 'defaults' => [
            [
                'palette' => ['foo'],
            ],
            static function (Group $group): void {
                self::assertEquals('my_group', $group->getName());
                self::assertEquals('tl_foo', $group->getTable());
                self::assertEquals(123, $group->getRowId());
                self::assertEquals('', $group->getLabel());
                self::assertEquals('', $group->getDescription());
                self::assertEquals(0, $group->getMinElements());
                self::assertEquals(0, $group->getMaxElements());
                self::assertEquals(['foo'], $group->getFields());
            },
        ];

        yield 'label/description' => [
            [
                'label' => ['my group', 'pretty nice'],
                'palette' => ['foo'],
            ],
            static function (Group $group): void {
                self::assertEquals('my group', $group->getLabel());
                self::assertEquals('pretty nice', $group->getDescription());
            },
        ];

        yield 'min/max' => [
            [
                'min' => 2,
                'max' => 10,
                'palette' => ['foo'],
            ],
            static function (Group $group): void {
                self::assertEquals(2, $group->getMinElements());
                self::assertEquals(10, $group->getMaxElements());
            },
        ];

        yield 'implicit palette' => [
            [
                'fields' => [
                    'bar' => [
                        'inputType' => 'text',
                    ],
                ],
            ],
            static function (Group $group): void {
                self::assertEquals(['bar'], $group->getFields());
            },
        ];

        yield 'fields and palette' => [
            [
                'palette' => ['foo', 'bar'],
                'fields' => [
                    'bar' => [
                        'inputType' => 'text',
                    ],
                ],
            ],
            static function (Group $group): void {
                self::assertEquals(['foo', 'bar'], $group->getFields());
            },
        ];

        yield 'merged fields' => [
            [
                'fields' => [
                    'foo' => [
                        'eval' => ['mandatory' => false],
                    ],
                ],
            ],
            static function (Group $group): void {
                self::assertEquals(['foo'], $group->getFields());
                self::assertEquals([
                    'inputType' => 'text',
                    'eval' => [
                        'tl_class' => 'w50',
                        'mandatory' => false,
                    ],
                ], $group->getFieldDefinition('foo'));
            },
        ];

        yield 'raw definition' => [
            [
                'palette' => ['foo'],
                'foobar' => ['bar' => 'baz'],
            ],
            static function (Group $group): void {
                self::assertEquals(['bar' => 'baz'], $group->getDefinition('foobar'));
            },
        ];
    }

    /**
     * @dataProvider provideInvalidDefinitions
     */
    public function testThrowsWithInvalidDefinition(array $definition, string $exception): void
    {
        $definition['inputType'] = 'group';

        $GLOBALS['TL_DCA']['tl_foo']['fields'] = [
            'my_group' => $definition,
            'foo' => [
                'inputType' => 'text',
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($exception);

        new Group(
            $this->createMock(ContainerInterface::class),
            'tl_foo',
            123,
            'my_group'
        );
    }

    public function provideInvalidDefinitions(): \Generator
    {
        yield 'no fields/palette' => [
            [],
            "Invalid definition for group 'my_group': Keys 'palette' and 'fields' cannot both be empty.",
        ];

        yield 'empty palette' => [
            [
                'palette' => [],
            ],
            "Invalid definition for group 'my_group': Keys 'palette' and 'fields' cannot both be empty.",
        ];

        yield 'string palette' => [
            [
                'palette' => 'foo',
            ],
            "Invalid definition for group 'my_group': Key 'palette' must be an array.",
        ];

        yield 'bad field reference' => [
            [
                'palette' => ['foo', 'bar'],
            ],
            "Invalid definition for group 'my_group': Field 'bar' does not exist.",
        ];

        yield 'min out of range' => [
            [
                'palette' => ['foo'],
                'min' => -10,
            ],
            "Invalid definition for group 'my_group': Key 'min' cannot be less than 0.",
        ];

        yield 'max smaller than min' => [
            [
                'palette' => ['foo'],
                'min' => 4,
                'max' => 3,
            ],
            "Invalid definition for group 'my_group': Key 'max' cannot be less than 'min'.",
        ];
    }

    public function testExpandsPalette(): void
    {
        $GLOBALS['TL_DCA']['tl_foo']['palettes']['default'] = '{some_legend},foobar;my_group';

        $GLOBALS['TL_DCA']['tl_foo']['fields'] = [
            'my_group' => [
                'inputType' => 'group',
                'palette' => ['foo', 'bar'],
                'fields' => [
                    'bar' => [
                        'inputType' => 'random',
                    ],
                ],
            ],
            'foo' => [
                'inputType' => 'text',
            ],
        ];

        $twig = $this->createMock(Environment::class);
        $locator = $this->createMock(ContainerInterface::class);

        $locator
            ->method('get')
            ->with('twig')
            ->willReturn($twig)
        ;

        $group = new Group(
            $locator,
            'tl_foo',
            123,
            'my_group'
        );

        $group->setStorage(new DummyStorage());
        $group->expand('default');

        $expectedFooDefinition = [
            'inputType' => 'text',
            'eval' => [
                'doNotSaveEmpty' => true,
            ],
            'sql' => null,
            'load_callback' => [[GroupWidgetListener::class, 'onLoadGroupField']],
            'save_callback' => [[GroupWidgetListener::class, 'onStoreGroupField']],
            'label' => null,
        ];

        $expectedBarDefinition = [
            'inputType' => 'random',
            'eval' => [
                'doNotSaveEmpty' => true,
            ],
            'sql' => null,
            'load_callback' => [[GroupWidgetListener::class, 'onLoadGroupField']],
            'save_callback' => [[GroupWidgetListener::class, 'onStoreGroupField']],
            'label' => null,
        ];

        $expectedFields = [
            'my_group__foo__1' => $expectedFooDefinition,
            'my_group__bar__1' => $expectedBarDefinition,
            'my_group__foo__2' => $expectedFooDefinition,
            'my_group__bar__2' => $expectedBarDefinition,
        ];

        $expectedGroupDelimiterFields = [
            'my_group__(start)',
            'my_group__(el_start)__1',
            'my_group__(el_end)__1',
            'my_group__(el_start)__2',
            'my_group__(el_end)__2',
            'my_group__(end)',
        ];

        foreach ($expectedFields as $field => $definition) {
            self::assertArrayHasKey($field, $GLOBALS['TL_DCA']['tl_foo']['fields']);
            self::assertSame($definition, $GLOBALS['TL_DCA']['tl_foo']['fields'][$field]);
        }

        foreach ($expectedGroupDelimiterFields as $field) {
            self::assertArrayHasKey($field, $GLOBALS['TL_DCA']['tl_foo']['fields']);
            self::assertInstanceOf(
                \Closure::class,
                $GLOBALS['TL_DCA']['tl_foo']['fields'][$field]['input_field_callback']
            );
        }

        $expectedPalette = '{some_legend},foobar;'.
            'my_group__(start),'.
            'my_group__(el_start)__1,my_group__foo__1,my_group__bar__1,my_group__(el_end)__1,'.
            'my_group__(el_start)__2,my_group__foo__2,my_group__bar__2,my_group__(el_end)__2,'.
            'my_group__(end)';

        self::assertEquals($expectedPalette, $GLOBALS['TL_DCA']['tl_foo']['palettes']['default']);
    }

    public function testGetField(): void
    {
        $group = $this->getDummyGroup();

        $storage = $this->createMock(StorageInterface::class);
        $storage
            ->expects(self::once())
            ->method('getField')
            ->with(123, 'bar')
            ->willReturn('data')
        ;

        $group->setStorage($storage);

        self::assertEquals('data', $group->getField(123, 'bar'));
    }

    public function testSetField(): void
    {
        $group = $this->getDummyGroup();

        $storage = $this->createMock(StorageInterface::class);
        $storage
            ->expects(self::once())
            ->method('setField')
            ->with(123, 'bar', 'data')
        ;

        $group->setStorage($storage);

        $group->setField(123, 'bar', 'data');
    }

    public function testSetElements(): void
    {
        $group = $this->getDummyGroup();

        $storage = $this->createMock(StorageInterface::class);

        // Simulate transition [1, 5, 3] with [5, 2, 1, -1] --> [5, 1, 6]
        //  - should create new item (6)
        //  - should remove item 3
        //  - should ignore unmapped (2)
        //  - should order (5, 1, 6)

        $storage
            ->method('getElements')
            ->willReturn([1, 5, 3])
        ;

        $storage
            ->expects(self::once())
            ->method('createElement')
            ->willReturn(6)
        ;

        $storage
            ->expects(self::once())
            ->method('removeElement')
            ->with(3)
        ;

        $storage
            ->expects(self::once())
            ->method('orderElements')
            ->with([5, 1, 6])
        ;

        $group->setStorage($storage);

        $group->setElements([2, 5, 1, -1]);
    }

    public function testSetElementsWithMinConstraint(): void
    {
        $group = $this->getDummyGroup(['min' => 3]);

        $storage = $this->createMock(StorageInterface::class);

        $storage
            ->method('getElements')
            ->willReturn([1])
        ;

        $storage
            ->expects(self::exactly(2))
            ->method('createElement')
            ->willReturnOnConsecutiveCalls(2, 3)
        ;

        $group->setStorage($storage);

        $group->setElements([1]);
    }

    public function testSetElementsWithMaxConstraint(): void
    {
        $group = $this->getDummyGroup(['max' => 3]);

        $storage = $this->createMock(StorageInterface::class);

        $storage
            ->method('getElements')
            ->willReturn([1, 2, 3, 4, 5])
        ;

        $storage
            ->expects(self::exactly(2))
            ->method('removeElement')
            ->willReturnOnConsecutiveCalls(4, 5)
        ;

        $group->setStorage($storage);

        $group->setElements([1, 2, 3, 4, 5]);
    }

    public function testRemove(): void
    {
        $group = $this->getDummyGroup();

        $storage = $this->createMock(StorageInterface::class);
        $storage
            ->expects(self::once())
            ->method('remove')
        ;

        $group->setStorage($storage);

        $group->remove();
    }

    public function testPersist(): void
    {
        $group = $this->getDummyGroup();

        $storage = $this->createMock(StorageInterface::class);
        $storage
            ->expects(self::once())
            ->method('persist')
        ;

        $group->setStorage($storage);

        $group->persist();
    }

    private function getDummyGroup($definition = []): Group
    {
        $GLOBALS['TL_DCA']['tl_foo']['fields'] = [
            'my_group' => array_merge(
                [
                    'inputType' => 'group',
                    'palette' => ['foo'],
                ],
                $definition
            ),
            'foo' => [
                'inputType' => 'text',
            ],
        ];

        return new Group(
            $this->createMock(ContainerInterface::class),
            'tl_foo',
            123,
            'my_group'
        );
    }
}
