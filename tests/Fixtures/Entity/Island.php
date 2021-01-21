<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Fixtures\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity for `tl_island` DCA.
 *
 * @ORM\Entity()
 * @ORM\Table(name="tl_island")
 */
class Island
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
    private string $islandName = '';

    /**
     * This is our group widget field! It will be updated through the
     * `getTreasures()`, `addTreasure()` and `removeTreasure()` functions.
     *
     * @ORM\OneToMany(targetEntity=Treasure::class, mappedBy="parent", orphanRemoval=true)
     */
    private $treasures;

    public function __construct()
    {
        $this->treasures = new ArrayCollection();
    }

    /**
     * @return Collection<int, Treasure>
     */
    public function getTreasures(): Collection
    {
        return $this->treasures;
    }

    public function addTreasure(Treasure $treasure): self
    {
        if (!$this->treasures->contains($treasure)) {
            $this->treasures[] = $treasure;
            $treasure->setParent($this);
        }

        return $this;
    }

    public function removeTreasure(Treasure $treasure): self
    {
        if ($this->treasures->removeElement($treasure)) {
            // set the owning side to null (unless already changed)
            if ($treasure->getParent() === $this) {
                $treasure->setParent(null);
            }
        }

        return $this;
    }

    public function getNameAndId(): string
    {
        return "{$this->islandName} ({$this->id})";
    }
}
