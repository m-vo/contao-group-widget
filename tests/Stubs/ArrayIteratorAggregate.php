<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Stubs;

/**
 * @template-implements \IteratorAggregate<string, mixed>
 */
class ArrayIteratorAggregate implements \IteratorAggregate
{
    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->values);
    }
}
