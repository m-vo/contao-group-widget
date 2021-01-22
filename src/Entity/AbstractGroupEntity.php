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
abstract class AbstractGroupEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer", options={"unsigned": true})
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

    // Implementing this method is optional
    public function getId(): ?int
    {
        return $this->id;
    }

    // Implementing this method is optional
    public function getSourceTable(): ?string
    {
        return $this->sourceTable;
    }

    // Implementing this method is optional
    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function getElements(): Collection
    {
        return $this->elements;
    }

    public function addElement($element): void
    {
        if (!$element instanceof AbstractGroupElementEntity) {
            throw new \RuntimeException(sprintf("Please provide an implementation of the '%s' method for class '%s'.", __METHOD__, static::class));
        }

        if (!$this->elements->contains($element)) {
            $this->elements[] = $element;
            $element->setParent($this);
        }
    }

    public function removeElement($element): void
    {
        if (!$element instanceof AbstractGroupElementEntity) {
            throw new \RuntimeException(sprintf("Please provide an implementation of the '%s' method for class '%s'", __METHOD__, static::class));
        }

        if ($this->elements->removeElement($element)) {
            // Set the owning side to null (unless already changed)
            if ($element->getParent() === $this) {
                $element->setParent(null);
            }
        }
    }
}
