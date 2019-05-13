<?php

declare(strict_types=1);

namespace Liquetsoft\Fias\Component\EntityManager;

use Liquetsoft\Fias\Component\EntityDescriptor\EntityDescriptor;
use Liquetsoft\Fias\Component\EntityRegistry\EntityRegistry;
use InvalidArgumentException;

/**
 * Объект, который содержит соответствия между сущностями ФИАС и их реализациями
 * в конкретном проекте.
 */
class BaseEntityManager implements EntityManager
{
    /**
     * @var EntityRegistry
     */
    protected $registry;

    /**
     * @var string[]
     */
    protected $bindings;

    /**
     * @param EntityRegistry $registry
     * @param string[]       $bindings
     *
     * @throws InvalidArgumentException
     */
    public function __construct(EntityRegistry $registry, array $bindings)
    {
        $this->registry = $registry;

        $this->bindings = [];
        foreach ($bindings as $entityName => $className) {
            $normalizedEntityName = $this->normalizeEntityName($entityName);
            $normalizedClassName = $this->normalizeClassName($className);
            if ($normalizedClassName === '') {
                throw new InvalidArgumentException(
                    "There is no class for {$entityName} entity name."
                );
            }
            $this->bindings[$normalizedEntityName] = $normalizedClassName;
        }
    }

    /**
     * @inheritdoc
     */
    public function getDescriptorByEntityName(string $entityName): ?EntityDescriptor
    {
        $normalizedEntityName = $this->normalizeEntityName($entityName);
        $return = null;

        if (isset($this->bindings[$normalizedEntityName]) && $this->registry->hasDescriptor($normalizedEntityName)) {
            $return = $this->registry->getDescriptor($normalizedEntityName);
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function getClassByDescriptor(EntityDescriptor $descriptor): ?string
    {
        $normalizedEntityName = $this->normalizeEntityName($descriptor->getName());

        return $this->bindings[$normalizedEntityName] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getDescriptorByInsertFile(string $insertFileName): ?EntityDescriptor
    {
        $return = null;

        foreach ($this->bindings as $entityName => $className) {
            $descriptor = $this->getDescriptorByEntityName($entityName);
            if ($descriptor && $descriptor->isFileNameFitsXmlInsertFileMask($insertFileName)) {
                $return = $descriptor;
                break;
            }
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function getDescriptorByDeleteFile(string $insertFileName): ?EntityDescriptor
    {
        $return = null;

        foreach ($this->bindings as $entityName => $className) {
            $descriptor = $this->getDescriptorByEntityName($entityName);
            if ($descriptor && $descriptor->isFileNameFitsXmlDeleteFileMask($insertFileName)) {
                $return = $descriptor;
                break;
            }
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function getDescriptorByClass(string $className): ?EntityDescriptor
    {
        $normalizedClassName = $this->normalizeClassName($className);
        $entityName = null;

        foreach ($this->bindings as $bindedEntity => $bindedClass) {
            if ($normalizedClassName === $bindedClass) {
                $entityName = $bindedEntity;
                break;
            }
        }

        return $entityName ? $this->getDescriptorByEntityName($entityName) : null;
    }

    /**
     * @inheritdoc
     */
    public function getDescriptorByObject($object): ?EntityDescriptor
    {
        $return = null;

        if (is_object($object)) {
            $return = $this->getDescriptorByClass(get_class($object));
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function getBindedClasses(): array
    {
        return array_unique(array_values($this->bindings));
    }

    /**
     * Приводит имя сущности к единообразному виду.
     *
     * @param string $entityName
     *
     * @return string
     */
    protected function normalizeEntityName(string $entityName): string
    {
        return strtolower(trim($entityName));
    }

    /**
     * Приводит имя класса к единообразному виду.
     *
     * @param string $className
     *
     * @return string
     */
    protected function normalizeClassName(string $className): string
    {
        return trim($className, '\\ ');
    }
}
