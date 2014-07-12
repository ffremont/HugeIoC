<?php

namespace Huge\IoC;

class RefBean {

    private $name;
    private $ioc;
    
    public function __construct($name, Container\IContainer $ioc) {
        $this->name = $name;
        $this->ioc = $ioc;
    }
    
    public function getBean(){
        return $this->ioc->getBean($this->name);
    }

}

