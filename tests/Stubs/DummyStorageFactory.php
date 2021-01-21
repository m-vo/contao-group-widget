<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Stubs;

use Mvo\ContaoGroupWidget\Storage\StorageFactoryInterface;

abstract class DummyStorageFactory implements StorageFactoryInterface
{
    public static function getName(): string
    {
        return 'dummy';
    }
}
