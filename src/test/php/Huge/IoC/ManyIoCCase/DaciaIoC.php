<?php

namespace Huge\IoC\ManyIoCCase;

use Huge\IoC\Factory\ConstructFactory;

class DaciaIoC extends \Huge\IoC\Container\SuperIoC{

    const MARQUE = 'DACIA';
    
    public function __construct() {
        parent::__construct(__CLASS__, '1.0');
        
        $this->addDefinitions(array(
            array(
                'id' => 'logan_01',
                'class' => 'Huge\IoC\ManyIoCCase\Voiture',
                'factory' => new ConstructFactory(array('logan_01', self::MARQUE))
            )
        ));
    }

}

