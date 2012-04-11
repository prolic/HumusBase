<?php

namespace HumusBase\Doctrine\ORM;

use HumusBase\Doctrine\Common\ArrayToObjectMapper,
    Doctrine\ORM\EntityManager,
    Doctrine\ORM\ORMInvalidArgumentException as InvalidArgumentException,
    Doctrine\ORM\Mapping\ClassMetadata,
    Doctrine\Common\NotifyPropertyChanged,
    Doctrine\Common\Collections\Collection,
    DateTime,
    ReflectionProperty;

class ArrayToEntityMapper implements ArrayToObjectMapper
{

    /**
     * @var EntityManager
     */
    protected $om;

    /**
     * Constructor
     *
     * @param EntityManager $em
     * @return void
     */
    public function __construct(EntityManager $em)
    {
        $this->om = $em;
    }

    /**
     * Get entity from array
     *
     * @param string $entityClassName
     * @param array $data
     * @param bool $clone
     * @return object
     * @throws InvalidArgumentException
     */
    public function getObjectFromArray($entityClassName, array $data, $clone = false)
    {
        $this->validateEntityClassName($entityClassName);
        $meta = $this->getEntityManager()->getClassMetadata($entityClassName);
        $entity = $this->loadEntity($entityClassName, $data, $clone);
        $identifier = array_shift($meta->getIdentifier());
        foreach ($data as $field => $value) {
            if ($field == $identifier
                && !$meta->generatorType !== ClassMetadata::GENERATOR_TYPE_NONE
            ) { // setting identifier is forbidden
                continue;
            }
            $field = $this->toCamelCase($field);
            if ($meta->hasField($field)) {
                $this->updateField($entity, $meta, $field, $value);
            } else if ($meta->hasAssociation($field)) {
                $this->updateAssociation($entity, $meta, $field, $value, $clone);
            } else {
                continue;
            }
        }
        return $entity;
    }

    /**
     * Get entity manager
     *
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->om;
    }

    /**
     * Validates the entity name
     *
     * @param string $entityName
     * @throws InvalidArgumentException
     * @return void
     */
    protected function validateEntityClassName($entityName)
    {
        if (!class_exists($entityName)) {
            throw new InvalidArgumentException('Class ' .$entityName . ' not found');
        }
        if (!$this->getEntityManager()->getClassMetadata($entityName)) {
            throw new InvalidArgumentException('Class ' .$entityName . ' is not a valid entity');
        }
    }

    /**
     * Update a field
     *
     * @param object $entity
     * @param ClassMetadata $meta
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function updateField($entity, ClassMetadata $meta, $field, $value)
    {
        $fieldMapping = $meta->getFieldMapping($field);
        $type = $fieldMapping['type'];
        switch ($type) {
            case 'datetime':
                $value = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                break;
            case 'time':
                $value = DateTime::createFromFormat('H:i:s', $value);
                break;
            case 'date':
                $value = DateTime::createFromFormat('Y-m-d', $value);
                break;
        }
        $setter = $this->fieldToSetterMethod($field);
        if (method_exists($entity, $setter)) { // use setter
            $entity->{$setter}($value);
        } else { // use reflection
            $reflectionProperty = $meta->getReflectionProperty($field);
            $oldValue = $this->getValue($entity, $meta, $field);
            $this->updateProperty($entity, $reflectionProperty, $oldValue, $value, $field);
        }
    }

    /**
     * Update an association
     *
     * @param object $entity
     * @param ClassMetadata $meta
     * @param string $field
     * @param mixed $value
     * @param bool $clone
     * @return void
     */
    protected function updateAssociation($entity, ClassMetadata $meta, $field, $value, $clone = false)
    {
        $associationMapping = $meta->getAssociationMapping($field);
        $targetEntityName = $associationMapping['targetEntity'];

        if (isset($associationMapping['joinColumns'])) { // x-to-one mapping
            if (is_array($value)) {
                $targetEntity = $this->getEntityFromArray($targetEntityName, $value, $clone);
            } else {
                $identifier = array_shift($this->getEntityManager()->getClassMetadata($targetEntityName)->getIdentifier());
                $targetEntity = $this->loadEntity($targetEntityName, array($identifier => $value), $clone);
            }
            $reflectionProperty = $meta->getReflectionProperty($field);
            $oldValue = $reflectionProperty->getValue($entity);
            if (method_exists($targetEntity, '__load')) {
                $targetEntity->__load();
            }
            $this->updateProperty($entity, $reflectionProperty, $oldValue, $targetEntity, $field);
        } else if(is_array($value)) { // x-to-many mapping
            // value has to be an array
            $reflectionProperty = $meta->getReflectionProperty($field);
            /* @var $reflectionProperty ReflectionProperty */
            $reflectionProperty->setAccessible(true);
            $collection = $reflectionProperty->getValue($entity);
            /* @var $collection Collection */
            foreach ($value as $data) {
                $targetMeta = $this->getEntityManager()->getClassMetadata($targetEntityName);
                $identifier = array_shift($targetMeta->getIdentifier());
                if (is_scalar($data)) {
                    $targetEntity = $this->loadEntity(
                        $targetEntityName,
                        array(
                            $identifier => $data
                        ),
                        $clone
                    );
                } else {
                    $targetEntity = $this->getEntityFromArray($targetEntityName, $data, $clone);
                }
                if (!$collection->contains($targetEntity)) {
                    $collection->add($targetEntity);
                }
            }
        }
    }

    /**
     * Load an entity
     *
     * @param string $entityName
     * @param array $data
     * @param bool $clone
     * @return object
     * @throws InvalidArgumentException
     */
    protected function loadEntity($entityName, array $data, $clone = false)
    {
        if (!class_exists($entityName)) {
            throw new InvalidArgumentException('Class ' . $entityName . ' does not exist');
        }
        if (!$this->getEntityManager()->getMetadataFactory()->hasMetadataFor($entityName)) {
            throw new InvalidArgumentException($entityName . ' is not an entity mapped by the entity manager');
        }
        $identifier = array_shift($this->getEntityManager()->getClassMetadata($entityName)->getIdentifier());
        if (isset($data[$identifier])) {
            $id = $data[$identifier];
            $entity = $this->getEntityManager()->find($entityName, $id);
            if (!$entity) {
                throw new InvalidArgumentException('Invalid identifier given, id: ' . $id . ' , entity: ' . $entityName);
            } else if (false !== $clone) {
                if (!method_exists($entity, '__clone') && !is_callable(array($entity, '__clone'))) {
                    throw new InvalidArgumentException('Entity ' . $entity . ' is not cloneable');
                }
                $entity = clone $entity;
            }
        } else {
            $entity = new $entityName;
        }
        return $entity;
    }

    /**
     * Get value from object
     *
     * @param object $entity
     * @param \Doctrine\ORM\Mapping\ClassMetadata $meta
     * @param string $field
     * @return mixed
     */
    protected function getValue($entity, ClassMetadata $meta, $field)
    {
        $reflectionProperty = $meta->getReflectionProperty($field);
        /* @var $reflectionProperty ReflectionProperty */
        $value = $reflectionProperty->getValue($entity);
        return $value;
    }

    /**
     * Update reflection property
     *
     * @param object $entity
     * @param ReflectionProperty $reflectionProperty
     * @param mixed $oldValue
     * @param mixed $newValue
     * @param string $field
     */
    protected function updateProperty($entity, ReflectionProperty $reflectionProperty, $oldValue, $newValue, $field)
    {
        if ($oldValue !== $newValue) {
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($entity, $newValue);
            if ($entity instanceof NotifyPropertyChanged) {
                $this->getEntityManager()->getUnitOfWork()->propertyChanged($entity, $field, $oldValue, $newValue);
            }
        }
    }

    /**
     * Convert to camel case
     *
     * @param $name
     * @return string
     */
    protected function toCamelCase($name)
    {
        return lcfirst(implode('',array_map('ucfirst', explode('_',$name))));
    }

    /**
     * Convert field to setter method
     *
     * @param $name
     * @return string
     */
    protected function fieldToSetterMethod($name)
    {
        return 'set' . implode('',array_map('ucfirst', explode('_',$name)));
    }
}
