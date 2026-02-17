<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Storage;

use Mvo\ContaoGroupWidget\Group\Group;
use Mvo\ContaoGroupWidget\Storage\EntityStorageFactory;
use Mvo\ContaoGroupWidget\Tests\Fixtures\Entity\Island;
use Mvo\ContaoGroupWidget\Tests\Fixtures\Entity\Map;
use Mvo\ContaoGroupWidget\Tests\Fixtures\Entity\Treasure;
use PHPUnit\Framework\TestCase;

class EntityStorageFactoryTest extends TestCase
{
    public function testName(): void
    {
        self::assertSame('entity', EntityStorageFactory::getName());
    }

    public function testCreateWithLocalEntity(): void
    {
        $entityManager = DoctrineTestHelper::createEntityManager();

        $entityManager->getConnection()->insert('tl_island', [
            'id' => 2,
            'name' => 'Monkey Island',
        ]);

        $factory = new EntityStorageFactory($entityManager);

        $instance = $factory->create($this->getGroupMock());

        /** @var Island $groupEntity */
        $groupEntity = $instance->getGroupEntityProxy()->getReference();

        self::assertInstanceOf(Island::class, $groupEntity);
        self::assertSame('Monkey Island (2)', $groupEntity->getNameAndId());

        self::assertSame(Treasure::class, $instance->getElementEntityClass());
    }

    public function testCreateWithNewReferencedEntity(): void
    {
        $entityManager = DoctrineTestHelper::createEntityManager();

        $factory = new EntityStorageFactory($entityManager);

        $instance = $factory->create($this->getGroupMock(Map::class));

        /** @var Map $groupEntity */
        $groupEntity = $instance->getGroupEntityProxy()->getReference();

        self::assertInstanceOf(Map::class, $groupEntity);
        self::assertSame('tl_island', $groupEntity->getSourceTable());
        self::assertSame(2, $groupEntity->getSourceId());

        self::assertSame(Treasure::class, $instance->getElementEntityClass());
    }

    public function testCreateWithExistingReferencedEntity(): void
    {
        $entityManager = DoctrineTestHelper::createEntityManager();

        $entityManager->getConnection()->insert('Map', [
            'id' => 100,
            'author' => 'LeChuck',
            'source_table' => 'tl_island',
            'source_id' => 2,
        ]);

        $factory = new EntityStorageFactory($entityManager);

        $instance = $factory->create($this->getGroupMock(Map::class));

        /** @var Map $groupEntity */
        $groupEntity = $instance->getGroupEntityProxy()->getReference();

        self::assertInstanceOf(Map::class, $groupEntity);
        self::assertSame('LeChuck', $groupEntity->author);
        self::assertSame('tl_island', $groupEntity->getSourceTable());
        self::assertSame(2, $groupEntity->getSourceId());

        self::assertSame(Treasure::class, $instance->getElementEntityClass());
    }

    private function getGroupMock(string $entityDefinition = null)
    {
        $group = $this->createMock(Group::class);
        $group
            ->method('getDefinition')
            ->with('entity')
            ->willReturn($entityDefinition)
        ;

        $group
            ->method('getName')
            ->willReturn('treasures')
        ;

        $group
            ->method('getTable')
            ->willReturn('tl_island')
        ;

        $group
            ->method('getRowId')
            ->willReturn(2)
        ;

        return $group;
    }
}
