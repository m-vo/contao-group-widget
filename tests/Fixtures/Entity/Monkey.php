<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity for `tl_monkey` DCA.
 *
 * @ORM\Entity()
 * @ORM\Table(name="tl_monkey")
 */
class Monkey
{
    /**
     * @ORM\Column(name="id", type="integer", options={"unsigned": true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(name="name", options={"default": ""})
     */
    private string $nickname = '';

    /**
     * This is our group widget field! It will be updated through the
     * `getGuardedTreasure()` and `setGuardedTreasure()` functions.
     *
     * @ORM\OneToOne(targetEntity=Treasure::class, cascade={"persist", "remove"})
     */
    private ?Treasure $guardedTreasure = null;

    public function getGuardedTreasure(): ?Treasure
    {
        return $this->guardedTreasure;
    }

    public function setGuardedTreasure(?Treasure $treasure): self
    {
        $this->guardedTreasure = $treasure;

        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }
}
