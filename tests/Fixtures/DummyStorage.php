<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Fixtures;

use Mvo\ContaoGroupWidget\Storage\StorageInterface;

class DummyStorage implements StorageInterface
{
    public function getElements(): array
    {
        return [1, 2];
    }

    public function createElement(): int
    {
        return 3;
    }

    public function removeElement(int $elementId): void
    {
    }

    public function orderElements(array $elementIds): void
    {
    }

    public function getField(int $elementId, string $field): void
    {
    }

    public function setField(int $elementId, string $field, $value): void
    {
    }

    public function persist(): void
    {
    }

    public function remove(): void
    {
    }
}
