<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Mvo\ContaoGroupWidget\EventListener\GroupWidgetListener;
use Mvo\ContaoGroupWidget\Storage\EntityStorage;
use Mvo\ContaoGroupWidget\Storage\SerializedStorage;
use Mvo\ContaoGroupWidget\Storage\StorageInterface;
use Psr\Container\ContainerInterface;
use Twig\Environment;

final class Group
{
    private ContainerInterface $locator;

    private string $name;
    private string $table;
    private int $rowId;

    private array $fields;
    private string $label;
    private string $description;
    private int $min;
    private int $max;

    private StorageInterface $storage;

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
        $definition = &$GLOBALS['TL_DCA'][$table]['fields'][$name];

        $fields = $definition['fields'] ?? [];
        $palette = $definition['palette'] ?? array_keys($fields) ?? null;

        if (null === $palette) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Keys 'palette' and 'fields' cannot both be empty.");
        }

        foreach($definition['palette'] ?? [] as $field) {
            // Prefer inlined field definition
            if(array_key_exists($field, $fields)) {
                $this->fields[$field] = $fields[$field];

                continue;
            }

            // Use field reference
            if(array_key_exists($field, $GLOBALS['TL_DCA'][$this->table]['fields'])) {
                $this->fields[$field] = &$GLOBALS['TL_DCA'][$this->table]['fields'][$field];

                continue;
            }

            throw new \InvalidArgumentException("Invalid definition for group '$name': Field '$field' does not exist.");
        }

        $this->label = $definition['label'][0] ?? '';
        $this->description = $definition['label'][1] ?? '';
        $this->min = $definition['min'] ?? 0;
        $this->max = $definition['max'] ?? 0;

        if ($this->min < 0) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Key 'min' cannot be less than 0.");
        }

        if (0 !== $this->max && $this->max < $this->min) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Key 'max' cannot be less than 'min'.");
        }

        // Storage backend
        switch ($definition['storage'] ?? 'serialized') {
            case 'serialized':
                $this->storage = new SerializedStorage($locator, $this);
                break;

            case 'entity':
                $entity = $definition['entity'] ?? '';

                if (!class_exists($entity)) {
                    throw new \InvalidArgumentException("Invalid definition for group '$name': Key 'entity' must point to a valid entity class.");
                }

                $this->storage = new EntityStorage($locator, $entity, $this);
                break;

            default:
                throw new \InvalidArgumentException("Invalid definition for group '$name': Unknown storage type.");
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getRowId(): int
    {
        return $this->rowId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getMinElements(): int
    {
        return $this->min;
    }

    public function getMaxElements(): int
    {
        return $this->max;
    }

    public function getFields(): array
    {
        return array_keys($this->fields);
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
        return $this->storage->getField($elementId, $field);
    }

    /**
     * Sets the value of an element's field.
     *
     * (Delegate to storage engine.)
     */
    public function setField(int $elementId, string $field, $value): self
    {
        $this->storage->setField($elementId, $field, $value);

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
        $existingElementIds = $this->storage->getElements();

        // Synchronize elements
        foreach ($newElementIds as $key => $id) {
            // Generate new elements for special value 0
            if (0 === $id) {
                $newElementIds[$key] = $this->storage->createElement();
                continue;
            }

            if (!\in_array($id, $existingElementIds, true)) {
                throw new \InvalidArgumentException("Element ID '$id' could not be located.");
            }
        }

        foreach (array_diff($existingElementIds, $newElementIds) as $id) {
            $this->storage->removeElement($id);
        }

        // Constrain element counts
        $this->applyMinMaxConstraints();

        // Adjust order
        $this->storage->orderElements($newElementIds);

        return $this;
    }

    /**
     * Persist changes.
     *
     * (Delegate to storage engine.)
     */
    public function persist(): self
    {
        $this->storage->persist();

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
    public function expand(string $palette): self
    {
        // Get elements
        $this->applyMinMaxConstraints();
        $elements = $this->storage->getElements();

        // Build virtual fields
        $newPaletteItems = [$this->addGroupField(true)];

        foreach ($elements as $id) {
            $newPaletteItems[] = $this->addGroupElementField(true, $id);

            foreach ($this->fields as $name => $definition) {
                $newPaletteItems[] = $this->addVirtualField($name, $definition, $id);
            }

            $newPaletteItems[] = $this->addGroupElementField(false, $id);
        }

        $newPaletteItems[] = $this->addGroupField(false);

        PaletteManipulator::create()
            ->addField($newPaletteItems, $this->name)
            ->removeField($this->name)
            ->applyToPalette($palette, $this->table)
        ;

        return $this;
    }

    private function applyMinMaxConstraints(): void
    {
        // Apply min/max constraints
        $existingElementIds = $this->storage->getElements();
        $size = \count($existingElementIds);

        if ($this->min > 0 && $size < $this->min) {
            for ($i = 0; $i < $this->min - $size; ++$i) {
                $this->storage->createElement();
            }
        }

        if ($this->max > 0 && $size > $this->max) {
            for ($i = $this->max; $i < $size; ++$i) {
                $this->storage->removeElement($existingElementIds[$i]);
            }
        }
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

    private function addVirtualField(string $field, int $id): string
    {
        $newName = "{$this->name}__{$field}__{$id}";

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = array_merge_recursive(
            $GLOBALS['TL_DCA'][$this->table]['fields'][$field],
            [
                'label' => &$GLOBALS['TL_LANG'][$this->table][$field],
                'eval' => [
                    'doNotSaveEmpty' => true,
                ],
                'load_callback' => [[GroupWidgetListener::class, 'onLoadGroupField']],
                'save_callback' => [[GroupWidgetListener::class, 'onStoreGroupField']],
                'sql' => null,
            ]
        );

        return $newName;
    }

    private function twig(): Environment
    {
        return $this->locator->get('twig');
    }
}
