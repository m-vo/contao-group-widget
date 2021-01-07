<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\EventListener\GroupWidgetListener;
use Mvo\ContaoGroupWidget\Storage\SerializedStorage;
use Mvo\ContaoGroupWidget\Storage\StorageInterface;
use Twig\Environment;

final class Group
{
    private string $name;
    private string $table;
    private int $rowId;

    private Definition $definition;
    private StorageInterface $storage;

    private Environment $twig;

    /**
     * @internal
     */
    public function __construct(Environment $twig, Connection $connection, string $table, int $rowId, string $name)
    {
        $this->twig = $twig;

        $this->definition = new Definition($GLOBALS['TL_DCA'][$table]['fields'][$name]);

        switch ($this->definition->getStorageType()) {
            default:
                $this->storage = new SerializedStorage($connection, $this);
        }

        $this->name = $name;
        $this->table = $table;
        $this->rowId = $rowId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefinition(): Definition
    {
        return $this->definition;
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getRowId(): int
    {
        return $this->rowId;
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
        $newPaletteItems = [];

        $newPaletteItems[] = $this->addGroupField(true);

        foreach ($this->storage->getElements() as $id) {
            $newPaletteItems[] = $this->addGroupElementField(true, $id);

            foreach ($this->definition->getFields() as $field) {
                $newPaletteItems[] = $this->addVirtualField($field, $id);
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

    private function addGroupField(bool $start): string
    {
        $type = $start ? 'start' : 'end';
        $newName = "{$this->name}__({$type})";

        $GLOBALS['TL_DCA'][$this->table]['fields'][$newName] = [
            'input_field_callback' => fn () => $this->twig->render(
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
            'input_field_callback' => fn () => $this->twig->render(
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
}
