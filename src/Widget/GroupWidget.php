<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Widget;

use Contao\System;
use Contao\Widget;
use Mvo\ContaoGroupWidget\Group\Group;

class GroupWidget extends Widget
{
    protected $strTemplate = 'be_widget_group';

    private string $componentType;
    private int $elementId;
    private string $groupName;

    private int $minElements;
    private int $maxElements;

    public function __construct(array $attributes = null)
    {
        parent::__construct($attributes);

        // Dynamic properties
        $this->componentType = $attributes[Group::KEY_COMPONENT_TYPE] ?? Group::TYPE_START;
        $this->elementId = $attributes[Group::KEY_ELEMENT_ID] ?? -1;
        $this->groupName = $attributes[Group::KEY_GROUP] ?? '';

        // User properties
        $this->minElements = $attributes[Group::USER_KEY_MIN_ELEMENTS] ?? 0;
        $this->maxElements = $attributes[Group::USER_KEY_MAX_ELEMENTS] ?? 0;

        // Add assets
        $GLOBALS['TL_JAVASCRIPT']['mvo-group-widget'] = 'bundles/mvocontaogroupwidget/backend.min.js';
        $GLOBALS['TL_CSS']['mvo-group-widget'] = 'bundles/mvocontaogroupwidget/backend.min.css';
    }

    public function generate(): string
    {
        throw new \LogicException('Not implemented');
    }

    public function getComponentType(): string
    {
        return $this->componentType;
    }

    public function getElementId(): int
    {
        return $this->elementId;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function getMinElements(): int
    {
        return $this->minElements;
    }

    public function getMaxElements(): int
    {
        return $this->maxElements;
    }

    public function trans(string $id, array $params = [], $domain = 'contao_default')
    {
        return System::getContainer()->get('translator')->trans($id, $params, $domain);
    }
}
