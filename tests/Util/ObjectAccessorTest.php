<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Util;

use Mvo\ContaoGroupWidget\Util\ObjectAccessor;
use PHPUnit\Framework\TestCase;

class ObjectAccessorTest extends TestCase
{
    public function testGetValues(): void
    {
        $object = new class() {
            private $foo = 'foo';
            public $bar = 'bar';

            public function getBar(): string
            {
                return 'getBar';
            }

            public function getFooBar(): string
            {
                return 'getFooBar';
            }
        };

        $accessor = new ObjectAccessor();

        self::assertEquals('foo', $accessor->getValue($object, 'foo'));
        self::assertEquals('getBar', $accessor->getValue($object, 'bar'));
        self::assertEquals('getFooBar', $accessor->getValue($object, 'fooBar'));
    }

    public function testSetValues(): void
    {
        $object = new class() {
            private $foo = '';
            public $bar = '';
            private $fooBar = '';
            private $other = '';

            public function setFoo(string $foo): void
            {
                $this->foo = $foo;
            }

            public function setOther(string $value): void
            {
                $this->other = $value;
            }
        };

        $accessor = new ObjectAccessor();

        $accessor->setValue($object, 'foo', 'foo');
        $accessor->setValue($object, 'bar', 'bar');
        $accessor->setValue($object, 'fooBar', 'fooBar');
        $accessor->setValue($object, 'other', 'other');

        self::assertEquals('foo', $accessor->getValue($object, 'foo'));
        self::assertEquals('bar', $accessor->getValue($object, 'bar'));
        self::assertEquals('fooBar', $accessor->getValue($object, 'fooBar'));
        self::assertEquals('other', $accessor->getValue($object, 'other'));
    }

    public function testThrowsWhenGettingMissingProperty(): void
    {
        $object = new class() {
            private $bar;
        };

        $accessor = new ObjectAccessor();

        $this->expectException(\ReflectionException::class);
        $this->expectErrorMessage('Property class@anonymous::$foo does not exist');

        $accessor->getValue($object, 'foo');
    }

    public function testThrowsWhenSettingMissingProperty(): void
    {
        $object = new class() {
            private $bar;
        };

        $accessor = new ObjectAccessor();

        $this->expectException(\ReflectionException::class);
        $this->expectErrorMessage('Property class@anonymous::$foo does not exist');

        $accessor->setValue($object, 'foo', 'foo');
    }

    public function testSupports(): void
    {
        $object = new class() {
            private $foo = 'foo';
            public $bar = 'bar';

            public function getFooBar(): string
            {
                return 'getFooBar';
            }

            public function setFooBar($value): void
            {
            }

            public function getThing(): string
            {
                return 'getThing';
            }
        };

        $accessor = new ObjectAccessor();

        self::assertTrue($accessor->supports($object, 'foo'));
        self::assertTrue($accessor->supports($object, 'bar'));
        self::assertTrue($accessor->supports($object, 'fooBar'));

        // No property
        self::assertFalse($accessor->supports($object, 'other'));

        // Missing setter
        self::assertFalse($accessor->supports($object, 'thing'));
    }
}
