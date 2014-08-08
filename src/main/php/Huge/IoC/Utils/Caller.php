<?php

namespace Huge\IoC\Utils;

abstract class Caller {
    /**
     * Like call_user_func_array mais 2-3 fois plus rapide
     * 
     * @param string $object
     * @param string $funcName
     * @param array $args
     */
    public static function instance($object, $funcName, $args = array()){
        switch (count($args)) {
            case 0: $object->$funcName(); break;
            case 1: $object->$funcName($args[0]); break;
            case 2: $object->$funcName($args[0], $args[1]); break;
            case 3: $object->$funcName($args[0], $args[1], $args[2]); break;
            case 4: $object->$funcName($args[0], $args[1], $args[2], $args[3]); break;
            case 5: $object->$funcName($args[0], $args[1], $args[2], $args[3], $args[4]); break;
            default: call_user_func_array(array($object, $funcName), $args); break;
        }
    }
    
    /**
     * Like call_user_func_array mais 2-3 fois plus rapide
     * 
     * @param string $object
     * @param string $funcName
     * @param array $args
     */
    public static function statiq($className, $funcName, $args = array()){
        switch (count($args)) {
            case 0: $className::$funcName(); break;
            case 1: $className::$funcName($args[0]); break;
            case 2: $className::$funcName($args[0], $args[1]); break;
            case 3: $className::$funcName($args[0], $args[1], $args[2]); break;
            case 4: $className::$funcName($args[0], $args[1], $args[2], $args[3]); break;
            case 5: $className::$funcName($args[0], $args[1], $args[2], $args[3], $args[4]); break;
            default: call_user_func_array($className.'::'.$funcName, $args); break;
        }
    }
}

