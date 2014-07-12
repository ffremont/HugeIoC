<?php

namespace Huge\IoC\Factory;

abstract class SimpleFactory{
    private static $instance = null;
    
    public static function getInstance(){
        if(is_null(self::$instance)){
            self::$instance = new ConstructFactory(array());
        }
        
        return self::$instance;
    }
}

