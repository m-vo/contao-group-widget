<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\EventListener;

use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\EventListener\GroupWidgetListener;
use Mvo\ContaoGroupWidget\Group\Group;
use Mvo\ContaoGroupWidget\Group\Registry;
use Mvo\ContaoGroupWidget\Tests\Stubs\ArrayIteratorAggregate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class GroupWidgetListenerTest extends TestCase
{
    /**
     * @dataProvider provideEnvironments
     */
    public function testInitializeGroupRegistersCallbacksAndAssets(string $act, array $fields, bool $registered): void
    {
        $request = new Request(['act' => $act]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $twig = $this->createMock(Environment::class);
        $connection = $this->createMock(Connection::class);

        $listener = new GroupWidgetListener(
            $requestStack,
            new Registry($twig, $requestStack, $connection, new ArrayIteratorAggregate()),
            $twig,
        );

        $GLOBALS['TL_DCA']['tl_foo']['fields'] = $fields;

        $listener->initializeGroups('tl_foo');

        if ($registered) {
            self::assertSame(
                [[GroupWidgetListener::class, 'onLoadDataContainer']],
                $GLOBALS['TL_DCA']['tl_foo']['config']['onload_callback'],
            );

            self::assertSame(
                [[GroupWidgetListener::class, 'onSubmitDataContainer']],
                $GLOBALS['TL_DCA']['tl_foo']['config']['onsubmit_callback'],
            );

            self::assertSame(
                [[GroupWidgetListener::class, 'onDeleteDataContainer']],
                $GLOBALS['TL_DCA']['tl_foo']['config']['ondelete_callback'],
            );

            self::assertSame(
                'bundles/mvocontaogroupwidget/backend.min.js',
                $GLOBALS['TL_JAVASCRIPT']['mvo-group-widget'],
            );

            self::assertSame(
                'bundles/mvocontaogroupwidget/backend.min.css',
                $GLOBALS['TL_CSS']['mvo-group-widget'],
            );
        } else {
            self::assertArrayNotHasKey('config', $GLOBALS['TL_DCA']['tl_foo']);
            self::assertArrayNotHasKey('TL_JAVASCRIPT', $GLOBALS);
            self::assertArrayNotHasKey('TL_CSS', $GLOBALS);
        }

        unset($GLOBALS['TL_DCA'], $GLOBALS['TL_JAVASCRIPT'], $GLOBALS['TL_CSS']);
    }

    public static function provideEnvironments(): iterable
    {
        yield 'existing group in edit mode' => [
            'edit',
            [
                'foo' => ['inputType' => 'group'],
            ],
            true,
        ];

        yield 'existing group in delete mode' => [
            'delete',
            [
                'foo' => ['inputType' => 'group'],
            ],
            true,
        ];

        yield 'multiple groups' => [
            'edit',
            [
                'bar' => ['inputType' => 'text'],
                'foo' => ['inputType' => 'group'],
                'foobar' => ['inputType' => 'group'],
            ],
            true,
        ];

        yield 'missing group in edit mode' => [
            'edit',
            [
                'foo' => ['inputType' => 'text'],
            ],
            false,
        ];

        yield 'missing group in delete mode' => [
            'delete',
            [
                'foo' => ['inputType' => 'text'],
            ],
            false,
        ];

        yield 'invalid mode' => [
            'something',
            [
                'foo' => ['inputType' => 'group'],
            ],
            false,
        ];
    }

    public function testOnLoadDataContainer(): void
    {
        $groupBar = $this->createMock(Group::class);
        $groupBar
            ->expects(self::once())
            ->method('expand')
            ->with('default', false)
        ;

        $groupFooBar = $this->createMock(Group::class);
        $groupFooBar
            ->expects(self::exactly(2))
            ->method('expand')
            ->withConsecutive(
                ['other', false],
                ['sub', true],
            )
        ;

        $groupFooBar
            ->expects(self::once())
            ->method('setElements')
            ->with([2, 4, -1])
        ;

        $registry = $this->createMock(Registry::class);
        $registry
            ->method('getGroupFields')
            ->with('tl_foo')
            ->willReturn(['bar', 'foobar'])
        ;

        $registry
            ->method('getGroup')
            ->willReturnCallback(
                static function (string $table, int $rowId, string $name) use ($groupFooBar, $groupBar) {
                    self::assertSame('tl_foo', $table);
                    self::assertSame(5, $rowId);

                    $groups = [
                        'bar' => $groupBar,
                        'foobar' => $groupFooBar,
                    ];

                    return $groups[$name];
                },
            )
        ;

        $request = new Request();
        $request->request->set('group-widget__foobar', '2,4,-1');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new GroupWidgetListener(
            $requestStack,
            $registry,
            $this->createMock(Environment::class),
        );

        $dataContainer = $this->createMock(DataContainer::class);
        $dataContainer
            ->method('getPalette')
            ->willReturn('foo,bar;[sub],foobar,[EOF]')
        ;

        $dataContainer
            ->method('__get')
            ->willReturnCallback(
                static function (string $key) {
                    $properties = [
                        'table' => 'tl_foo',
                        'id' => '5',
                    ];

                    return $properties[$key];
                },
            )
        ;

        $GLOBALS['TL_DCA']['tl_foo'] = [
            'palettes' => [
                '__selector' => ['something'],
                'default' => 'foo, bar  ;sub', // spaces should get normalized
                'other' => 'foobar;baz',
            ],
            'subpalettes' => [
                'sub' => 'foobar',
            ],
        ];

        $listener->onLoadDataContainer($dataContainer);

        unset($GLOBALS['TL_DCA']);
    }

    public function testOnSubmitDataContainer(): void
    {
        $groups = array_map(
            function ($_) {
                $group = $this->createMock(Group::class);
                $group
                    ->expects(self::once())
                    ->method('persist')
                ;

                return $group;
            },
            range(1, 3),
        );

        $registry = $this->createMock(Registry::class);
        $registry
            ->method('getInitializedGroups')
            ->with('tl_foo', 99)
            ->willReturn($groups)
        ;

        $listener = new GroupWidgetListener(
            $this->createMock(RequestStack::class),
            $registry,
            $this->createMock(Environment::class),
        );

        $dataContainer = $this->createMock(DataContainer::class);
        $dataContainer
            ->method('__get')
            ->willReturnCallback(
                static function (string $key) {
                    $properties = [
                        'table' => 'tl_foo',
                        'id' => '99',
                    ];

                    return $properties[$key];
                },
            )
        ;

        $listener->onSubmitDataContainer($dataContainer);
    }

    public function testOnDeleteDataContainer(): void
    {
        $groups = array_map(
            function ($_) {
                $group = $this->createMock(Group::class);
                $group
                    ->expects(self::once())
                    ->method('remove')
                ;

                return $group;
            },
            range(1, 3),
        );

        $registry = $this->createMock(Registry::class);
        $registry
            ->method('getInitializedGroups')
            ->with('tl_foo', 100)
            ->willReturn($groups)
        ;

        $listener = new GroupWidgetListener(
            $this->createMock(RequestStack::class),
            $registry,
            $this->createMock(Environment::class),
        );

        $dataContainer = $this->createMock(DataContainer::class);
        $dataContainer
            ->method('__get')
            ->willReturnCallback(
                static function (string $key) {
                    $properties = [
                        'table' => 'tl_foo',
                        'id' => '100',
                    ];

                    return $properties[$key];
                },
            )
        ;

        $listener->onDeleteDataContainer($dataContainer);
    }

    public function testOnLoadGroupField(): void
    {
        $group = $this->createMock(Group::class);
        $group
            ->expects(self::once())
            ->method('getField')
            ->with(4, 'myfield')
        ;

        $registry = $this->createMock(Registry::class);
        $registry
            ->method('getGroup')
            ->with('tl_foo', 42, 'my_group_a')
            ->willReturn($group)
        ;

        $listener = new GroupWidgetListener(
            $this->createMock(RequestStack::class),
            $registry,
            $this->createMock(Environment::class),
        );

        $dataContainer = $this->createMock(DataContainer::class);
        $dataContainer
            ->method('__get')
            ->willReturnCallback(
                static function (string $key) {
                    $properties = [
                        'table' => 'tl_foo',
                        'id' => '42',
                        'field' => 'my_group_a__myfield__4',
                    ];

                    return $properties[$key];
                },
            )
        ;

        $listener->onLoadGroupField('', $dataContainer);
    }

    public function testOnStoreGroupField(): void
    {
        $group = $this->createMock(Group::class);
        $group
            ->expects(self::once())
            ->method('setField')
            ->with(4, 'myfield', 'some value')
        ;

        $registry = $this->createMock(Registry::class);
        $registry
            ->method('getGroup')
            ->with('tl_foo', 123, 'my_group_b')
            ->willReturn($group)
        ;

        $listener = new GroupWidgetListener(
            $this->createMock(RequestStack::class),
            $registry,
            $this->createMock(Environment::class),
        );

        $dataContainer = $this->createMock(DataContainer::class);
        $dataContainer
            ->method('__get')
            ->willReturnCallback(
                static function (string $key) {
                    $properties = [
                        'table' => 'tl_foo',
                        'id' => '123',
                        'field' => 'my_group_b__myfield__4',
                    ];

                    return $properties[$key];
                },
            )
        ;

        self::assertNull(
            $listener->onStoreGroupField('some value', $dataContainer),
        );
    }
}
