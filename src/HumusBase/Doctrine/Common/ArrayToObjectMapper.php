<?php

namespace HumusBase\Doctrine\Common;

interface ArrayToObjectMapper
{

    /**
     * Get object from array
     *
     * @param string $objectClassClassName
     * @param array $data
     * @param bool $clone
     * @return object
     */
    public function getObjectFromArray($objectClassClassName, array $data, $clone = false);

}