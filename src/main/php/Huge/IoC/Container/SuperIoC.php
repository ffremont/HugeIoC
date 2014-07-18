<?php

namespace Huge\IoC\Container;

use Huge\IoC\Factory\IFactory;
use Doctrine\Common\Cache\Cache;
use Huge\IoC\Scope;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Huge\IoC\Exceptions\InvalidBeanException;

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
     * @var \Logger
     */
    private $_logger;

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
        $this->_logger = \Logger::getLogger(__CLASS__);
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
        if (is_null($definition)) {
            foreach ($this->otherContainers as $ioc) {
                $def = $ioc->getDefinitionById($id);
                if (!is_null($def)) {
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

        $this->_logger->trace('chargement du bean : ' . $id);
        $this->beans[$id] = $definition['factory']->create($definition['class']);

        $deps = isset($this->deps[$id]) ? $this->deps[$id] : array();
        foreach ($deps as $aDep) {
            $def = $this->getDefinitionById($aDep['ref']);
            if (is_null($def)) {
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
            if (is_null($def)) {
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
        foreach ($this->definitions as &$definition) {
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
        $cacheKey = self::whoAmI() . md5(serialize($this->definitions)) . $this->version . '_loadDeps';
        if (!is_null($this->cacheImpl)) {
            $deps = $this->cacheImpl->fetch($cacheKey);
            if ($deps !== FALSE) {
                $this->deps = $deps;
                $this->_logger->trace('récupération dans le cache des dépendances des beans du conteneur');
                return;
            }
        }
        $this->_logger->trace('recherche des dépendances des beans du conteneur');

        $annotationReader = new AnnotationReader();
        foreach ($this->definitions as &$definition) {
            $RClass = new \ReflectionClass($definition['class']);
            $props = $RClass->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED);
            $depsOfBean = array();
            foreach ($props as $prop) {
                $annotation = $annotationReader->getPropertyAnnotation($prop, 'Huge\IoC\Annotations\Autowired');
                if (!is_null($annotation) && !empty($annotation->value)) {
                    $depsOfBean[] = array(
                        'property' => $prop->getName(),
                        'ref' => trim($annotation->value, '\\')
                    );
                }
            }

            $this->deps[$definition['id']] = $depsOfBean;
        }

        if (!is_null($this->cacheImpl)) {
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
                $this->_logger->warn('Factory du bean invalide : ' . $definition['class']);
                continue;
            }

            $RClass = new \ReflectionClass($definition['class']);
            if ($annotationReader->getClassAnnotation($RClass, 'Huge\IoC\Annotations\Component') !== null) {
                $list[$definition['id']] = $definition;
            } else {
                $this->_logger->warn('Annotation @Component manquante pour la classe : ' . $definition['class']);
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
        if ($this->_logger->isTraceEnabled()) {
            $this->_logger->trace('récupération du bean : ' . $id);
        }
        $id = trim($id, '\\');

        if (isset($this->beans[$id])) {
            return $this->beans[$id];
        }

        $def = $this->getDefinitionById($id);
        if (is_null($def)) {
            $bean = null;
            foreach ($this->otherContainers as $ioc) {
                $bean = $ioc->getBean($id);
                if (!is_null($bean)) {
                    break;
                }
            }
            return $bean;
        } else {
            if ($this->_logger->isTraceEnabled()) {
                $this->_logger->trace('existance du bean : ' . $id);
            }
            $this->_loadBean($def);
            return $this->beans[$def['id']];
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
        if (!is_null($this->cacheImpl)) {
            $beans = $this->cacheImpl->fetch($cacheKey);
            if ($beans !== FALSE) {
                return $beans;
            }
        }

        $beans = array();
        foreach ($this->definitions as $definition) {
            $impls = class_implements($definition['class']);
            if (in_array($implClassName, $impls)) {
                $beans[] = $definition['id'];
            }
        }
        foreach ($this->otherContainers as $ioc) {
            $beans = array_merge($beans, $ioc->findBeansByImpl($implClassName));
        }

        if (!is_null($this->cacheImpl)) {
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
        if (!is_null($this->cacheImpl)) {
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

        if (!is_null($this->cacheImpl)) {
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
        $this->beans[self::whoAmI()] = $this;

        $this->_loadDeps();
        $this->_loadBeans(Scope::REQUEST);
    }

    public function getDefinitions() {
        return $this->definitions;
    }

    public final function addDefinitions($definitions) {
        $cacheKey = self::whoAmI() . md5(serialize($definitions)) . $this->version . 'addDefinitions';
        if (!is_null($this->cacheImpl)) {
            $cacheDefinitions = $this->cacheImpl->fetch($cacheKey);
            if ($cacheDefinitions !== FALSE) {
                $definitions = $cacheDefinitions;
                $this->_logger->trace('récupération dans le cache des définitions à ajouter du conteneur');
            }
        }

        $definitions = $this->_normalizeDefinitions($definitions);

        if (!is_null($this->cacheImpl)) {
            $this->cacheImpl->save($cacheKey, $definitions);
        }

        $this->definitions = array_merge($definitions, $this->definitions);
    }

    public function getOtherContainers() {
        return $this->otherContainers;
    }

    public function addOtherContainers($otherContainers) {
        $list = array();
        foreach ($otherContainers as $ioc) {
            if ($ioc instanceof SuperIoC) {
                if (!is_null($this->cacheImpl)) {
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

}

