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
     * Nom du composant
     * 
     * @var string
     */
    protected $name;

    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    
    /**
     * Nom de la classe fille
     * 
     * @var string
     */
    protected $childClassName;

    public function __construct($name = '', $version = '') {
        $this->name = $name;
        $this->version = $version;
        $this->childClassName = self::whoAmI();
        $this->definitions = array(
            $this->childClassName => array(
                'id' => $this->childClassName,
                'class' => $this->childClassName,
                'factory' => null
            )
        );
        $this->otherContainers = array();
        $this->beans = array($this->childClassName => $this);
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
        return isset($this->definitions[$id]) ? $this->definitions[$id] : null;
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

        $this->beans[$id] = $definition['factory']->create($this, $definition['class']);

        $deps = isset($this->deps[$id]) ? $this->deps[$id] : array();
        $depsCount = count($deps);
        for ($i = 0; $i < $depsCount; $i++) {
            $aDep = $deps[$i];
            $def = $this->getDefinitionById($aDep['ref']);
            if ($def === null) {
                $implBeans = $this->findBeansByImpl($aDep['ref']);
                $countImpl = count($implBeans);
                if ($countImpl === 1) {
                    $def = $this->getDefinitionById($implBeans[0]);
                } else if ($countImpl > 1) {
                    throw new InvalidBeanException($id, 'Multi implémentation pour une interface : ' . $aDep['ref'], 1);
                } else {
                    $subClasses = $this->findBeansBySubClass($aDep['ref']);
                    $countSubClasses = count($subClasses);
                    if ($countSubClasses === 1) {
                        $def = $this->getDefinitionById($subClasses[0]);
                    } else if ($countSubClasses > 1) {
                        throw new InvalidBeanException($id, 'Multi sous classe pour une interface : ' . $aDep['ref'], 1);
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
        for ($i = 0; $i < $keyCount; $i++) {
            $definition = $this->definitions[$keys[$i]];
            if (isset($definition['factory']) && ($definition['factory']->getScope() === $scope)) {
                $this->_loadBean($definition);
            }
        }
    }

    /**
     * Charge les dépendances (toutes les définitions)
     */
    private function _loadDeps() {
        $cacheKey = md5($this->childClassName . $this->name . $this->version . __FUNCTION__);
        if ($this->cacheImpl !== null) {
            $deps = $this->cacheImpl->fetch($cacheKey);
            if ($deps !== FALSE) {
                $this->deps = $deps;
                return;
            }
        }

        $annotationReader = new AnnotationReader();
        foreach ($this->definitions as $definition) {
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
     *  Normalise toutes les définitions et les retourne
     * 
     *  @param array $definitions
     *  @return array
     */
    private function _normalizeDefinitions() {
        $cacheKey = md5($this->childClassName. $this->name . $this->version . __FUNCTION__);
        if ($this->cacheImpl !== null) {
            $cacheDefinitions = $this->cacheImpl->fetch($cacheKey);
            if ($cacheDefinitions !== FALSE) {
                return $cacheDefinitions;
            }
        }

        $list = array();
        $annotationReader = new AnnotationReader();
        $definitions = $this->getAllDefinitions();
        foreach ($definitions as $definition) {
            $definition['class'] = trim($definition['class'], '\\');
            if (!isset($definition['id'])) {
                $definition['id'] = $definition['class'];
            }

            if ((!isset($definition['factory']) || !($definition['factory'] instanceof IFactory))) {
                if(!isset($this->beans[$definition['id']])){
                    $this->logger->warning('Factory du bean invalide : ' . $definition['class']);
                    continue;
                }
            }

            $RClass = new \ReflectionClass($definition['class']);
            if ($annotationReader->getClassAnnotation($RClass, 'Huge\IoC\Annotations\Component') !== null) {
                $list[$definition['id']] = $definition;
            } else {
                $this->logger->warning('Annotation @Component manquante pour la classe : ' . $definition['class']);
            }
        }

        if ($this->cacheImpl !== null) {
            $this->cacheImpl->save($cacheKey, $list);
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

        $def = $this->getDefinitionById($id);    // deep
        if ($def !== null) {
            $this->_loadBean($def);
            return $this->beans[$def['id']];
        }

        // recherche du bean en tant qu'interface
        $implBeans = $this->findBeansByImpl($id);
        if (count($implBeans) === 1) {
            return $this->getBean($implBeans[0]);
        } else {
            $this->logger->warning('Double implémentation de "' . $id . '"');
            return null;
        }

        // recherche du bean en tant que classe parente
        $subClassBeans = $this->findBeansBySubClass($id);
        if (count($subClassBeans) === 1) {
            return $this->getBean($subClassBeans[0]);
        } else {
            $this->logger->warning('2 sous classe de "' . $id . '" sous définies');
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
        $cacheKey = md5($this->childClassName . $this->name . $this->version . $implClassName . 'findByImpl');
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
        $cacheKey = md5($this->childClassName . $this->name . $this->version . $parentClass . 'findBeansBySubClass');
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
        $this->definitions = $this->_normalizeDefinitions();
        
        $this->_loadDeps();
        $this->_loadBeans(Scope::REQUEST);
    }

    public function getDefinitions() {
        return $this->definitions;
    }

    /**
     * 
     * @return array
     */
    public function getAllDefinitions() {
        $definitions = $this->definitions;

        /* @var $ioc \Huge\IoC\Container\SuperIoC */
        foreach ($this->otherContainers as $ioc) {
            $definitions = array_merge($definitions, $ioc->getAllDefinitions());
        }

        return $definitions;
    }

    /**
     * 
     * @param array $definitions
     * @param boolean $cacheable permet de mettre en cache les définitions (attention si vous utilisez des variables au RUN)
     */
    public final function addDefinitions($definitions) {
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
    public function getAllOtherContainers() {
        $containers = array();

        /* @var $ioc \Huge\IoC\Container\SuperIoC */
        foreach ($this->otherContainers as $ioc) {
            $containers = array_merge($containers, $ioc->getAllOtherContainers());
        }

        return $containers;
    }

    /**
     * Ajout de conteneurs "autre"
     * 
     * @param array $otherContainers
     */
    public function addOtherContainers($otherContainers) {
        $list = array();
        $iocCount = count($otherContainers);
        for ($i = 0; $i < $iocCount; $i++) {
            $ioc = $otherContainers[$i];
            if ($ioc instanceof SuperIoC) {
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

    public function setCacheImpl($cacheImpl) {
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

    public function getName() {
        return $this->name;
    }

}
