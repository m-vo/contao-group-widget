<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Entity;

use Doctrine\Common\Collections\Collection;

interface GroupEntityInterface
{
    public function getSourceTable(): ?string;

    public function setSourceTable(string $sourceTable);

    public function getSourceId(): ?int;

    public function setSourceId(int $sourceId);

    public function getElements(): Collection;

    public function addElement(GroupElementEntityInterface $element);

    public function removeElement(GroupElementEntityInterface $element);
}
