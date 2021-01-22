<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Mvo\ContaoGroupWidget\EventListener\GroupWidgetListener;
use Mvo\ContaoGroupWidget\Storage\StorageInterface;
use Mvo\ContaoGroupWidget\Util\ArrayUtil;
use Psr\Container\ContainerInterface;
use Twig\Environment;

/**
 * @final
 */
class Group
{
    private ContainerInterface $locator;

    private string $name;
    private string $table;
    private int $rowId;

    private array $definition;
    private array $fields;
    private string $label;
    private string $description;
    private int $min;
    private int $max;

    private ?StorageInterface $storage;

    private array $expandedPalette = [];

    /**
     * @internal
     */
    public function __construct(ContainerInterface $locator, string $table, int $rowId, string $name)
    {
        $this->locator = $locator;

        // Object metadata
        $this->name = $name;
        $this->table = $table;
        $this->rowId = $rowId;

        // DCA definition
        $this->definition = $GLOBALS['TL_DCA'][$table]['fields'][$name];

        $fields = $this->definition['fields'] ?? [];

        $getReferencedDefinition = function (string $field): ?array {
            return $GLOBALS['TL_DCA'][$this->table]['fields'][$field] ?? null;
        };

        // Pull in referenced definitions
        foreach ($fields as $field => $fieldDefinition) {
            if (0 === strpos($field, '&')) {
                $realFieldName = ltrim($field, '&');

                if (null === ($referencedDefinition = $getReferencedDefinition($realFieldName))) {
                    throw new \InvalidArgumentException("Invalid definition for group '$this->name': Referenced field '$field' does not exist.");
                }

                $fields[$realFieldName] = ArrayUtil::mergePropertiesRecursive($referencedDefinition, $fieldDefinition);
                unset($fields[$field]);
            }
        }

        $palette = $this->definition['palette'] ?? array_keys($fields) ?? [];

        if (empty($palette)) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Keys 'palette' and 'fields' cannot both be empty.");
        }

        if (!\is_array($palette)) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Key 'palette' must be an array.");
        }

        // Validate palette and build field definitions
        foreach ($palette as $field) {
            if (null === ($fieldDefinition = $fields[$field] ?? $getReferencedDefinition($field))) {
                throw new \InvalidArgumentException("Invalid definition for group '$name': Field '$field' does not exist.");
            }

            $this->fields[$field] = $fieldDefinition;
        }

        $this->label = $this->definition['label'][0] ?? '';
        $this->description = $this->definition['label'][1] ?? '';
        $this->min = $this->definition['min'] ?? 0;
        $this->max = $this->definition['max'] ?? 0;

        if ($this->min < 0) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Key 'min' cannot be less than 0.");
        }

        if (0 !== $this->max && $this->max < $this->min) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Key 'max' cannot be less than 'min'.");
        }
    }

    public function setStorage(StorageInterface $storage): self
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * @internal
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @internal
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @internal
     */
    public function getRowId(): int
    {
        return $this->rowId;
    }

    /**
     * @internal
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @internal
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @internal
     */
    public function getMinElements(): int
    {
        return $this->min;
    }

    /**
     * @internal
     */
    public function getMaxElements(): int
    {
        return $this->max;
    }

    /**
     * @internal
     */
    public function getFields(): array
    {
        return array_keys($this->fields);
    }

    /**
     * @internal
     */
    public function getFieldDefinition(string $field): ?array
    {
        return $this->fields[$field] ?? null;
    }

    /**
     * @internal
     *
     * @return mixed
     */
    public function getDefinition(string $key)
    {
        return $this->definition[$key] ?? null;
    }

    /**
     * Returns the value of an element's field.
     *
     * (Delegate to storage engine.)
     *
     * @return mixed
     */
    public function getField(int $elementId, string $field)
    {
        if (null === $this->storage) {
            return null;
        }

        return $this->storage->getField($elementId, $field);
    }

    /**
     * Sets the value of an element's field.
     *
     * (Delegate to storage engine.)
     */
    public function setField(int $elementId, string $field, $value): self
    {
        if (null !== $this->storage) {
            $this->storage->setField($elementId, $field, $value);
        }

        return $this;
    }

    /**
     * Defines the contained elements and their order by setting an array of
     * element IDs.
     *
     * (Delegate to storage engine.)
     */
    public function setElements(array $newElementIds): self
    {
        if (null === $this->storage) {
            return $this;
        }

        $existingElementIds = $this->storage->getElements();

        // Synchronize elements
        foreach ($newElementIds as $key => $id) {
            // Generate new elements for special value -1
            if (-1 === $id) {
                $newElementIds[$key] = $this->storage->createElement();

                continue;
            }

            // Strip unmatched IDs
            if (!\in_array($id, $existingElementIds, true)) {
                unset($newElementIds[$key]);
            }
        }

        foreach (array_diff($existingElementIds, $newElementIds) as $id) {
            $this->storage->removeElement($id);
        }

        // Constrain element counts
        $newElementIds = $this->applyMinMaxConstraints($newElementIds);

        // Adjust order
        $this->storage->orderElements(array_values($newElementIds));

        return $this;
    }

    /**
     * Persist changes.
     *
     * (Delegate to storage engine.)
     */
    public function persist(): self
    {
        if (null !== $this->storage) {
            $this->storage->persist();
        }

        return $this;
    }

    /**
     * Remove this group.
     *
     * (Delegate to storage engine.)
     */
    public function remove(): self
    {
        if (null !== $this->storage) {
            $this->storage->remove();
        }

        return $this;
    }

    /**
     * Expand palette + add virtual fields.
     *
     *  <group>
     *
     *   ==>
     *
     *  <group start>,
     *    <element start 4>, <fieldA 4>, <fieldB 4>, […], <element end 4>
     *    <element start 1>, <fieldA 1>, <fieldB 1>, […], <element end 1>
     *    […]
     *  <group end>
     */
    public function expand(string $paletteKey, bool $isSubPalette = false): self
    {
        if (null === $this->storage) {
            return $this;
        }

        // Create virtual fields once
        if (empty($this->expandedPalette)) {
            $elements = $this->applyMinMaxConstraints($this->storage->getElements());

            $this->expandedPalette = [$this->addGroupField(true)];

            foreach ($elements as $id) {
                $this->expandedPalette[] = $this->addGroupElementField(true, $id);

                foreach ($this->fields as $name => $definition) {
                    $this->expandedPalette[] = $this->addVirtualField($name, $definition, $id);
                }

                $this->expandedPalette[] = $this->addGroupElementField(false, $id);
            }

            $this->expandedPalette[] = $this->addGroupField(false);
        }

        // Expand palette
        $paletteManipulator = PaletteManipulator::create()
            ->addField($this->expandedPalette, $this->name)
            ->removeField($this->name)
        ;

        if (!$isSubPalette) {
            $paletteManipulator->applyToPalette($paletteKey, $this->table);
        } else {
            $paletteManipulator->applyToSubpalette($paletteKey, $this->table);
        }

        return $this;
    }

    private function applyMinMaxConstraints(array $elementIds): array
    {
        // Apply min/max constraints
        $size = \count($elementIds);

        if ($this->min > 0 && $size < $this->min) {
            for ($i = 0; $i < $this->min - $size; ++$i) {
                $elementIds[] = $this->storage->createElement();
            }
        } elseif ($this->max > 0 && $size > $this->max) {
            for ($i = $this->max; $i < $size; ++$i) {
                $this->storage->removeElement($elementIds[$i]);

                unset($elementIds[$i]);
            }
        }

        return $elementIds;
    }

    private function addGroupField(bool $start): string
    {
        $type = $start ? 'start' : 'end';
        $newName = "{$this->name}__({$type})";

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = [
            'input_field_callback' => fn () => $this->twig()->render(
                '@MvoContaoGroupWidget/widget_group.html.twig',
                [
                    'group' => $this,
                    'type' => $start,
                ]
            ),
        ];

        return $newName;
    }

    private function addGroupElementField(bool $start, int $id): string
    {
        $type = $start ? 'el_start' : 'el_end';
        $newName = "{$this->name}__({$type})__{$id}";

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = [
            'input_field_callback' => fn () => $this->twig()->render(
                '@MvoContaoGroupWidget/widget_group_element.html.twig',
                [
                    'group' => $this,
                    'type' => $start,
                    'id' => $id,
                ]
            ),
        ];

        return $newName;
    }

    private function addVirtualField(string $name, array $definition, int $id): string
    {
        $newName = "{$this->name}__{$name}__{$id}";

        $definition = ArrayUtil::mergePropertiesRecursive(
            $definition,
            [
                'eval' => [
                    'doNotSaveEmpty' => true,
                ],
                'sql' => null,
            ]
        );

        // Install storage callbacks
        $definition['load_callback'] = [
            [GroupWidgetListener::class, 'onLoadGroupField'],
            ...($definition['load_callback'] ?? []),
        ];

        $definition['save_callback'] = [
            ...($definition['save_callback'] ?? []),
            [GroupWidgetListener::class, 'onStoreGroupField'],
        ];

        // Set a default label
        if (!\array_key_exists('label', $definition)) {
            // Use '<groupName>.<fieldName>' as key for inlined fields
            $labelKey = \array_key_exists($name, $GLOBALS['TL_DCA'][$this->table]['fields'])
                ? $name : "{$this->name}.$name";

            $definition['label'] = &$GLOBALS['TL_LANG'][$this->table][$labelKey];
        }

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = $definition;

        return $newName;
    }

    private function twig(): Environment
    {
        return $this->locator->get('twig');
    }
}
