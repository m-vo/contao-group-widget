<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Mvo\ContaoGroupWidget\Group\GroupFactory;
use Symfony\Component\HttpFoundation\RequestStack;

class GroupWidgetListener
{
    private RequestStack $requestStack;
    private GroupFactory $groupFactory;

    public function __construct(RequestStack $requestStack, GroupFactory $groupFactory)
    {
        $this->requestStack = $requestStack;
        $this->groupFactory = $groupFactory;
    }

    /**
     * @Hook("loadDataContainer", priority=-256)
     */
    public function initializeGroups(string $table): void
    {
        if (null === $this->requestStack->getMasterRequest() || empty($this->groupFactory->getGroupFields($table))) {
            return;
        }

        $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = [self::class, 'onLoadDataContainer'];
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
        $groupFields = $this->groupFactory->getGroupFields($table);

        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] ?? [] as $paletteName => $palette) {
            if (!\is_string($palette)) {
                continue;
            }

            $groupNames = array_filter(
                preg_split('/[,;]/', $palette),
                static function (string $name) use ($groupFields): bool {
                    return isset($groupFields[$name]);
                }
            );

            foreach ($groupNames as $groupName) {
                $group = $this->groupFactory->create($table, (int) $dc->id, $groupName);

                if (
                    null !== ($request = $this->requestStack->getMasterRequest())
                    && null !== ($post = $request->request->get("widget-group__$groupName"))
                ) {
                    $idMapping = array_map(
                        'intval',
                        explode(',', $post)
                    );

                    $group->updateData($idMapping);
                }

                $group->expand($paletteName);
            }
        }
    }

    /**
     * Listener for the virtual field's save_callback that get registered dynamically.
     *
     * Persists changes to the virtual fields.
     *
     * @return mixed
     */
    public function onLoadGroupElement($_, DataContainer $dc)
    {
        [$group, $field, $id] = explode('__', $dc->field);

        return $this->groupFactory
            ->create($dc->table, (int) $dc->id, $group)
            ->getField((int) $id, $field)
        ;
    }

    /**
     * Listener for the virtual field's save_callback that get registered dynamically.
     *
     * Retrieves values for the virtual fields.
     */
    public function onStoreGroupElement($value, DataContainer $dc): ?int
    {
        [$group, $field, $id] = explode('__', $dc->field);

        $this->groupFactory
            ->create($dc->table, (int) $dc->id, $group)
            ->setField((int) $id, $field, $value)
        ;

        // Prevent DC_Table from saving the record
        return null;
    }
}
