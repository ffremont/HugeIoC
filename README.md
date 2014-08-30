Huge IoC
=======

Framework IoC Simple et efficace pour php5.
Le principe de cette librairie est de gérer les instances des objets PHP à votre place. De cette façon vous n'êtes plus obligé de gérer vous-même dans vos constructeurs les instances en paramètres. Il est possible d'injecter via une annotation @Autowired.

## Installation
* Installer avec composer
``` json
    {
        "require": {
           "huge/ioc": "..."
        }
    }
```
* Cache : https://github.com/doctrine/cache

```php
  $loader = require(__DIR__.'/../../../vendor/autoload.php');
  
  // nécessaire charger les annotations
  \Huge\IoC\Container\SuperIoC::registerLoader(array($loader, 'loadClass'));
```


## Fonctionnalités

* Définition d'un bean : @Component
* Gestion de plusieurs conteneurs d'objets
* Injection des instances via l'annotation @Autowired("ID_BEAN")
* Injection d'une implémentation via l'annotation @Autowired("INTERFACE")
* Injection d'une sous classe via l'annotation @Autowired("CLASSE_PARENTE")
* Gère l'instanciation request ou lazy (sur demande)
* Surcharge IFactory pour l'instanciation
* Création de conteneur spécifique possible (entends SuperIoC)
* Cache : basé sur doctrine cache
* Annotations basé sur doctrine annotations


## Conteneurs
* Etendre \Huge\IoC\Container\SuperIoC
```php
    namespace MyApp;

    class GarageIoC extends \Huge\IoC\Container\SuperIoC{
            public function __construct($config) {
                parent::__construct(__CLASS__, '1.0');

                $memcache = new Memcache();
                $memcache->connect($config['memcache.host'], $config['memcache.port']);
                $cache = new \Doctrine\Common\Cache\MemcacheCache();
                $cache->setMemcache($memcache);

                $this->setCacheImpl($cache);
                $this->addDefinitions(array(
                    array(
                        'class' => 'Huge\IoC\Fixtures\Contact',
                        'factory' => new \Huge\Io\Factory\ConstructFactory(array('DUPUIT', 'Pierre'))
                    )
                ));
                $this->addOtherContainers(array(
                    new AudiIoC(),
                    new RenaultIoC()
                ));
            }
    }
```
* Attention, il est nécessaire de mettre à jour la version en cas de relivraison (rafraîchissement du cache)
* 
## Factories
1. Créer vos factories : implémenter Huge\IoC\Factory\IFactory
```php
    namespace MyApp;

    class MyNullFactoy implements \Huge\IoC\Factory\IFactory{
            public function __construct() {}

            public function create($classname) {
                        return null;
            }
    }

```

```php
$c = new DefaultIoC();
$c->addDefinitions(array(
    array('class' => 'MyClass', 'factory' => new MyNullFactory())
));
```

## Injecter les instances
Injecter dans vos beans d'autres beans
```php
    use Huge\IoC\Annotations\Component;
    use Huge\IoC\Annotations\Autowired;
    
    /**
    * @Component
    */
    class MyController{
        /**
        * @Autowired("MyApp\MyCustomIoC")
        */
        private $ioc;
        
        /**
        * @Autowired("Huge\IoC\Fixtures\Contact");
        */
        private $daoContact;
        
        /**
         * @Autowired("Huge\IoC\Factory\ILogFactory")
         * @var \Huge\IoC\Factory\ILogFactory
         */
        private $loggerFactory;
        
        /**
        * Nécessaire au conteneur pour setter la valeur
        */
        public function setIoc($ioc){
            $this->ioc = $ioc;
        }
        public function setDaoContact($contact){
            $this->daoContact = $contact;
        }
        
        public function getLoggerFactory() {
            return $this->loggerFactory;
        }
    
        public function setLoggerFactory(\Huge\IoC\Factory\ILogFactory $loggerFactory) {
            $this->loggerFactory = $loggerFactory;
        }
    }
```


## Limitations
* Cache Doctrine
* Annotations Doctrine
* Logger basé sur l'interface Psr\Log

## Cache
Utilisation des implémentations Doctrine\Common\Cache\Cache
```php
    $c = new DefaultIoC('default', '1.0');
    $c->setCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
```

Mettre en cache mes définitions de beans, par défaut "false". Attention si vous utilisez des variables aux RUN, car elles seront cachées.
```php
$ioc->addDefinitions($definitions, true);
```

## Logger
1. Implémentation du composant factory : Huge\IoC\Factory\ILogFactory
```php
$ioc = new DefaultIoC();
$ioc->setLogger(new MyApp\Log4phpLoggerImpl('DefaultIoC'));
$ioc->addDefinitions(array(
    array(
        'class' => 'MyApp\Log4phpFactoryImpl',
        'factory' => SimpleFactory::getInstance() // retourne un singleton (optimisation)
    )
));
```
2. Plus d'information : http://www.php-fig.org/psr/psr-3/

