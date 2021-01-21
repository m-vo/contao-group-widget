<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\Group\Group;

class SerializedStorageFactory implements StorageFactoryInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getName(): string
    {
        return 'serialized';
    }

    public function create(Group $group): SerializedStorage
    {
        return new SerializedStorage($this->connection, $group);
    }
}
