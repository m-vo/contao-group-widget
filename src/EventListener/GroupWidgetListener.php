<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Contao\DC_File;
use Contao\DC_Folder;
use Contao\FilesModel;
use Mvo\ContaoGroupWidget\Group\Group;
use Mvo\ContaoGroupWidget\Group\Registry;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
final class GroupWidgetListener
{
    private RequestStack $requestStack;
    private Registry $registry;
    private Environment $twig;

    public function __construct(RequestStack $requestStack, Registry $registry, Environment $twig)
    {
        $this->requestStack = $requestStack;
        $this->registry = $registry;
        $this->twig = $twig;
    }

    /**
     * @Hook("loadDataContainer", priority=-256)
     */
    public function initializeGroups(string $table): void
    {
        if (
            null === ($request = $this->requestStack->getMasterRequest()) ||
            empty($this->registry->getGroupFields($table))
        ) {
            return;
        }

        if (!\in_array($action = $request->get('act', ''), ['edit', 'delete'], true)) {
            return;
        }

        $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = [self::class, 'onLoadDataContainer'];
        $GLOBALS['TL_DCA'][$table]['config']['onsubmit_callback'][] = [self::class, 'onSubmitDataContainer'];
        $GLOBALS['TL_DCA'][$table]['config']['ondelete_callback'][] = [self::class, 'onDeleteDataContainer'];

        $GLOBALS['TL_JAVASCRIPT']['mvo-group-widget'] = 'bundles/mvocontaogroupwidget/backend.min.js';
        $GLOBALS['TL_CSS']['mvo-group-widget'] = 'bundles/mvocontaogroupwidget/backend.min.css';
    }

    /**
     * Listener for the DCA onload_callback that gets registered dynamically.
     *
     * Applies changes to group elements (reorder/delete/new) and expands the
     * group palettes and fields by adding virtual nodes.
     */
    public function onLoadDataContainer(DataContainer $dc): void
    {
        $table = $dc->table;
        $id = $this->getRowId($dc);

        // Split a palette into fields
        $getPaletteFields = static fn (string $palette): array => array_map('trim', preg_split('/[,;]/', $palette));

        // Find currently visible group fields
        $groupFields = $this->registry->getGroupFields($table);
        $visibleGroupFields = array_intersect($groupFields, $getPaletteFields($dc->getPalette()));

        if (empty($visibleGroupFields)) {
            foreach ($groupFields as $groupField) {
                // If a group widget is located in a subpalette that isn't shown by
                // default, we need to submit the form once the dummy widget gets
                // injected so that the virtual fields can be built correctly.
                $GLOBALS['TL_DCA'][$table]['fields'][$groupField] = [
                    'input_field_callback' => fn () => $this->twig->render(
                        '@MvoContaoGroupWidget/widget_group_reloader.html.twig',
                        [
                            'table' => $table,
                        ]
                    ),
                ];
            }

            return;
        }

        // Build a mapping [affected group name => [palette key, isSubPalette]]
        $groupFieldsWithPalettes = [];

        $addAffectedPalettes = static function (string $key, string $palette, bool $isSubPalette) use ($getPaletteFields, $visibleGroupFields, &$groupFieldsWithPalettes): void {
            foreach (array_intersect($visibleGroupFields, $getPaletteFields($palette)) as $groupField) {
                $groupFieldsWithPalettes[$groupField][] = [$key, $isSubPalette];
            }
        };

        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] ?? [] as $key => $palette) {
            if (!\is_string($palette)) {
                continue;
            }

            $addAffectedPalettes($key, $palette, false);
        }

        foreach ($GLOBALS['TL_DCA'][$table]['subpalettes'] ?? [] as $key => $palette) {
            $addAffectedPalettes($key, $palette, true);
        }

        // Handle form data and expand groups
        foreach ($groupFieldsWithPalettes as $name => $palettes) {
            $group = $this->registry->getGroup($table, $id, $name);

            if (
                null !== ($request = $this->requestStack->getMasterRequest())
                && null !== ($post = $request->request->get("widget-group__$name"))
            ) {
                $ids = array_map(
                    'intval',
                    array_filter(explode(',', $post))
                );

                $group->setElements($ids);
            }

            foreach ($palettes as [$palette, $isSubPalette]) {
                $group->expand($palette, $isSubPalette);
            }
        }
    }

    /**
     * Listener for the DCA onsubmit_callback that gets registered dynamically.
     *
     * Persists changes for all groups that were loaded.
     */
    public function onSubmitDataContainer(DataContainer $dc): void
    {
        /** @var Group $group */
        foreach ($this->registry->getInitializedGroups($dc->table, $this->getRowId($dc)) as $group) {
            $group->persist();
        }
    }

    /**
     * Listener for the DCA ondelete_callback that gets registered dynamically.
     *
     * Removes all groups that were loaded.
     */
    public function onDeleteDataContainer(/* dynamic arguments */): void
    {
        $args = \func_get_args();

        /** @var DataContainer $dc is the second argument in DC_Folder, first everywhere else */
        $dc = $args[0] instanceof DataContainer ? $args[0] : $args[1];

        /** @var Group $group */
        foreach ($this->registry->getInitializedGroups($dc->table, $this->getRowId($dc)) as $group) {
            $group->remove();
        }
    }

    /**
     * Listener for a virtual field's save_callback that gets registered dynamically.
     *
     * Persists changes to the virtual fields.
     *
     * @return mixed
     */
    public function onLoadGroupField($_, DataContainer $dc)
    {
        [$group, $field, $id] = explode('__', $dc->field);

        return $this->registry
            ->getGroup($dc->table, $this->getRowId($dc), $group)
            ->getField((int) $id, $field)
            ;
    }

    /**
     * Listener for a virtual field's save_callback that gets registered dynamically.
     *
     * Retrieves values for the virtual fields.
     */
    public function onStoreGroupField($value, DataContainer $dc): ?int
    {
        [$group, $field, $id] = explode('__', $dc->field);

        $this->registry
            ->getGroup($dc->table, $this->getRowId($dc), $group)
            ->setField((int) $id, $field, $value)
        ;

        // Prevent DataContainer from saving the record
        return null;
    }

    private function getRowId(DataContainer $dc): int
    {
        if ($dc instanceof DC_File || $dc instanceof DC_Folder) {
            if (null === ($model = FilesModel::findByPath((string) $dc->id))) {
                throw new \RuntimeException('Could not determine a numeric row ID.');
            }

            return (int) $model->id;
        }

        return (int) $dc->id;
    }
}
