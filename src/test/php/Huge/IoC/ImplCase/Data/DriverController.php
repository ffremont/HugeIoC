<?php

namespace Huge\IoC\ImplCase\Data;

use Huge\IoC\Annotations\Component;
use Huge\IoC\Annotations\Autowired;

/**
 * @Component
 */
class DriverController {

    /**
     * @Autowired("Huge\IoC\ImplCase\Data\ImplDriver")
     * @var \Huge\IoC\ImplCase\Data\ImplDriver
     */
    private $driver;
    
    public function __construct() {
        
    }
    
    public function getDriver() {
        return $this->driver;
    }

    public function setDriver(\Huge\IoC\ImplCase\Data\ImplDriver $driver) {
        $this->driver = $driver;
    }

}

