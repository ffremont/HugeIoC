<?php

namespace Huge\IoC\Cache;

use Huge\IoC\Cache\CacheProvider;

class Memcache extends CacheProvider {

    /**
     * @var \Memcache
     */
    private $memcache;
    
    public function __construct(\Memcache $memcache){
        $this->memcache = $memcache;
    }

    /**
     * Sets the memcache instance to use.
     *
     * @param \Memcache $memcache
     */
    public function setMemcache(\Memcache $memcache) {
        $this->memcache = $memcache;
    }

    /**
     * Gets the memcache instance used by the cache.
     *
     * @return \Memcache
     */
    public function getMemcache() {
        return $this->memcache;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id) {
        return $this->memcache->get($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id) {
        return (bool) $this->memcache->get($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifeTime = 0) {
        $lifeTime = is_null($lifeTime) ? 0 : $lifeTime;
        if ($lifeTime > 30 * 24 * 3600) {
            $lifeTime = time() + $lifeTime;
        }
        return $this->memcache->set($id, $data, 0, (int) $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id) {
        return $this->memcache->delete($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush() {
        return $this->memcache->flush();
    }

}

