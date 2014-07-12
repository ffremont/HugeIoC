<?php

namespace Huge\IoC\Container;

use \Huge\Core\Cache\ICache;
use Huge\IoC\Scope;

abstract class SuperIoC implements IContainer {

    const REX_AUTOWIRED = '#@Autowired\("(.*)"\)#';

    /**
     * ID_BEAN =>  array( id, class, factory)
     * 
     * @var array
     */
    private $definitions;
    private $otherContainers;
    protected $beans;

    /**
     *
     * @var array
     *  ID_BEAN => array(
     *      array(property,ref)
     * )
     */
    protected $deps;

    /**
     *
     * @var \Huge\Core\Cache\ICache
     */
    private $cacheImpl;
    
    /**
     *
     * @var string
     */
    private $version;
    
    private $_logger;

    public function __construct($version = '') {
        $this->version = $version;
        $this->definitions = array();
        $this->otherContainers = array();
        $this->beans = array();
        $this->deps = array();
        $this->cacheImpl = null;
        $this->_logger = \Logger::getLogger(__CLASS__);
    }

    /**
     * 
     * @param array $definition
     * @return Object
     */
    private function _loadBean($definitionOrId) {
        $definition = is_array($definitionOrId) ? $definitionOrId : $this->definitions[$definitionOrId];
        $id = $definition['id'];
        if (isset($this->beans[$id])) {
            return;
        }
        $this->_logger->trace('chargement du bean : '.$id);

        $this->beans[$id] = $definition['factory']->create($definition['class']);
        
        $deps = isset($this->deps[$id]) ? $this->deps[$id] : array();
        foreach ($deps as $aDep) {
            $this->_loadBean($aDep['ref']);
            $setter = 'set'.ucfirst($aDep['property']);
            $this->beans[$id]->$setter($this->beans[$aDep['ref']]);
        }
    }

    /**
     * 
     * @param int $scope
     */
    private function _loadBeans($scope) {
        foreach ($this->definitions as &$definition) {
            if ($definition['factory']->getScope() === $scope) {
                $this->_loadBean($definition);
            }
        }
    }
    
    private function _existsBeanDef($id){
        return isset($this->definitions[$id]);
    }

    /**
     * 
     * @param string $id
     * @return mixed
     */
    public function getBean($id) {
        $this->_logger->trace('récupération du bean : '.$id);
        
        if ($this->_existsBeanDef($id)) {
            $this->_logger->trace('existance du bean');
            $this->_loadBean($id);
            return $this->beans[$id];
        }else{
            $bean = null;
            foreach ($this->otherContainers as $ioc) {
                $bean = $ioc->getBean($id);
                if (!is_null($bean)) {
                    break;
                }
            }
            return $bean;
        } 

        return null;
    }
    
    private static function _whoAmI() {
        return get_called_class();
    }

    /**
     * @Cacheable
     */
    private function _loadDeps() {
        $cacheKey = self::_whoAmI().$this->version.'_loadDeps';
        if(!is_null($this->cacheImpl)){
            if($this->cacheImpl->contains($cacheKey)){
                $this->deps = $this->cacheImpl->fetch($cacheKey);
                $this->_logger->trace('récupération dans le cache des dépendances des beans du conteneur');
                return;
            }
        }
        
        $this->_logger->trace('recherche des dépendances des beans du conteneur');
        foreach ($this->definitions as &$definition) {
            $RClass = new \ReflectionClass($definition['class']);
            $props = $RClass->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED);
            $depsOfBean = array();
            foreach ($props as $prop) {
                $matches = array();
                preg_match(self::REX_AUTOWIRED, $prop->getDocComment(), $matches);
                if (count($matches) >= 2) {
                    $depsOfBean[] = array(
                        'property' => $prop->getName(),
                        'ref' => $matches[1]
                    );
                }
            }

            $this->deps[$definition['id']] = $depsOfBean;
        }
        
        if(!is_null($this->cacheImpl)){
            $this->cacheImpl->save($cacheKey, $this->deps);
        }
    }
    
    /**
     * Recherche la liste des beans qui implémentent l'interface
     * 
     * @param string $implClassName
     * @return array liste des ID des beans implémentant d'interface
     */
    public function findBeansByImpl($implClassName){
        $cacheKey = self::_whoAmI().$this->version.$implClassName.'findByImpl';
        if(!is_null($this->cacheImpl)){
            if($this->cacheImpl->contains($cacheKey)){
                return $this->cacheImpl->fetch($cacheKey);
            }
        }
        
        $beans = array();
        foreach($this->definitions as $definition){
            $impls = class_implements($definition['class']);
            if(in_array($implClassName, $impls)){
                $beans[] = $definition['id'];
            }
        }
        foreach ($this->otherContainers as $ioc) {
            $beans = array_merge($beans, $ioc->findBeansByImpl($implClassName));
        }        
        
        if(!is_null($this->cacheImpl)){
            $this->cacheImpl->save($cacheKey, $beans);
        }
        return $beans;
    }

    /**
     * 
     * @return void
     */
    public function start() {
        foreach ($this->otherContainers as $ioc) {
            $ioc->start();
        }

        $this->_logger->trace('démarrage du conteneur');
        $this->_loadDeps();
        $this->_loadBeans(Scope::ON_LOAD);
    }

    public function getDefinitions() {
        return $this->definitions;
    }

    public function setDefinitions($definitions) {
        foreach ($definitions as $definition) {
            if (!isset($definition['id'])) {
                $definition['id'] = $definition['class'];
            }
            $this->definitions[$definition['id']] = $definition;
        }
    }

    public function getOtherContainers() {
        return $this->otherContainers;
    }

    public function setOtherContainers($otherContainers) {
        if (!is_null($this->cacheImpl)) {
            foreach ($otherContainers as $ioc) {
                if ($ioc instanceof SuperIoC) {
                    $ioc->setCacheImpl($this->cacheImpl);
                }
            }
        }

        $this->otherContainers = $otherContainers;
    }

    public function getBeans() {
        return $this->beans;
    }

    public function getCacheImpl() {
        return $this->cacheImpl;
    }

    public function setCacheImpl(ICache $cacheImpl) {
        $this->cacheImpl = $cacheImpl;
    }

}

