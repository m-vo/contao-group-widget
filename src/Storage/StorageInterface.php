<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

interface StorageInterface
{
    /**
     * Returns an array of element IDs in order of their appearance.
     *
     * @return array<int>
     */
    public function getElements(): array;

    /**
     * Defines the contained elements and their order by setting an array of
     * element IDs.
     *
     * @param array<int> $elementIds
     */
    public function setElements(array $elementIds): void;

    /**
     * Get the value of an element's field.
     *
     * @return mixed
     */
    public function getField(int $elementId, string $field);

    /**
     * Set the value of an element's field.
     *
     * @param mixed $value
     */
    public function setField(int $elementId, string $field, $value): void;

    /**
     * Persist changes.
     */
    public function persist(): void;
}
