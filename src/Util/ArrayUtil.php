<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Util;

/**
 * @internal
 */
final class ArrayUtil
{
    /**
     * The output will only contain those keys of $array that are specified
     * in $keys, missing keys are added with the specified $fallbackValue.
     * The order of the resulting array matches that of $keys.
     *
     * Example:
     *   $array = ['foo' => 'foo', 'bar' => 2, 'other' => true]
     *   $keys = ['a', 'bar', 'foo']
     *
     *   ==>
     *
     *  ['a' => null, 'bar' => 2, 'foo' => 'foo]
     */
    public static function normalizeKeys(array $array, array $keys, $fallbackValue = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $array[$key] ?? $fallbackValue;
        }

        return $result;
    }
}
