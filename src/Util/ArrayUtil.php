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
     * Merge two arrays recursively, but let $right overwrite properties of $left.
     */
    public static function mergePropertiesRecursive(array $left, array $right): array
    {
        $keys = array_merge(array_keys($left), array_keys($right));
        $result = [];

        foreach ($keys as $key) {
            $leftExists = \array_key_exists($key, $left);
            $rightExists = \array_key_exists($key, $right);

            $leftValue = $left[$key] ?? null;
            $rightValue = $right[$key] ?? null;

            $result[$key] = (
                static function () use ($rightValue, $leftValue, $rightExists, $leftExists) {
                    if (!$leftExists) {
                        return $rightValue;
                    }

                    if (!$rightExists) {
                        return $leftValue;
                    }

                    if (\is_array($leftValue)) {
                        return self::mergePropertiesRecursive($leftValue, (array) $rightValue);
                    }

                    return $rightValue;
                }
            )();
        }

        return $result;
    }
}
