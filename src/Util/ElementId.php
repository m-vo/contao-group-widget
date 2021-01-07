<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Util;

class ElementId
{
    /**
     * Check if a value is a valid element ID.
     */
    public static function validate($value): bool
    {
        return \is_int($value) && 0 !== $value;
    }

    /**
     * Get the next available element ID.
     *
     * @param array<int> $keys
     */
    public static function getNextId(array $keys): int
    {
        // Note: The output of this method must be deterministic
        if (empty($keys)) {
            return 1;
        }

        return max(...$keys) + 1;
    }
}
