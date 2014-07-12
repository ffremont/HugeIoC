<?php

namespace Huge\IoC\Cache;

use Huge\IoC\Cache\ICache;

abstract class CacheProvider implements ICache {

    /**
     * @var string The namespace to prefix all cache ids with
     */
    const PREFIX = 'huge_';
    
    /**
     * {@inheritdoc}
     */
    public function fetch($id) {
        return $this->doFetch($this->getNamespacedId($id));
    }

    /**
     * {@inheritdoc}
     */
    public function contains($id) {
        return $this->doContains($this->getNamespacedId($id));
    }

    /**
     * {@inheritdoc}
     */
    public function save($id, $data, $lifeTime = 0) {
        return $this->doSave($this->getNamespacedId($id), $data, $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id) {
        return $this->doDelete($this->getNamespacedId($id));
    }

    /**
     * Prefix the passed id with the configured namespace value
     *
     * @param string $id  The id to namespace
     * @return string $id The namespaced id
     */
    private function getNamespacedId($id) {
        return self::PREFIX.$id;
    }

}
