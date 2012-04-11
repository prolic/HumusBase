<?php

namespace HumusBase\Doctrine\ArrayToObjectMapper;

interface ArrayToObjectMapperInterface
{

    /**
     * Get entity from array
     *
     * @param string $entityName
     * @param array $data
     * @param bool $clone
     * @return object
     */
    public function getEntityFromArray($entityName, array $data, $clone = false);

}