<?php

namespace HumusBase\Zend\EventManager\StaticListenerAggregate;

interface StaticListenerAggregateInterface
{
    /**
     * Get identifiers
     *
     * @return array
     */
    public function getIdentifiers();

    /**
     * Get events for a specific identifier
     *
     * @param int|string $identifier
     * @return array
     */
    public function getEvents($identifier);

    /**
     * Get metadata for an event
     *
     * @param int|string $identifier
     * @param int|string $event
     * @return array
     */
    public function getMetadata($identifier, $event);

    /**
     * Get priority for an event
     *
     * @param int|string $identifier
     * @param int|string $event
     * @return int
     */
    public function getPriority($identifier, $event);

    /**
     * Get default priority
     *
     * @return int
     */
    public function getDefaultPriority();

    /**
     * Attach one or more listeners
     *
     * @return void
     */
    public function attach();

    /**
     * Detach all previously attached listeners
     *
     * @return void
     */
    public function detach();
}