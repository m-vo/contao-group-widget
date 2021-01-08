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
     * Creates a new element and returns its ID.
     */
    public function createElement(): int;

    /**
     * Removes an element.
     */
    public function removeElement(int $elementId): void;

    /**
     * Adjust order of elements.
     *
     * @param array<int> $elementIds
     */
    public function orderElements(array $elementIds): void;

    /**
     * Returns the value of an element's field.
     *
     * @return mixed
     */
    public function getField(int $elementId, string $field);

    /**
     * Sets the value of an element's field.
     *
     * @param mixed $value
     */
    public function setField(int $elementId, string $field, $value): void;

    /**
     * Persist changes.
     */
    public function persist(): void;
}
