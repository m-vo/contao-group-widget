<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\Group\Group;

/**
 * Storage adapter to store group/element data into a DCA table's blob field.
 *
 * @internal
 */
final class SerializedStorage implements StorageInterface
{
    private Connection $connection;

    private Group $group;

    private ?string $originalData = null;

    private ?array $data = null;

    public function __construct(Connection $connection, Group $group)
    {
        $this->connection = $connection;
        $this->group = $group;
    }

    public function getElements(): array
    {
        return array_keys($this->getData());
    }

    public function createElement(): int
    {
        $getNextId = static function ($keys) {
            if (empty($keys)) {
                return 1;
            }

            return max(1, ...$keys) + 1;
        };

        $data = $this->getData();

        $elementId = $getNextId(array_keys($data));
        $data[$elementId] = null;

        $this->data = $this->normalizeGroupData($data);

        return $elementId;
    }

    public function removeElement(int $elementId): void
    {
        $data = $this->getData();

        if (!\array_key_exists($elementId, $data)) {
            throw new \InvalidArgumentException("Element '$elementId' does not exist.");
        }

        unset($data[$elementId]);

        $this->data = $this->normalizeGroupData($data);
    }

    public function orderElements(array $elementIds): void
    {
        $data = $this->getData();

        if (\count(array_intersect($elementIds, array_keys($data))) !== \count($data)) {
            throw new \InvalidArgumentException('Cannot order, provided elements do not match stored data.');
        }

        $this->data = $this->normalizeKeys($data, $elementIds);
    }

    public function getField(int $elementId, string $field)
    {
        $data = $this->getData();

        if (!\array_key_exists($elementId, $data)) {
            throw new \InvalidArgumentException("Element '$elementId' does not exist.");
        }

        if (!\array_key_exists($field, $data[$elementId])) {
            throw new \InvalidArgumentException("Field '$field' does not exist.");
        }

        return $data[$elementId][$field];
    }

    public function setField(int $elementId, string $field, $value): void
    {
        $data = $this->getData();

        if (!\array_key_exists($elementId, $data)) {
            throw new \InvalidArgumentException("Element '$elementId' does not exist.");
        }

        if (!\array_key_exists($field, $data[$elementId])) {
            throw new \InvalidArgumentException("Field '$field' does not exist.");
        }

        $data[$elementId][$field] = $value;

        $this->data = $data;
    }

    public function persist(): void
    {
        if (null === $this->data) {
            throw new \RuntimeException('Cannot persist, data was never loaded.');
        }

        // Serialize data, return early if nothing has changed
        $serialized = serialize($this->data);

        if ($serialized === $this->originalData) {
            return;
        }

        // Announce changes, so that a new version will be created
        $this->group->setChanged();

        // Store blob to DCA table
        $name = $this->connection->quoteIdentifier($this->group->getName());

        $this->connection->update(
            $this->group->getTable(),
            [$name => $serialized],
            ['id' => $this->group->getRowId()],
        );

        $this->originalData = $serialized;
    }

    public function remove(): void
    {
        // Storage is part of the row that gets deleted by DC_Table
    }

    private function getData(): array
    {
        if (null !== $this->data) {
            return $this->data;
        }

        // Fetch blob from DCA table
        $name = $this->connection->quoteIdentifier($this->group->getName());
        $table = $this->connection->quoteIdentifier($this->group->getTable());

        $this->originalData = $this->connection->fetchOne(
            "SELECT $name from $table WHERE id = ?",
            [$this->group->getRowId()],
        ) ?: '';

        // Deserialize and normalize it
        /** @var array $deserialized */
        $deserialized = StringUtil::deserialize($this->originalData, true);

        $normalized = $this->normalizeGroupData($deserialized);

        return $this->data = $normalized;
    }

    /**
     * Normalize a group data array to the following form:.
     *
     *  [
     *     <elementId> => [
     *        <field> => <value>,
     *        …
     *     ],
     *     …
     *  ]
     *
     * These normalizations are applied:
     *
     *  - Data under invalid element IDs is removed.
     *  - Field keys will be constrained to $fields.
     */
    private function normalizeGroupData(array $data): array
    {
        $isValidId = static fn ($id) => \is_int($id) && 0 !== $id;

        $keys = array_keys($data);

        // Make sure we're dealing with valid IDs
        foreach ($keys as $i => $key) {
            if (!$isValidId($key)) {
                unset($keys[$i]);
            }
        }

        // Apply normalization
        $data = $this->normalizeKeys($data, $keys);

        // Constrain fields
        $fields = $this->group->getFields();

        foreach ($data as $key => $fieldsData) {
            $data[$key] = $this->normalizeKeys(
                \is_array($fieldsData) ? $fieldsData : [],
                $fields,
            );
        }

        return $data;
    }

    /**
     * The output will only contain those keys of $array that are specified in $keys,
     * missing keys are added with the specified $fallbackValue. The order of the
     * resulting array matches that of $keys.
     *
     * Example:
     *   $array = ['foo' => 'foo', 'bar' => 2, 'other' => true]
     *   $keys = ['a', 'bar', 'foo']
     *
     *   ==>
     *
     *  ['a' => null, 'bar' => 2, 'foo' => 'foo]
     */
    private function normalizeKeys(array $array, array $keys, $fallbackValue = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $array[$key] ?? $fallbackValue;
        }

        return $result;
    }
}
