<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass()
 */
class AbstractGroupElementEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer", options={"unsigned": true})
     */
    protected $id;

    // Defining this field is optional
    /**
     * @ORM\Column(name="position", type="integer", options={"unsigned": true})
     */
    protected $position;

    /**
     * Add your own ORM\Column definition for the child elements when extending this class.
     *
     * Example:
     *   > ORM\ManyToOne(targetEntity=MyGroup::class, inversedBy="elements")
     *   > ORM\JoinColumn(nullable=false)
     */
    protected $parent;

    public function getId(): ?int
    {
        return $this->id;
    }

    // Implementing this method is optional
    public function getPosition(): ?int
    {
        return $this->position;
    }

    // Implementing this method is optional
    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getParent(): ?object
    {
        return $this->parent;
    }

    public function setParent(?object $parent): self
    {
        $this->parent = $parent;

        return $this;
    }
}
