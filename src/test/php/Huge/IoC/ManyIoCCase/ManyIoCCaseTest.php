<?php

namespace Huge\IoC\ManyIoCCase;

class ManyIoCCaseTest extends \PHPUnit_Framework_TestCase {

    public function __construct() {
        parent::__construct();
    }

    /**
     * @test
     */
    public function get_bean_recursively() {
        $garageIoc = new GarageIoC();
        $garageIoc->start();
        
        // renault zoé
        $zoe = $garageIoc->getBean('zoe_01');
        $this->assertNotNull($zoe);
        $this->assertEquals(RenaultIoC::MARQUE, $zoe->getMarque());
        
        // audi A4
        $audi = $garageIoc->getBean('a4_01');
        $this->assertNotNull($audi);
        $this->assertEquals(AudiIoC::MARQUE, $audi->getMarque());
        
        // logan de chez Dacia
        $logan = $garageIoc->getBean('logan_01');
        $this->assertNotNull($logan);
        $this->assertEquals(DaciaIoC::MARQUE, $logan->getMarque());
    }
    
     /**
     * @test
     */
    public function get_bean_recursively_withCache() {
        $garageIoc = new GarageIoC();
        $garageIoc->setCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
        $garageIoc->start();
        
        // renault zoé
        $zoe = $garageIoc->getBean('zoe_01');
        $this->assertNotNull($zoe);
        
        $zoe = $garageIoc->getBean('zoe_01');
        $this->assertNotNull($zoe);
    }

}

