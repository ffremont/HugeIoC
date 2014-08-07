<?php

namespace Huge\IoC\Container;

use Huge\IoC\Factory\IFactory;
use Doctrine\Common\Cache\Cache;
use Huge\IoC\Scope;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Huge\IoC\Exceptions\InvalidBeanException;
use Huge\IoC\Utils\IocArray;
use \Psr\Log\NullLogger;

abstract class SuperIoC implements IContainer {

    /**
     * ID_BEAN =>  array( id, class, factory)
     * 
     * @var array
     */
    private $definitions;

    /**
     * Liste des container IoC
     * 
     * @var array
     */
    private $otherContainers;

    /**
     * Liste des beans
     * 
     * @var array
     */
    protected $beans;

    /**
     * Liste des dépendances des beans
     * 
     * @var array
     *  ID_BEAN => array(
     *      array(property,ref)
     * )
     */
    protected $deps;

    /**
     *
     * @var \Doctrine\Common\Cache\Cache
     */
    private $cacheImpl;

    /**
     *
     * @var string
     */
    protected $version;

    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct($version = '') {
        $this->version = $version;
        $class = self::whoAmI();
        $this->definitions = array(
            $class => array(
                'id' => $class,
                'class' => $class,
                'factory' => null
            )
        );
        $this->otherContainers = array();
        $this->beans = array();
        $this->deps = array();
        $this->cacheImpl = null;
        $this->logger = new NullLogger();
    }

    /**
     * Retourne la définition du bean si elle existe
     * On recherche la définition dans les conteneurs associés
     * 
     * @param string $id
     * @return array|null
     */
    public final function getDefinitionById($id) {
        $definition = isset($this->definitions[$id]) ? $this->definitions[$id] : null;
        if ($definition === null) {
            $iocCount = count($this->otherContainers);
            for($i = 0; $i < $iocCount; $i++){
                $def = $this->otherContainers[$i]->getDefinitionById($id);
                if ($def !== null) {
                    $definition = $def;
                    break;
                }
            }
        }

        return $definition;
    }

    /**
     * Charge un bean à partir de sa définition
     * 
     * @param array $definition
     * @throws \Huge\IoC\Exceptions\InvalidBeanException s'il existe plusieurs implémentation d'une interface / sous classe à injecter
     */
    private function _loadBean($definition) {
        $id = $definition['id'];
        if (isset($this->beans[$id])) {
            return;
        }
        
        $this->beans[$id] = $definition['factory']->create($definition['class']);

        $deps = isset($this->deps[$id]) ? $this->deps[$id] : array();
        $depsCount = count($deps);
        for($i = 0; $i < $depsCount; $i++){
            $aDep = $deps[$i];
            $def = $this->getDefinitionById($aDep['ref']);
            if ($def === null) {
                $implBeans = $this->findBeansByImpl($aDep['ref']);
                $countImpl = count($implBeans);
                if ($countImpl === 1) {
                    $def = $this->getDefinitionById($implBeans[0]);
                } else if ($countImpl > 1) {
                    throw new InvalidBeanException($id, 'Multi implémentation pour une interface : '.$aDep['ref'], 1);
                } else {
                    $subClasses = $this->findBeansBySubClass($aDep['ref']);
                    $countSubClasses = count($subClasses);
                    if ($countSubClasses === 1) {
                        $def = $this->getDefinitionById($subClasses[0]);
                        
                    } else if ($countSubClasses > 1) {
                        throw new InvalidBeanException($id, 'Multi sous classe pour une interface : '.$aDep['ref'], 1);
                    }
                }
            }

            $setter = 'set' . ucfirst($aDep['property']);
            if ($def === null) {
                $this->beans[$id]->$setter(null);
            } else {
                $this->_loadBean($def);
                $this->beans[$id]->$setter($this->beans[$def['id']]);
            }
        }
    }

    /**
     * 
     * @param int $scope
     */
    private function _loadBeans($scope) {
        $keys = array_keys($this->definitions);
        $keyCount = count($keys);
        for($i = 0; $i < $keyCount; $i++){
            $definition = $this->definitions[ $keys[$i] ];
            if (isset($definition['factory']) && ($definition['factory']->getScope() === $scope)) {
                $this->_loadBean($definition);
            }
        }
    }

    /**
     * 
     * @Cacheable
     */
    private function _loadDeps() {
        $cacheKey = self::whoAmI() . md5(json_encode(array_keys($this->definitions))) . $this->version . '_loadDeps';
        if ($this->cacheImpl !== null) {
            $deps = $this->cacheImpl->fetch($cacheKey);
            if ($deps !== FALSE) {
                $this->deps = $deps;
                $this->logger->debug('chargement depuis le cache des dépendances des beans');
                return;
            }
        }
        $this->logger->debug('rafraichissement du cache : dépendances des beans');

        $annotationReader = new AnnotationReader();
        foreach ($this->definitions as &$definition) {
            $RClass = new \ReflectionClass($definition['class']);
            $props = $RClass->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED);
            $depsOfBean = array();
            foreach ($props as $prop) {
                $annotation = $annotationReader->getPropertyAnnotation($prop, 'Huge\IoC\Annotations\Autowired');
                if (($annotation !== null) && !empty($annotation->value)) {
                    $depsOfBean[] = array(
                        'property' => $prop->getName(),
                        'ref' => trim($annotation->value, '\\')
                    );
                }
            }

            $this->deps[$definition['id']] = $depsOfBean;
        }

        if ($this->cacheImpl !== null) {
            $this->cacheImpl->save($cacheKey, $this->deps);
        }
    }

    /**
     * Normalise les définitions
     * 
     * @param array $definitions
     * @return array
     */
    private function _normalizeDefinitions($definitions) {
        $list = array();

        $annotationReader = new AnnotationReader();
        foreach ($definitions as &$definition) {
            $definition['class'] = trim($definition['class'], '\\');
            if (!isset($definition['id'])) {
                $definition['id'] = $definition['class'];
            }

            if (!isset($definition['factory']) || !($definition['factory'] instanceof IFactory)) {
                $this->logger->warning('Factory du bean invalide : ' . $definition['class']);
                continue;
            }

            $RClass = new \ReflectionClass($definition['class']);
            if ($annotationReader->getClassAnnotation($RClass, 'Huge\IoC\Annotations\Component') !== null) {
                $list[$definition['id']] = $definition;
            } else {
                $this->logger->warning('Annotation @Component manquante pour la classe : ' . $definition['class']);
            }
        }

        return $list;
    }

    /**
     * Retourne le nom de la classe courante
     * 
     * @return string
     */
    public final static function whoAmI() {
        return get_called_class();
    }

    /**
     * Procède à l'enregistrement du loader pour Doctrine Annotations
     * 
     * @param array $call
     */
    public final static function registerLoader($call) {
        \Doctrine\Common\Annotations\AnnotationRegistry::registerLoader($call);
    }

    /**
     * Retourne l'objet stocké dans le conteneur. L'Identifiant peut être le nom de la classe ou celui donné explicitement lors de la définition du bean.
     * S'il existe 2 beans ayant le même ID, le 1er sera retenu et retourné.
     * 
     * @param string $id
     * @return object|null
     * @throws \Huge\IoC\Exceptions\InvalidBeanException s'il existe plusieurs implémentation d'une interface / sous classe à injecter
     */
     public final function getBean($id) {
        $id = trim($id, '\\');

        if (isset($this->beans[$id])) {
            return $this->beans[$id];
        }

        $def = $this->getDefinitionById($id);        
        if ($def === null) {
            $bean = null;
            foreach ($this->otherContainers as $ioc) {
                $bean = $ioc->getBean($id);
                if ($bean !== null) {
                    return $bean;
                }
            }
        } else {
            $this->_loadBean($def);
            return $this->beans[$def['id']];
        }

        // recherche du bean en tant qu'interface
        $implBeans = $this->findBeansByImpl($id);
        if(count($implBeans) === 1){
            return $this->getBean($implBeans[0]);
        }else{
            $this->logger->warning('Double implémentation de "'.$id.'"');
            return null;
        }
        
        // recherche du bean en tant que classe parente
        $subClassBeans = $this->findBeansBySubClass($id);
        if(count($subClassBeans) === 1){
            return $this->getBean($subClassBeans[0]);
        }else{
            $this->logger->warning('2 sous classe de "'.$id.'" sous définies');
            return null;
        }
        
        return null;
    }

    /**
     * Recherche la liste des beans qui implémentent l'interface
     * 
     * @param string $implClassName
     * @return array liste des ID des beans implémentant d'interface
     */
    public final function findBeansByImpl($implClassName) {
        $cacheKey = self::whoAmI() . $this->version . $implClassName . 'findByImpl';
        if ($this->cacheImpl !== null) {
            $beans = $this->cacheImpl->fetch($cacheKey);
            if ($beans !== FALSE) {
                return $beans;
            }
        }

        $beans = array();
        foreach ($this->definitions as $definition) {
            $impls = class_implements($definition['class']);
            if (IocArray::in_array($implClassName, $impls)) {
                $beans[] = $definition['id'];
            }
        }
        foreach ($this->otherContainers as $ioc) {
            $beans = array_merge($beans, $ioc->findBeansByImpl($implClassName));
        }

        if ($this->cacheImpl !== null) {
            $this->cacheImpl->save($cacheKey, $beans);
        }
        return $beans;
    }

    /**
     * Retourne la liste des identifiants des beans qui sont des sous classe de $parentClass
     * 
     * @param string $parentClass nom de la classe parente 
     * @return array liste de identifiants des beans
     */
    public final function findBeansBySubClass($parentClass) {
        $cacheKey = self::whoAmI() . $this->version . $parentClass . 'findBeansBySubClass';
        if ($this->cacheImpl !== null) {
            $beans = $this->cacheImpl->fetch($cacheKey);
            if ($beans !== FALSE) {
                return $beans;
            }
        }

        $beans = array();
        foreach ($this->definitions as $definition) {
            if (is_subclass_of($definition['class'], $parentClass)) {
                $beans[] = $definition['id'];
            }
        }
        foreach ($this->otherContainers as $ioc) {
            $beans = array_merge($beans, $ioc->findBeansBySubClass($parentClass));
        }

        if ($this->cacheImpl !== null) {
            $this->cacheImpl->save($cacheKey, $beans);
        }
        return $beans;
    }

    /**
     * 
     * @return void
     */
    public function start() {
        $iocCount = count($this->otherContainers);
        for($i=0; $i < $iocCount; $i++){
            $this->otherContainers[$i]->start();
        }

        $this->beans[self::whoAmI()] = $this;

        $this->_loadDeps();
        $this->_loadBeans(Scope::REQUEST);
    }

    public function getDefinitions() {
        return $this->definitions;
    }

    public final function addDefinitions($definitions) {
        $cacheKey = self::whoAmI() . md5(json_encode(array_keys($definitions))) . $this->version . 'addDefinitions';
        if ($this->cacheImpl !== null) {
            $cacheDefinitions = $this->cacheImpl->fetch($cacheKey);
            if ($cacheDefinitions !== FALSE) {
                $definitions = $cacheDefinitions;
                $this->logger->debug('récupération dans le cache des définitions à ajouter du conteneur');
            }
        }

        $definitions = $this->_normalizeDefinitions($definitions);

        if ($this->cacheImpl !== null) {
            $this->cacheImpl->save($cacheKey, $definitions);
        }

        $this->definitions = array_merge($definitions, $this->definitions);
    }

    public function getOtherContainers() {
        return $this->otherContainers;
    }
    
    /**
     * Retourne tous les conteneurs ioc
     * 
     * @return array
     */
    public function getAllOtherContainers(){
        $containers = array();
        
        /* @var $ioc \Huge\IoC\Container\SuperIoC */
        foreach($this->otherContainers as $ioc){
            $containers = array_merge($containers, $ioc->getAllOtherContainers());
        }
        
        return $containers;
    }

    public function addOtherContainers($otherContainers) {
        $list = array();
        $iocCount = count($otherContainers);
        for($i=0; $i < $iocCount; $i++){
            $ioc = $otherContainers[$i];
            if ($ioc instanceof SuperIoC) {
                if ($this->cacheImpl !== null) {
                    $ioc->setCacheImpl($this->cacheImpl);
                }
                $list[] = $ioc;
            }
        }

        $this->otherContainers = array_merge($this->otherContainers, $list);
    }

    public function getBeans() {
        return $this->beans;
    }

    public function getCacheImpl() {
        return $this->cacheImpl;
    }

    public function setCacheImpl(Cache $cacheImpl) {
        $this->cacheImpl = $cacheImpl;
    }

    public function getVersion() {
        return $this->version;
    }

    public function setVersion($version) {
        $this->version = $version;
    }
    
    public function getLogger() {
        return $this->logger;
    }

    public function setLogger(\Psr\Log\LoggerInterface $logger) {
        $this->logger = $logger;
    }

}

