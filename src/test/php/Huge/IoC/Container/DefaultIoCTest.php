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
        $c = new DefaultIoC();
        $cache = new ArrayCache();
        $c->setCacheImpl($cache);
        $c->setDefinitions(array(
            array(
                'id' => 'contact',
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $c->start();
        $this->assertNotNull($c->getBean('contact'));
        
        $cc = new DefaultIoC();
        $cc->setCacheImpl($cache);
        $cc->setDefinitions(array(
            array(
                'id' => 'contact',
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $cc->start();
        $this->assertNotNull($cc->getBean('contact'));
    }

    /**
     * @test
     */
    public function iocSimpleOk() {
        $c = new DefaultIoC();
        $c->setDefinitions(array(
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
        $c->setDefinitions(array(
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
        $c->setDefinitions(array(
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
        $c->setDefinitions(array(
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
        $c->setDefinitions(array(
            array(
                'class' => '\Huge\IoC\Fixtures\Contact',
                'factory' => SimpleFactory::getInstance()
            ),array(
                'class' => '\Huge\IoC\Fixtures\BigClient',
                'factory' => new ConstructFactory(array('001'))
            )
        ));
        $c->setOtherContainers(array($c2));
        $c->start();
        
        $this->assertNotNull($c->getBean('\Huge\IoC\Fixtures\Contact'));
        $this->assertNotNull($c->getBean('\Huge\IoC\Fixtures\BigClient'));
        $this->assertNotNull($c->getBean('\Huge\IoC\Fixtures\BigClient')->getContact());
        $this->assertEquals($c->getBean('\Huge\IoC\Fixtures\BigClient')->getContact(), $c->getBean('\Huge\IoC\Fixtures\Contact'));
        $this->assertEquals($c->getBean('\Huge\IoC\Fixtures\BigClient')->getNumero(), '001');
    }

}

