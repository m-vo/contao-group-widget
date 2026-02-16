<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mvo\ContaoGroupWidget\Entity\AbstractGroupElementEntity;

/**
 * Element entity.
 *
 * This contains information about element positions and ID generation. If you
 * want to adjust the implementation of the base class, make sure to implement the
 * `GroupElementEntityInterface` yourself.
 *
 * @ORM\Entity()
 * @ORM\Table(name="tl_treasure")
 */
class Treasure extends AbstractGroupElementEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=Island::class, inversedBy="locations")
     * @ORM\JoinColumn(name="parent", nullable=false)
     */
    protected $parent;

    /**
     * Private field 'finding' will need to be accessed via reflection.
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $finding = '';

    /**
     * Field not present in DCA.
     *
     * @ORM\Column(type="float")
     */
    private float $latitude = 0;

    /**
     * Field not present in DCA.
     *
     * @ORM\Column(type="float")
     */
    private float $longitude = 0;

    /**
     * Virtual field 'location' (get).
     */
    public function getLocation(): string
    {
        return \sprintf('%d, %d', $this->latitude, $this->longitude);
    }

    /**
     * Virtual field 'location' (set).
     */
    public function setLocation(string $latLong): void
    {
        [$this->latitude, $this->longitude] = array_map('floatval', explode(',', $latLong));
    }
}
