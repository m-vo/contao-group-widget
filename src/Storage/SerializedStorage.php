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
use Mvo\ContaoGroupWidget\Util\ArrayUtil;
use Mvo\ContaoGroupWidget\Util\ElementId;

/**
 * Storage adapter to store element data into a DCA table's blob field.
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

    public function setElements(array $elementIds): void
    {
        // Generate a new ID for special value 0
        if (false !== ($position = array_search(0, $elementIds, true))) {
            $elementIds[$position] = ElementId::getNextId($elementIds);
        }

        // Reorder/remove/add elements
        $data = ArrayUtil::normalizeKeys($this->getData(), $elementIds);

        $this->data = $this->normalizeGroupData($data);
    }

    public function getField(int $elementId, string $field)
    {
        $data = $this->getData();

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

        // Store blob to DCA table
        $name = $this->connection->quoteIdentifier($this->group->getName());

        $this->connection->update(
            $this->group->getTable(),
            [$name => $serialized],
            ['id' => $this->group->getRowId()]
        );

        $this->originalData = $serialized;
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
            [$this->group->getRowId()]
        );

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
     *  - If specified, the size of the first dimension will be constrained to
     *    a minimum/maximum of elements. Missing elements will be appended
     *    following the normalizations from above.
     */
    private function normalizeGroupData(array $data): array
    {
        $keys = array_keys($data);

        // Make sure we're dealing with valid IDs
        foreach ($keys as $i => $key) {
            if (!ElementId::validate($key)) {
                unset($keys[$i]);
            }
        }

        // Apply min/max constraints
        $definition = $this->group->getDefinition();

        $minElements = $definition->getMinElements();
        $maxElements = $definition->getMaxElements();
        $size = \count($keys);

        if ($minElements > 0 && $size < $minElements) {
            for ($i = 0; $i < $minElements - $size; ++$i) {
                $keys[] = ElementId::getNextId($keys);
            }
        }

        if ($maxElements > 0 && $size > $maxElements) {
            $keys = \array_slice($keys, 0, $maxElements);
        }

        // Apply normalization
        $data = ArrayUtil::normalizeKeys($data, $keys);

        // Constrain fields
        $fields = $definition->getFields();

        foreach ($data as $key => $fieldsData) {
            $data[$key] = ArrayUtil::normalizeKeys(
                \is_array($fieldsData) ? $fieldsData : [],
                $fields
            );
        }

        return $data;
    }
}
