<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Mvo\ContaoGroupWidget\Entity\GroupElementEntityInterface;
use Mvo\ContaoGroupWidget\Entity\GroupEntityProxy;
use Mvo\ContaoGroupWidget\Tests\Fixtures\Entity\Island;
use Mvo\ContaoGroupWidget\Tests\Fixtures\Entity\Treasure;
use PHPUnit\Framework\TestCase;

class GroupEntityProxyTest extends TestCase
{
    public function testDelegatesCalls(): void
    {
        $entity = new Island();

        $proxy = new GroupEntityProxy($entity, 'treasures');

        self::assertEmpty($proxy->getElements());

        $treasure = new Treasure();
        $treasure->setLocation('-25.7904, 113.7185');

        $proxy->addElement($treasure);

        self::assertCount(1, $proxy->getElements());
        self::assertEquals($treasure, $proxy->getElements()->first());

        $proxy->removeElement($treasure);

        self::assertEmpty($proxy->getElements());
    }

    /**
     * @dataProvider provideEntities
     */
    public function testThrowsIfMethodsAreMissing(object $entity, string $method): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches("/Group entity '.*' needs to have a method '$method' to be able to access the association 'things'\\./");

        new GroupEntityProxy($entity, 'things');
    }

    public function provideEntities(): \Generator
    {
        yield 'missing getThings()' => [
            new class() {
                public function addThing(GroupElementEntityInterface $thing): void
                {
                }

                public function removeThing(GroupElementEntityInterface $thing): void
                {
                }
            },
            'getThings',
        ];

        yield 'missing addThing()' => [
            new class() {
                public function getThings(): Collection
                {
                    return new ArrayCollection();
                }

                public function removeThing(GroupElementEntityInterface $thing): void
                {
                }
            },
            'addThing',
        ];

        yield 'missing removeThing()' => [
            new class() {
                public function getThings(): Collection
                {
                    return new ArrayCollection();
                }

                public function addThing(GroupElementEntityInterface $thing): void
                {
                }
            },
            'removeThing',
        ];
    }
}
