<?php

namespace Huge\IoC\SubClassCase\Data;

use Huge\IoC\Annotations\Component;
use Huge\IoC\Annotations\Autowired;

/**
 * @Component
 */
class Controller {

    /**
     * @Autowired("Huge\IoC\SubClassCase\Data\Person")
     * @var \Huge\IoC\SubClassCase\Data\Person
     */
    private $person;
    
    public function __construct() {
        
    }
    
    public function getPerson() {
        return $this->person;
    }

    public function setPerson(\Huge\IoC\SubClassCase\Data\Person $person) {
        $this->person = $person;
    }

}

