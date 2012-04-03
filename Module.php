<?php

namespace HumusBase;

use Zend\Module\Consumer\AutoloaderProvider,
    Zend\Module\Manager;

class Module implements AutoloaderProvider
{
    /**
     * Return an array for passing to Zend\Loader\AutoloaderFactory.
     *
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
        );
    }

}