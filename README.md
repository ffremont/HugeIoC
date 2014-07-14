Huge IoC
=======

IoC Simple et efficace pour php5.
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
## Fonctionnalités
* Gestion de plusieurs conteneurs d'objets
* Injection des instances via l'annotation @Autowired("ID_BEAN")
* Gère l'instanciation on_load ou en mode lazy (sur demande)
* Surcharge à souhait du comportement par défaut
* Création de factory spécifique possible
* Création de conteneur spécifique possible (entends SuperIoC)
* Cache : basé sur doctrine cache

## Pourquoi ?
Rien, il n'existe rien sur les mécaniques IoC SIMPLE et FLEXIBLE en php5. Mon souhait est de construire une librairie légère pour charger facilement et rapidement des instances à la mode Spring.


## Exemples
``` php
    // instanciation du conteneur par défaut
    $c = new DefaultIoC();
    $c2 = new DefaultIoC();
    
    // définition des beans (instances), l'instanciation se fera dans la Factory
    $c->setDefinitions(array(
        array(
            'id' => 'contact',
            'class' => '\Huge\IoC\Fixtures\Contact',
            'factory' => \Huge\IoC\FactorySimpleFactory::getInstance() // retourne un singleton (optimisation)
        ),
        array(
            'id' => 'client',
            'class' => '\Huge\IoC\Fixtures\Client',
            'factory' => new ConstructFactory(array(new RefBean('contact', $c), '001'))
        )
    ));
    // possibilité de charger d'autres conteneur, dans le cas où l'on travail de façon modulaire
    $c->setOtherContainers(array($c2));
    
    // init du conteneur
    $c->start();
    
    $monContact = $c->getBean('contact');
```

## Extensible 
1. Créer vos factories : implémenter \Huge\IoC\FactoryIFactory
2. Créer vos conteneurs qui chargent/définissent des beans
```php
    class MyCustomIoC extends \Huge\IoC\Container\SuperIoC{
            public function __construct() {
                parent::__construct();

                $this->setDefinitions(array(
                    array(
                        'class' => '\Huge\IoC\Fixtures\Contact',
                        'factory' => new ConstructFactory(array('DUPUIT', 'Pierre'))
                    )
                ));
            }
    }
```
3. Créer votre système de cache : implémenter Huge\Core\Cache\ICache
