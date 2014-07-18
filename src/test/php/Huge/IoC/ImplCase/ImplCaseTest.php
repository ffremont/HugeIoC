<?php

namespace Huge\IoC\ImplCase;

use Huge\IoC\Container\DefaultIoC;
use Huge\IoC\Factory\SimpleFactory;
use Doctrine\Common\Cache\ArrayCache;

class ImplCaseTest extends \PHPUnit_Framework_TestCase {

    public function __construct() {
        parent::__construct();
    }

    /**
     * @param \Doctrine\Common\Cache\ArrayCache $cache
     * @return \Huge\IoC\Container\DefaultIoC
     */
    public function getDefaultIocInstance(ArrayCache $cache) {
        $c = new DefaultIoC();
        $c->setCacheImpl($cache);
        $c->addDefinitions(array(
            array(
                'class' => 'Huge\IoC\ImplCase\Data\ImplDriver',
                'factory' => SimpleFactory::getInstance()
            ), array(
                'class' => 'Huge\IoC\ImplCase\Data\DriverController',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $c->start();

        return $c;
    }

    /**
     * @test
     */
    public function iocSimpleImplOk() {
        $cache = new ArrayCache();
        $c = $this->getDefaultIocInstance($cache);
        $i = 0;
        while ($i < 2) {
            $i++;
            $this->assertNotNull($c->getBean('Huge\IoC\ImplCase\Data\ImplDriver'));
            $this->assertNotNull($c->getBean('Huge\IoC\ImplCase\Data\DriverController')->getDriver());
            $this->assertEquals($c->getBean('Huge\IoC\ImplCase\Data\ImplDriver'), $c->getBean('Huge\IoC\ImplCase\Data\DriverController')->getDriver());
        }
    }

}

