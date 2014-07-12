<?php

namespace Huge\IoC\Fixtures;

/**
 */
class BigClient {
    
    /**
     * @Autowired("contact")
     * @var \Huge\IoC\Fixtures\Contact
     */
    private $contact;
    private $numero;
    
    function __construct($numero) {
        $this->numero = $numero;
    }
    
    public function getContact() {
        return $this->contact;
    }

    public function setContact( \Huge\IoC\Fixtures\Contact $contact) {
        $this->contact = $contact;
    }

    public function getNumero() {
        return $this->numero;
    }

    public function setNumero($numero) {
        $this->numero = $numero;
    }



}

