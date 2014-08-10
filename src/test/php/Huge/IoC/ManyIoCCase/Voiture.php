<?php

namespace Huge\IoC\ManyIoCCase;

use Huge\IoC\Annotations\Component;

/**
 * @Component
 */
class Voiture {

    private $place;
    private $marque;
    
    function __construct($place, $marque) {
        $this->place = $place;
        $this->marque = $marque;
    }

    public function getPlace() {
        return $this->place;
    }

    public function setPlace($place) {
        $this->place = $place;
    }

    public function getMarque() {
        return $this->marque;
    }

    public function setMarque($marque) {
        $this->marque = $marque;
    }
}

