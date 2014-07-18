<?php

namespace Huge\IoC\SubClassCase;

use Huge\IoC\Container\DefaultIoC;
use Huge\IoC\Factory\SimpleFactory;
use Doctrine\Common\Cache\ArrayCache;

class SubClassCaseTest extends \PHPUnit_Framework_TestCase {

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
                'class' => 'Huge\IoC\SubClassCase\Data\Controller',
                'factory' => SimpleFactory::getInstance()
            ), array(
                'class' => 'Huge\IoC\SubClassCase\Data\Client',
                'factory' => SimpleFactory::getInstance()
            )
        ));
        $c->start();

        return $c;
    }

    /**
     * @test
     */
    public function iocSimpleSubClassOk() {
        $cache = new ArrayCache();
        $c = $this->getDefaultIocInstance($cache);
        $i = 0;
        while ($i < 2) {
            $i++;
            $this->assertNotNull($c->getBean('Huge\IoC\SubClassCase\Data\Client'));
            $this->assertNotNull($c->getBean('Huge\IoC\SubClassCase\Data\Controller')->getPerson());
            $this->assertEquals($c->getBean('Huge\IoC\SubClassCase\Data\Client'), $c->getBean('Huge\IoC\SubClassCase\Data\Controller')->getPerson());
        }
    }

}

