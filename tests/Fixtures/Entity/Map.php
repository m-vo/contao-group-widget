<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mvo\ContaoGroupWidget\Entity\AbstractGroupEntity;

/**
 * Standalone group entity.
 *
 * This entity contains `sourceTable` and `sourceId` fields to soft-reference
 * a DCA table/row (see abstract base class). You can adjust the implementation
 * of the base class to your needs - there are no type hints in place.
 *
 * @ORM\Entity()
 * @ORM\Table(name="Map")
 */
class Map extends AbstractGroupEntity
{
    /**
     * @ORM\Column(name="author", options={"default": ""})
     */
    public string $author = '';

    /**
     * @ORM\OneToMany(targetEntity=Treasure::class, mappedBy="parent", orphanRemoval=true)
     */
    protected $elements;
}
