<?php

namespace HumusBase\Zend\EventManager\StaticListenerAggregate;

use Zend\EventManager\StaticEventManager,
    Zend\Stdlib\CallbackHandler,
    Traversable;

abstract class StaticListenerAggregate implements StaticListenerAggregateInterface
{

    /**
     * @var array
     */
    protected $identifiers = array();

    /**
     * @var array
     */
    protected $events = array();

    /**
     * @var int
     */
    protected $defaultPriority = 1;

    /**
     * Constructor
     *
     * @param array|Traversable $config
     * @return void
     */
    public function __construct($config)
    {
        if (!is_array($config) && !$config instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Parameter provided to %s must be an array or Traversable',
                __METHOD__
            ));
        }
        $this->setIdentifiers(array_keys($config));
        foreach ($config as $identifier => $events) {
            $this->addEvents($identifier, $events);
        }
    }

    /**
     * Get identifiers
     *
     * @return array
     */
    public function getIdentifiers()
    {
        return $this->identifiers;
    }

    /**
     * Get events for a specific identifier
     *
     * @param int|string $identifier
     * @return array
     */
    public function getEvents($identifier)
    {
        if (!array_key_exists($identifier, $this->events)) {
            throw new Exception\InvalidArgumentException('Invalid identifier given');
        }
        $events = $this->events[$identifier];
        return array_keys($events);
    }

    /**
     * Get metadata for an event
     *
     * @param int|string $identifier
     * @param int|string $event
     * @return array
     */
    public function getMetadata($identifier, $event)
    {
        if (!array_key_exists($identifier, $this->events)) {
            throw new Exception\InvalidArgumentException('Invalid identifier given');
        }
        if (!array_key_exists($event, $this->events[$identifier])) {
            throw new Exception\InvalidArgumentException('Invalid event given');
        }
        $event = $this->events[$identifier][$event];
        return $event['metadata'];
    }

    /**
     * Get priority for an event
     *
     * @param int|string $identifier
     * @param int|string $event
     * @return int
     */
    public function getPriority($identifier, $event)
    {
        if (!array_key_exists($identifier, $this->events)) {
            throw new Exception\InvalidArgumentException('Invalid identifier given');
        }
        if (!array_key_exists($event, $this->events[$identifier])) {
            throw new Exception\InvalidArgumentException('Invalid event given');
        }
        $event = $this->events[$identifier][$event];
        return $event['priority'];
    }

    /**
     * Get default priority
     *
     * @return int
     */
    public function getDefaultPriority()
    {
        return $this->defaultPriority;
    }

    /**
     * Detach all previously attached listeners
     *
     * @return void
     */
    public function detach()
    {
        $sevm = $this->staticEventManager();
        foreach ($this->getIdentifiers() as $identifier) {
            foreach ($this->getEvents($identifier) as $event) {
                foreach ($sevm->getListeners($identifier, $event) as $callbackHandler) {
                    $sevm->detach($identifier, $callbackHandler);
                }
            }
        }
    }

    /**
     * Returns the static event manager
     *
     * @return StaticEventManager
     */
    protected function staticEventManager()
    {
        return StaticEventManager::getInstance();
    }

    /**
     * Set the identifiers (overrides any currently set identifiers)
     *
     * @param string|int|array|Traversable $identifiers
     * @return void
     */
    protected function setIdentifiers($identifiers)
    {
        if (is_array($identifiers) || $identifiers instanceof \Traversable) {
            $this->identifiers = array_unique((array) $identifiers);
        } elseif ($identifiers !== null) {
            $this->identifiers = array($identifiers);
        }
    }

    /**
     * Add an event
     *
     * @param string|int $identifier
     * @param array $events
     * @return void
     */
    protected function addEvents($identifier, array $events)
    {
        foreach ($events as $name => $event) {
            if (!is_array($event)) {
                $name = $event;
                $priority = $this->getDefaultPriority();
                $metadata = array();
            } else {
                $priority = isset($event['priority']) ? $event['priority'] : $this->getDefaultPriority();
                $metadata = isset($event['metadata']) ? $event['metadata'] : array();
            }
            $this->events[$identifier][$name] = array(
                'priority' => $priority,
                'metadata' => $metadata
            );
        }
    }
}