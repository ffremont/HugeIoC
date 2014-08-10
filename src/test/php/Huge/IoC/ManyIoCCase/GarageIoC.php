<?php

namespace Huge\IoC\ManyIoCCase;


class GarageIoC extends \Huge\IoC\Container\SuperIoC{

    public function __construct($version = '1.0') {
        parent::__construct(__CLASS__, $version);
        
        $this->addOtherContainers(array(
            new AudiIoC(),
            new RenaultIoC()
        ));
    }

}

