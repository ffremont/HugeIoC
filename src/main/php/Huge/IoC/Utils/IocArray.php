<?php

namespace Huge\IoC\Utils;

class IocArray {

    public function __construct() {
        
    }
    
    public static function in_array($value, $array){
        $flip_var = array_flip($array);
        
        return isset($flip_var[$value]);
    }

}

