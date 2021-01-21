<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Mvo\ContaoGroupWidget\Entity\GroupEntityProxy;
use Mvo\ContaoGroupWidget\Storage\EntityStorage;
use Mvo\ContaoGroupWidget\Tests\Fixtures\Entity\Island;
use Mvo\ContaoGroupWidget\Tests\Fixtures\Entity\Treasure;
use PHPUnit\Framework\TestCase;

class EntityStorageTest extends TestCase
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = DoctrineTestHelper::createEntityManager();
        $this->setupDatabase($this->entityManager->getConnection());
    }

    public function testGetElements(): void
    {
        $storage = $this->getEntityStorage();

        self::assertEquals([70, 12, 42], $storage->getElements());
    }

    public function testCreateElement(): void
    {
        $storage = $this->getEntityStorage();

        $storage->createElement();

        self::assertEquals([70, 12, 42, 71], $storage->getElements());
    }

    public function testRemoveElement(): void
    {
        $storage = $this->getEntityStorage();

        $storage->removeElement(12);

        self::assertEquals([70, 42], $storage->getElements());
    }

    public function testOrderElements(): void
    {
        $storage = $this->getEntityStorage();

        $storage->orderElements([42, 70, 12]);

        self::assertEquals([42, 70, 12], $storage->getElements());
    }

    public function testGetField(): void
    {
        $storage = $this->getEntityStorage();

        self::assertEquals('coconuts', $storage->getField(12, 'finding'));
    }

    public function testSetFieldAndPersist(): void
    {
        $storage = $this->getEntityStorage();
        $connection = $this->entityManager->getConnection();

        $storage->setField(42, 'finding', 'eh, nothing?');

        self::assertEquals('eh, nothing?', $storage->getField(42, 'finding'));

        self::assertEquals(
            'gold',
            $connection->fetchOne('SELECT finding FROM tl_treasure WHERE id = 42')
        );

        $storage->persist();

        self::assertEquals(
            'eh, nothing?',
            $connection->fetchOne('SELECT finding FROM tl_treasure WHERE id = 42')
        );
    }

    public function testRemove(): void
    {
        $storage = $this->getEntityStorage();

        $storage->remove();

        self::assertEquals(
            0,
            $this->entityManager
                ->getConnection()
                ->fetchOne('SELECT COUNT(*) FROM tl_island WHERE id = 1')
        );
    }

    protected function setupDatabase(Connection $connection): void
    {
        $connection->insert(
            'tl_island',
            [
                'id' => 1,
                'name' => 'Mêlée Island',
            ]
        );

        $connection->insert(
            'tl_treasure',
            [
                'id' => 42,
                'finding' => 'gold',
                'latitude' => 80.13,
                'longitude' => 40.22,
                'position' => 2,
                'parent' => 1,
            ]
        );

        $connection->insert(
            'tl_treasure',
            [
                'id' => 70,
                'finding' => 'silver',
                'latitude' => 79.55,
                'longitude' => 39.992,
                'position' => 8,
                'parent' => 1,
            ]
        );

        $connection->insert(
            'tl_treasure',
            [
                'id' => 12,
                'finding' => 'coconuts',
                'latitude' => 80.11,
                'longitude' => 40.42,
                'position' => 4,
                'parent' => 1,
            ]
        );
    }

    protected function getEntityStorage(): EntityStorage
    {
        $entity = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Island::class, 'i')
            ->where('i.id = 1')
            ->getQuery()
            ->getSingleResult()
        ;

        $groupEntityProxy = new GroupEntityProxy($entity, 'treasures');

        return new EntityStorage($this->entityManager, $groupEntityProxy, Treasure::class);
    }
}
