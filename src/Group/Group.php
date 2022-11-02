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
use Twig\Environment;

/**
 * @final
 */
class Group
{
    private Environment $twig;

    private string $name;
    private string $table;
    private int $rowId;

    private array $definition;
    private array $fields;
    private string $label;
    private string $description;
    private array $defaultWrapperDefinition = [];
    private int $min;
    private int $max;
    private bool $enableOrdering;
    private array $htmlAttributes;

    private ?StorageInterface $storage = null;
    private bool $changed = false;

    private array $expandedPalette = [];

    /**
     * @internal
     */
    public function __construct(Environment $twig, string $table, int $rowId, string $name)
    {
        $this->twig = $twig;

        // Object metadata
        $this->name = $name;
        $this->table = $table;
        $this->rowId = $rowId;

        // DCA definition
        $this->definition = $GLOBALS['TL_DCA'][$table]['fields'][$name];

        $fields = $this->definition['fields'] ?? [];

        $getReferencedDefinition = fn (string $field): ?array => $GLOBALS['TL_DCA'][$this->table]['fields'][$field] ?? null;

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

        $this->htmlAttributes = array_filter([
            'class' => $this->definition['eval']['tl_class'] ?? '',
            'style' => $this->definition['eval']['style'] ?? '',
        ]);

        $this->min = $this->definition['min'] ?? 0;
        $this->max = $this->definition['max'] ?? 0;
        $this->enableOrdering = $this->definition['order'] ?? true;

        if ($this->min < 0) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Key 'min' cannot be less than 0.");
        }

        if (0 !== $this->max && $this->max < $this->min) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Key 'max' cannot be less than 'min'.");
        }

        // Make sure the group wrappers are visible when using DC Multilingual
        foreach ($fields as $fieldDefinition) {
            if (isset($fieldDefinition['eval']['translatableFor'])) {
                $this->defaultWrapperDefinition = ['eval' => ['translatableFor' => '*']];

                break;
            }
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
     * @internal
     */
    public function setChanged(): void
    {
        $this->changed = true;
    }

    /**
     * @internal
     */
    public function hasChanges(): bool
    {
        return $this->changed;
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
        $elementsToCreate = array_filter($newElementIds, static fn (int $id): bool => -1 === $id);
        $elementsToRemove = array_diff($existingElementIds, $newElementIds);
        $unmappedItems = array_diff($newElementIds, $existingElementIds, [-1]);

        // In case there are no explicitly set new elements (id = -1), we
        // assume the first unmatched ID to be a new element that was never
        // stored due to validation errors
        if (empty($elementsToCreate) && !empty($unmappedItems)) {
            $key = array_key_first($unmappedItems);

            unset($unmappedItems[$key]);
            $elementsToCreate[$key] = -1;
        }

        // Generate new elements for special value -1
        foreach (array_keys($elementsToCreate) as $key) {
            $newElementIds[$key] = $this->storage->createElement();
        }

        // Remove any element that is not in the list
        foreach ($elementsToRemove as $key => $id) {
            $this->storage->removeElement($id);
        }

        // Drop unmapped items
        foreach (array_keys($unmappedItems) as $key) {
            unset($newElementIds[$key]);
        }

        // Constrain element counts
        $newElementIds = $this->applyMinMaxConstraints($newElementIds);

        // Adjust order
        if ($this->enableOrdering) {
            $this->storage->orderElements(array_values($newElementIds));
        }

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

        // Set 'multiple' = true, so that we're getting better diffs
        $GLOBALS['TL_DCA'][$this->table]['fields'][$this->name]['eval']['multiple'] = true;

        return $this;
    }

    private function applyMinMaxConstraints(array $elementIds): array
    {
        if (null === $this->storage) {
            throw new \RuntimeException('Cannot apply min/max constraints if no storage back end is set.');
        }

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

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = array_merge(
            $this->defaultWrapperDefinition,
            [
                'input_field_callback' => fn () => $this->twig->render(
                    '@MvoContaoGroupWidget/widget_group.html.twig',
                    [
                        'group' => $this,
                        'type' => $start,
                        'order' => $this->enableOrdering,
                        'htmlAttributes' => $this->htmlAttributes,
                    ]
                ),
            ]
        );

        return $newName;
    }

    private function addGroupElementField(bool $start, int $id): string
    {
        $type = $start ? 'el_start' : 'el_end';
        $newName = "{$this->name}__({$type})__{$id}";

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = array_merge(
            $this->defaultWrapperDefinition,
            [
                'input_field_callback' => fn () => $this->twig->render(
                    '@MvoContaoGroupWidget/widget_group_element.html.twig',
                    [
                        'group' => $this,
                        'type' => $start,
                        'id' => $id,
                    ]
                ),
            ]
        );

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
            if (\array_key_exists($name, $GLOBALS['TL_LANG'][$this->table]["{$this->name}_"] ?? [])) {
                $definition['label'] = &$GLOBALS['TL_LANG'][$this->table]["{$this->name}_"][$name];
            } else {
                $definition['label'] = &$GLOBALS['TL_LANG'][$this->table][$name];
            }
        }

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = $definition;

        return $newName;
    }
}
