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

    public function create(Group $group): StorageInterface;
}
