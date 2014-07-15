<?php

namespace Huge\IoC\Container;

use \Huge\IoC\Factory\IFactory;
use Doctrine\Common\Cache\Cache;
use Huge\IoC\Scope;

abstract class SuperIoC implements IContainer {
    /**
     * Annotation pour injecter un bean dans une classe
     */

    const REX_AUTOWIRED = '#@Autowired\("(.*)"\)#';

    /**
     * Annotation pour définir une classe en tant que composant (bean)
     */
    const REX_COMPONENT = '#@Component#';

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
    private $version;

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

        $this->_logger->trace('chargement du bean : ' . $id);

        $this->beans[$id] = $definition['factory']->create($definition['class']);

        $deps = isset($this->deps[$id]) ? $this->deps[$id] : array();
        foreach ($deps as $aDep) {
            $this->_loadBean($aDep['ref']);
            $setter = 'set' . ucfirst($aDep['property']);
            $this->beans[$id]->$setter($this->beans[$aDep['ref']]);
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
     * Retourne vrai si le bean est définit
     * 
     * @param string $id
     * @return boolean
     */
    private function _existsBeanDef($id) {
        return isset($this->definitions[$id]);
    }

    /**
     * Retourne l'objet stocké dans le conteneur. L'Identifiant peut être le nom de la classe ou celui donné explicitement lors de la définition du bean.
     * S'il existe 2 beans ayant le même ID, le 1er sera retenu et retourné.
     * 
     * @param string $id
     * @return mixed
     */
    public function getBean($id) {
        $this->_logger->trace('récupération du bean : ' . $id);

        if (isset($this->beans[$id])) {
            return $this->beans[$id];
        }

        if ($this->_existsBeanDef($id)) {
            $this->_logger->trace('existance du bean');
            $this->_loadBean($id);
            return $this->beans[$id];
        } else {
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

    public static function whoAmI() {
        return get_called_class();
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
        
        foreach ($definitions as &$definition) {
            if (!isset($definition['id'])) {
                $definition['id'] = $definition['class'];
            }

            if (!isset($definition['factory']) || !($definition['factory'] instanceof IFactory)) {
                $this->_logger->warn('Attribut factory du bean invalide : ' . $definition['class']);
                continue;
            }

            $RClass = new \ReflectionClass($definition['class']);
            if (preg_match(self::REX_COMPONENT, $RClass->getDocComment())) {
                $list[$definition['id']] = $definition;
            } else {
                $this->_logger->warn('Annotation @Component manquante pour la classe : ' . $definition['class']);
            }
        }
        
        return $list;
    }

    /**
     * Recherche la liste des beans qui implémentent l'interface
     * 
     * @param string $implClassName
     * @return array liste des ID des beans implémentant d'interface
     */
    public function findBeansByImpl($implClassName) {
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

    public function addDefinitions($definitions) {
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

    public function setCacheImpl(Cache $cacheImpl) {
        $this->cacheImpl = $cacheImpl;
    }

}

