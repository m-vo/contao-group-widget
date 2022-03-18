<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Stubs;

class ArrayIteratorAggregate implements \IteratorAggregate
{
    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->values);
    }
}
