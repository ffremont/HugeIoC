<?php

namespace Huge\IoC\Cache;

use Huge\IoC\Cache\CacheProvider;

/**
 * Array cache driver.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author David Abdemoulaie <dave@hobodave.com>
 */
class ArrayCache extends CacheProvider {

    /**
     * @var array $data
     */
    private $data = array();

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id) {
        return (isset($this->data[$id])) ? $this->data[$id] : false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id) {
        return isset($this->data[$id]);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifeTime = 0) {
        $this->data[$id] = $data;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id) {
        unset($this->data[$id]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush() {
        $this->data = array();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats() {
        return null;
    }

}
