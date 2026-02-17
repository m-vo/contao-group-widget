<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

use Mvo\ContaoGroupWidget\Group\Group;

interface StorageFactoryInterface
{
    public static function getName(): string;

    // TODO: make row id part of the storage interface in v2 and remove it from being
    // a property of the Group class
    public function create(Group $group): StorageInterface;
}
