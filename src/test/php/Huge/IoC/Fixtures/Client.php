<?php

namespace Huge\IoC\Fixtures;

use Huge\IoC\Annotations\Component;

/**
 * @Component
 */
class Client {
    
    private $contact;
    private $numero;
    
    function __construct(Contact $contact, $numero) {
        $this->contact = $contact;
        $this->numero = $numero;
    }
    
    public function getContact() {
        return $this->contact;
    }

    public function setContact($contact) {
        $this->contact = $contact;
    }

    public function getNumero() {
        return $this->numero;
    }

    public function setNumero($numero) {
        $this->numero = $numero;
    }



}

