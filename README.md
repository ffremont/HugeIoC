Huge IoC
=======

Framework IoC Simple et efficace pour php5.
Le principe de cette librairie est de gérer les instances des objets PHP à votre place. De cette façon vous n'êtes plus obligé de gérer vous-même dans vos constructeurs les instances en paramètres. Il est possible d'injecter via une annotation @Autowired.


##Installation
Installer avec composer
``` json
    {
        "require": {
           "huge/ioc": "..."
        }
    }
```

```php
  $loader = require(__DIR__.'/../../../vendor/autoload.php');
  
  // nécessaire charger les annotations
  \Huge\IoC\Container\SuperIoC::registerLoader(array($loader, 'loadClass'));
```

## Fonctionnalités
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

## Pourquoi ?
Rien, il n'existe rien sur les mécaniques IoC SIMPLE et FLEXIBLE en php5. Mon souhait est de construire une librairie légère pour charger facilement et rapidement des instances à la mode Spring.


## Exemples de configuration
``` php
    // instanciation du conteneur par défaut
    $c = new DefaultIoC();
    $c2 = new DefaultIoC();
    
    // définition des beans (instances), l'instanciation se fera dans la Factory
    $c->addDefinitions(array(
        array(
            'id' => 'contact',
            'class' => 'Huge\IoC\Fixtures\Contact',
            'factory' => SimpleFactory::getInstance() // retourne un singleton (optimisation)
        ),
        array(
            'id' => 'client',
            'class' => 'Huge\IoC\Fixtures\Client',
            'factory' => new ConstructFactory(array(new RefBean('contact', $c), '001'))
        )
    ));
    // possibilité de charger d'autres conteneurs, dans le cas où l'on travail de façon modulaire
    $c->addOtherContainers(array($c2));
    
    // init du conteneur
    $c->start();
    
    $monContact = $c->getBean('contact');
```

## Extensible 
1. Créer vos factories : implémenter Huge\IoC\FactoryIFactory
2. Créer vos conteneurs qui chargent/définissent des beans
```php
    namespace MyApp;

    class MyCustomIoC extends \Huge\IoC\Container\SuperIoC{
            /**
            * @param $version permet de construire des clefs de cache cloisonnées par version 
            * (très très pratique pour les déploiements en production)
            */
            public function __construct($version) {
                parent::__construct($version);

                $this->addDefinitions(array(
                    array(
                        'class' => 'Huge\IoC\Fixtures\Contact',
                        'factory' => new ConstructFactory(array('DUPUIT', 'Pierre'))
                    )
                ));
            }
    }
```
3. Injecter dans vos beans d'autres beans
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
        private $daoContact
        
        /**
        * Nécessaire au conteneur pour setter la valeur
        */
        public function setIoc($ioc){
            $this->ioc = $ioc;
        }
        public function setDaoContact($contact){
            $this->daoContact = $contact;
        }
    }
```
