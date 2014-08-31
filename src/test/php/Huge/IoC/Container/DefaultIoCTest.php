<?php

namespace Huge\IoC\Container;

use Huge\IoC\RefBean;
use Huge\IoC\Factory\SimpleFactory;
use Huge\IoC\Factory\ConstructFactory;
use Doctrine\Common\Cache\ArrayCache;

use Huge\IoC\Fixtures\Contact;

class DefaultIoCTest extends \PHPUnit_Framework_TestCase {

    public function __construct() {
        parent::__construct();
    }
    
    /**
     * @test
     */
    public function iocSimpleCacheOk() {
        $c = new DefaultIoC('a');
        $cache = new ArrayCache();
        $c->setCacheImpl($cache);
        $c->addDefinitions(array(
            array(
                'id' => 'contact',
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            ),
            array(
                'id' => 'client',
                'class' => '\Huge\IoC\Fixtures\Client',
                'factory' =>  new ConstructFactory(array(new RefBean('contact', $c), '001'))
            )
        ));
        $c->start();
        $this->assertNotNull($c->getBean('contact'));
        $this->assertNotNull($c->getBean('client'));
        
        $cc = new DefaultIoC('b');
        $cc->setCacheImpl($cache);
        $cc->addDefinitions(array(
            array(
                'id' => 'contact',
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $cc->start();
        $this->assertNotNull($cc->getBean('contact'));
        $this->assertNull($cc->getBean('client'));
    }
    
     /**
     * @test
     */
    public function iocOtherIoCOk() {
        $c1 = new DefaultIoC();
        // ioc contenant la classe qui va injecter une interface
        $c1->addDefinitions(array(
            array(
                'class' => 'Huge\IoC\Fixtures\Personne',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $this->assertNotEmpty($c1->getDefinitions());
        
        $c2 = new DefaultIoC();
        // ioc contenant l'impl. de l'interface
        $c2->addDefinitions(array(
            array(
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $this->assertNotEmpty($c2->getDefinitions());
        
        $c1->addOtherContainers(array($c2));
        $c1->start();
        
        $this->assertNotNull($c1->getBean('Huge\IoC\Fixtures\Contact'));
        $this->assertNotNull($c1->getBean('Huge\IoC\Fixtures\Personne'));
        $this->assertNotNull($c1->getBean('Huge\IoC\Fixtures\Personne')->getImplIWeb());
    }

    /**
     * @test
     */
    public function iocSimpleOk() {
        $c = new DefaultIoC();
        $c->addDefinitions(array(
            array(
                'id' => 'contact',
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $c->start();
        
        $this->assertNotNull($c->getBean('contact'));
        $this->assertEmpty($c->getBean('contact')->getNom());
        $this->assertNotNull($c->getBean('Huge\IoC\Container\DefaultIoC'));
    }
    
    /**
     * @test
     */
    public function iocFindBeansByImplOk() {
        $c = new DefaultIoC();
        $c->addDefinitions(array(
            array(
                'id' => 'contact',
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $c->start();
        
        $this->assertNotNull($c->getBean('contact'));
        $res = $c->findBeansByImpl('Huge\IoC\Fixtures\IWeb');
        $this->assertCount(1, $res);
    }
    
     /**
     * @test
     */
    public function iocSimpleArgsOk() {
        $c = new DefaultIoC();
        $c->addDefinitions(array(
            array(
                'id' => 'contact',
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => new ConstructFactory(array('DUPUIT', 'Pierre'))
            )
        ));
        $c->start();
        
        $this->assertNotNull($c->getBean('contact'));
        $this->assertEquals($c->getBean('contact')->getNom(), 'DUPUIT');
        $this->assertEquals($c->getBean('contact')->getPrenom(), 'Pierre');
    }
    
    /**
     * @test
     */
    public function iocSimpleRefOk() {
        $c = new DefaultIoC();
        $c->addDefinitions(array(
            array(
                'id' => 'contact',
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => new ConstructFactory(array('DUPUIT', 'Pierre'))
            ),
            array(
                'id' => 'client',
                'class' => '\Huge\IoC\Fixtures\Client',
                'factory' => new ConstructFactory(array(new RefBean('contact', $c), '001'))
            )
        ));
        $c->start();
        
        $this->assertNotNull($c->getBean('client'));
        $this->assertNotNull($c->getBean('contact'));
        $this->assertNotNull($c->getBean('client')->getContact());
        $this->assertEquals($c->getBean('client')->getNumero(), '001');
        $this->assertEquals($c->getBean('client')->getContact(), $c->getBean('contact'));
    }
    
    /**
     * @test
     */
    public function iocAutowiredOk() {
        $c = new DefaultIoC();
        $c2 = new DefaultIoC();
        $c->addDefinitions(array(
            array(
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            ),array(
                'class' => '\Huge\IoC\Fixtures\BigClient',
                'factory' => new ConstructFactory(array('001'))
            )
        ));
        $c->addOtherContainers(array($c2));
        $c->start();
        
        $this->assertNotNull($c->getBean('\Huge\IoC\Fixtures\Contact'));
        $this->assertNotNull($c->getBean('\Huge\IoC\Fixtures\BigClient'));
        $this->assertNotNull($c->getBean('\Huge\IoC\Fixtures\BigClient')->getContact());
        $this->assertEquals($c->getBean('\Huge\IoC\Fixtures\BigClient')->getContact(), $c->getBean('\Huge\IoC\Fixtures\Contact'));
        $this->assertEquals($c->getBean('\Huge\IoC\Fixtures\BigClient')->getNumero(), '001');
    }

}

