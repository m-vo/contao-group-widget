<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

/**
 * Parsed DCA definition of a group.
 */
final class Definition
{
    private string $label;
    private string $description;

    private array $fields;
    private int $min;
    private int $max;

    private string $storageType;

    public function __construct(array $definition)
    {
        $this->label = $definition['label'][0] ?? '';
        $this->description = $definition['label'][1] ?? '';
        $this->fields = $definition['eval']['palette'] ?? [];
        $this->min = $definition['eval']['min'] ?? 0;
        $this->max = $definition['eval']['max'] ?? 0;
        $this->storageType = $definition['eval']['storage'] ?? 'blob';

        if (empty($this->fields)) {
            throw new \InvalidArgumentException("Invalid group definition: Key 'palette' cannot be empty.");
        }

        if ($this->min < 0) {
            throw new \InvalidArgumentException("Invalid group definition: Key 'min' cannot be less than 0.");
        }

        if (0 !== $this->max && $this->max < $this->min) {
            throw new \InvalidArgumentException("Invalid group definition: Key 'max' cannot be less than 'min'.");
        }

        if (!\in_array($this->storageType, ['blob'], true)) {
            throw new \InvalidArgumentException("Invalid group definition: Unknown storage type '{$this->storageType}}'.");
        }
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getMinElements(): int
    {
        return $this->min;
    }

    public function getMaxElements(): int
    {
        return $this->max;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getStorageType(): string
    {
        return $this->storageType;
    }
}
