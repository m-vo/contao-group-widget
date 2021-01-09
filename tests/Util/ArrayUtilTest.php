<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

use Mvo\ContaoGroupWidget\Util\ArrayUtil;
use PHPUnit\Framework\TestCase;

class ArrayUtilTest extends TestCase
{
    /**
     * @dataProvider provideArrays
     */
    public function testMergePropertiesRecursive(array $left, array $right, array $expected): void
    {
        self::assertEquals($expected, ArrayUtil::mergePropertiesRecursive($left, $right));
    }

    public function provideArrays(): Generator
    {
        yield 'all empty' => [
            [], [], [],
        ];

        yield 'empty left' => [
            [],
            ['foo' => 'bar'],
            ['foo' => 'bar'],
        ];

        yield 'empty right' => [
            ['foo' => 'bar'],
            [],
            ['foo' => 'bar'],
        ];

        yield 'merge one dimension' => [
            ['foo' => 'bar', 'bar' => 2],
            ['foo' => 'other', 'foobar' => true],
            ['foo' => 'other', 'bar' => 2, 'foobar' => true],
        ];

        yield 'merge multi dimensional' => [
            [
                'label' => ['foo', 'bar'],
                'eval' => [
                    'mandatory' => true,
                    'tl_class' => 'w50',
                ],
            ],
            [
                'eval' => [
                    'mandatory' => false,
                ],
            ],
            [
                'label' => ['foo', 'bar'],
                'eval' => [
                    'mandatory' => false,
                    'tl_class' => 'w50',
                ],
            ],
        ];
    }
}
