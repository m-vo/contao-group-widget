<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Entity;

interface GroupElementEntityInterface
{
    public function getId(): ?int;

    public function getPosition(): ?int;

    public function setPosition(int $position);
}
