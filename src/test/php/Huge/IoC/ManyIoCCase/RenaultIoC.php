<?php

namespace Huge\IoC\ManyIoCCase;

use Huge\IoC\Factory\ConstructFactory;

class RenaultIoC extends \Huge\IoC\Container\SuperIoC{

    const MARQUE = 'RENAULT';
    
    public function __construct() {
        parent::__construct(__CLASS__, '1.0');
        
        $this->addDefinitions(array(
            array(
                'id' => 'zoe_01',
                'class' => 'Huge\IoC\ManyIoCCase\Voiture',
                'factory' => new ConstructFactory(array('zoe_01', self::MARQUE))
            )
        ));
       $this->addOtherContainers(array(
           new DaciaIoC()
       ));
    }

}

