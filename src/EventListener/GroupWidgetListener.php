<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Mvo\ContaoGroupWidget\Group\Registry;
use Symfony\Component\HttpFoundation\RequestStack;

final class GroupWidgetListener
{
    private RequestStack $requestStack;
    private Registry $registry;

    public function __construct(RequestStack $requestStack, Registry $registry)
    {
        $this->requestStack = $requestStack;
        $this->registry = $registry;
    }

    /**
     * @Hook("loadDataContainer", priority=-256)
     */
    public function initializeGroups(string $table): void
    {
        if (null === $this->requestStack->getMasterRequest() || empty($this->registry->getGroupFields($table))) {
            return;
        }

        $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = [self::class, 'onLoadDataContainer'];
        $GLOBALS['TL_DCA'][$table]['config']['onsubmit_callback'][] = [self::class, 'onSubmitDataContainer'];

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
        $availableGroupFields = $this->registry->getGroupFields($table);

        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] ?? [] as $paletteName => $palette) {
            if (!\is_string($palette)) {
                continue;
            }

            // Search palettes for group fields
            $groupFields = array_filter(
                preg_split('/[,;]/', $palette),
                static function (string $name) use ($availableGroupFields): bool {
                    return \in_array($name, $availableGroupFields, true);
                }
            );

            foreach ($groupFields as $name) {
                $group = $this->registry->getGroup($table, (int) $dc->id, $name);

                if (
                    null !== ($request = $this->requestStack->getMasterRequest())
                    && null !== ($post = $request->request->get("widget-group__$name"))
                ) {
                    $ids = array_map(
                        'intval',
                        explode(',', $post)
                    );

                    $group->setElements($ids);
                }

                $group->expand($paletteName);
            }
        }
    }

    /**
     * Listener for the DCA onsubmit_callback that gets registered dynamically.
     *
     * Persists changes for all groups that were loaded.
     */
    public function onSubmitDataContainer(): void
    {
        foreach ($this->registry->getAllInitializedGroups() as $group) {
            $group->persist();
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
            ->getGroup($dc->table, (int) $dc->id, $group)
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
            ->getGroup($dc->table, (int) $dc->id, $group)
            ->setField((int) $id, $field, $value)
        ;

        // Prevent DC_Table from saving the record
        return null;
    }
}
