<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

/**
 * Dummy storage adapter that doesn't persist anything.
 *
 * @internal
 */
final class NullStorage implements StorageInterface
{
    private int $currentId = 1;

    public function getElements(): array
    {
        return [];
    }

    public function createElement(): int
    {
        return $this->currentId++;
    }

    public function removeElement(int $elementId): void
    {
    }

    public function orderElements(array $elementIds): void
    {
    }

    public function getField(int $elementId, string $field)
    {
        return null;
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
