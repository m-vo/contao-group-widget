<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\EventListener\GroupWidgetListener;

final class Group
{
    // Keys that can be defined in the group field's eval section
    public const USER_KEY_MIN_ELEMENTS = 'min';
    public const USER_KEY_MAX_ELEMENTS = 'max';
    public const USER_KEY_PALETTE = 'palette';

    // Internal keys for virtual elements
    public const KEY_COMPONENT_TYPE = '_type';
    public const KEY_ELEMENT_ID = '_id';
    public const KEY_GROUP = '_group';

    // Component types
    public const TYPE_START = 'start';
    public const TYPE_ELEMENT_START = 'el_start';
    public const TYPE_ELEMENT_END = 'el_end';
    public const TYPE_END = 'end';

    private const MAX_ITEMS = 200;

    // Bound target
    private string $name;
    private string $table;
    private int $rowId;

    // Definition
    private array $fields;
    private int $min;
    private int $max;

    // Data
    private array $data = [];
    private bool $dirty = true;

    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection, string $table, int $rowId, string $name)
    {
        $this->connection = $connection;

        $this->table = $table;
        $this->rowId = $rowId;
        $this->name = $name;

        $attributes = &$GLOBALS['TL_DCA'][$this->table]['fields'][$name]['eval'];

        $this->fields = $attributes[self::USER_KEY_PALETTE] ?? [];
        $this->min = max($attributes[self::USER_KEY_MIN_ELEMENTS] ?? 0, 0);
        $this->max = min($attributes[self::USER_KEY_MAX_ELEMENTS] ?? 0, self::MAX_ITEMS);
    }

    /**
     * Apply a linear order [id1, id5, id3, …]. IDs that are not included will
     * be removed while adding unknown IDs will create elements.
     *
     * @param array<int, int> $order
     */
    public function updateData(array $order): self
    {
        $this->loadData();

        // Generate a new ID for special value 0
        if (false !== ($position = array_search(0, $order, true))) {
            $order[$position] = $this->getNextId($order);
        }

        $data = $this->constrainKeys($this->data, $order, []);

        $this->persist($data);

        return $this;
    }

    /**
     * Get the data of a the nth field.
     *
     * @return mixed
     */
    public function getField(int $index, string $field)
    {
        $this->loadData();

        return $this->data[$index][$field] ?? null;
    }

    /**
     * Set the data of a the nth field.
     */
    public function setField(int $id, string $field, $value): self
    {
        $this->loadData();

        $data = $this->data;
        $data[$id][$field] = $value;

        $this->persist($data);

        return $this;
    }

    /**
     * Expand palette + add virtual fields:.
     *
     *  <group>
     *
     *   ==>
     *
     *  <group>,
     *    <element start 4>, <fieldA 4>, <fieldB 4>, […], <element end 4>
     *    <element start 1>, <fieldA 1>, <fieldB 1>, […], <element end 1>
     *    […]
     *  <group end>
     */
    public function expand(string $palette): self
    {
        $this->loadData();

        $newPaletteItems = [];

        foreach (array_keys($this->data) as $index) {
            $newPaletteItems[] = $this->addGroupStructureField(self::TYPE_ELEMENT_START, $index);

            foreach ($this->fields as $field) {
                $newPaletteItems[] = $this->addVirtualField($field, $index);
            }

            $newPaletteItems[] = $this->addGroupStructureField(self::TYPE_ELEMENT_END, $index);
        }

        $newPaletteItems[] = $this->addGroupStructureField(self::TYPE_END);

        PaletteManipulator::create()
            ->addField($newPaletteItems, $this->name)
            ->applyToPalette($palette, $this->table);

        return $this;
    }

    private function loadData(): void
    {
        if (!$this->dirty) {
            return;
        }

        $table = $this->connection->quoteIdentifier($this->table);
        $name = $this->connection->quoteIdentifier($this->name);

        /** @var array $data */
        $data = StringUtil::deserialize(
            $this->connection->fetchOne("SELECT $name from $table WHERE id = ?", [$this->rowId]),
            true
        );

        $this->data = $this->constrain($data);
        $this->dirty = false;
    }

    private function persist(array $data): void
    {
        $this->dirty = false;

        $data = $this->constrain($data);

        if (serialize($this->data) === ($serializedData = serialize($data))) {
            return;
        }

        $this->data = $data;

        $name = $this->connection->quoteIdentifier($this->name);

        $this->connection->update(
            $this->table,
            [$name => $serializedData],
            ['id' => $this->rowId]
        );
    }

    private function constrain(array $data): array
    {
        $size = \count($data);
        $keys = array_keys($data);

        // Make sure we're dealing with valid IDs
        foreach ($keys as $i => $key) {
            if (!\is_int($key) || $key < 1 || $key > self::MAX_ITEMS) {
                unset($keys[$i]);
            }
        }

        // Constrain to min/max values
        if ($this->min > 0 && $size < $this->min) {
            for ($i = 0; $i < $this->min - $size; ++$i) {
                $keys[] = $this->getNextId($keys);
            }
        }

        if ($this->max > 0 && $size > $this->max) {
            $keys = \array_slice($keys, 0, $this->max);
        }

        $data = $this->constrainKeys($data, $keys, []);

        // Constrain fields
        foreach ($data as $key => $value) {
            if (!\is_array($value)) {
                $data[$key] = null;

                continue;
            }

            $data[$key] = $this->constrainKeys($value, $this->fields, null);
        }

        return $data;
    }

    private function constrainKeys(array $source, array $keys, $fallbackValue): array
    {
        $target = [];

        foreach ($keys as $key) {
            $target[$key] = $source[$key] ?? $fallbackValue;
        }

        return $target;
    }

    private function getNextId(array $ids): int
    {
        do {
            $id = random_int(1, self::MAX_ITEMS);
        } while (\in_array($id, $ids, true));

        return $id;
    }

    private function addGroupStructureField(string $type, int $index = null): string
    {
        $newName = "{$this->name}__({$type})" . (null !== $index ? "__{$index}" : '');

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = [
            'inputType' => 'group',
            'eval' => [
                self::KEY_COMPONENT_TYPE => $type,
                self::KEY_ELEMENT_ID => $index,
                self::KEY_GROUP => $this->name,
            ],
        ];

        return $newName;
    }

    private function addVirtualField(string $field, int $index): string
    {
        $newName = "{$this->name}__{$field}__{$index}";

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = array_merge_recursive(
            $GLOBALS['TL_DCA'][$this->table]['fields'][$field],
            [
                'label' => &$GLOBALS['TL_LANG'][$this->table][$field],
                'eval' => [
                    'doNotSaveEmpty' => true,
                ],
                'load_callback' => [[GroupWidgetListener::class, 'onLoadGroupElement']],
                'save_callback' => [[GroupWidgetListener::class, 'onStoreGroupElement']],
                'sql' => null,
            ]
        );

        return $newName;
    }
}
