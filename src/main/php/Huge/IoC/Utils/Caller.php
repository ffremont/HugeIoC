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
            case 0: return $object->$funcName(); break;
            case 1: return $object->$funcName($args[0]); break;
            case 2: return $object->$funcName($args[0], $args[1]); break;
            case 3: return $object->$funcName($args[0], $args[1], $args[2]); break;
            case 4: return $object->$funcName($args[0], $args[1], $args[2], $args[3]); break;
            case 5: return $object->$funcName($args[0], $args[1], $args[2], $args[3], $args[4]); break;
            default: return call_user_func_array(array($object, $funcName), $args); break;
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
            case 0: return $className::$funcName(); break;
            case 1: return $className::$funcName($args[0]); break;
            case 2: return $className::$funcName($args[0], $args[1]); break;
            case 3: return $className::$funcName($args[0], $args[1], $args[2]); break;
            case 4: return $className::$funcName($args[0], $args[1], $args[2], $args[3]); break;
            case 5: return $className::$funcName($args[0], $args[1], $args[2], $args[3], $args[4]); break;
            default: return call_user_func_array($className.'::'.$funcName, $args); break;
        }
    }
}

