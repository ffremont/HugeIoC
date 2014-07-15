<?php
    $loader = require(__DIR__.'/../../../vendor/autoload.php');
    
    $loader->add('Huge\IoC\\', 'src/test/php/');
    \Doctrine\Common\Annotations\AnnotationRegistry::registerLoader(array($loader, 'loadClass'));
    
    $GLOBALS['resourcesDir'] = __DIR__.'/../resources';
    