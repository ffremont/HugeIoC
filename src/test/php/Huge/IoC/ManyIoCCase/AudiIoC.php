<?php

namespace Huge\IoC\ManyIoCCase;

use Huge\IoC\Factory\ConstructFactory;

class AudiIoC extends \Huge\IoC\Container\SuperIoC{

    const MARQUE = 'AUDI';
    
    public function __construct() {
        parent::__construct(__CLASS__, '1.0');
        
        $this->addDefinitions(array(
            array(
                'id' => 'a4_01',
                'class' => 'Huge\IoC\ManyIoCCase\Voiture',
                'factory' => new ConstructFactory(array('a4_01', self::MARQUE))
            )
        ));
    }

}

