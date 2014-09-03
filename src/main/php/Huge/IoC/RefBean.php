<?php

namespace Huge\IoC;

class RefBean {

    private $name;
    
    public function __construct($name) {
        $this->name = $name;
    }
    
    public function getBean(Container\IContainer $ioc){
        return $ioc->getBean($this->name);
    }

}

