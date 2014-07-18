<?php

namespace Huge\IoC\Fixtures;

use Huge\IoC\Annotations\Component;
use Huge\IoC\Annotations\Autowired;

/**
 * @Component
 */
class Personne {

    private $nom;
    
    /**
     * @Autowired("Huge\IoC\Fixtures\IWeb")
     * @var \Huge\IoC\Fixtures\IWeb 
     */
    private $implIWeb;
    
    public function __construct() {
        
    }
    
    public function getNom() {
        return $this->nom;
    }

    public function setNom($nom) {
        $this->nom = $nom;
    }

    public function getImplIWeb() {
        return $this->implIWeb;
    }

    public function setImplIWeb(\Huge\IoC\Fixtures\IWeb $implIWeb) {
        $this->implIWeb = $implIWeb;
    }



}

