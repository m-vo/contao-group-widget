<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass()
 */
abstract class AbstractGroupEntity implements GroupEntityInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(name="source_id", type="integer")
     */
    protected $sourceId;

    /**
     * @ORM\Column(name="source_table", type="string", length=255)
     */
    protected $sourceTable;

    /**
     * Add your own ORM\Column definition for the child elements when extending this class.
     *
     * Example:
     *   > ORM\OneToMany(targetEntity=MyGroupElement::class, mappedBy="parent", orphanRemoval=true)
     */
    protected $elements;

    public function __construct()
    {
        $this->elements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceTable(): ?string
    {
        return $this->sourceTable;
    }

    public function setSourceTable(string $sourceTable): void
    {
        $this->sourceTable = $sourceTable;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSourceId(int $sourceId): void
    {
        $this->sourceId = $sourceId;
    }

    /**
     * @return Collection|array<GroupElementEntityInterface>
     */
    public function getElements(): Collection
    {
        return $this->elements;
    }

    public function addElement(GroupElementEntityInterface $element): void
    {
        if (!$this->elements->contains($element)) {
            $this->elements[] = $element;
            $element->setParent($this);
        }
    }

    public function removeElement(GroupElementEntityInterface $element): void
    {
        if ($this->elements->removeElement($element)) {
            // set the owning side to null (unless already changed)
            if ($element->getParent() === $this) {
                $element->setParent(null);
            }
        }
    }
}
