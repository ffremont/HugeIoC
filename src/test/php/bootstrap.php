<?php
    $loader = require(__DIR__.'/../../../vendor/autoload.php');
    
    $loader->add('Huge\IoC\\', 'src/test/php/');
    
    $GLOBALS['resourcesDir'] = __DIR__.'/../resources';
    