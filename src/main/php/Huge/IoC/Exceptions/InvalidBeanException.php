<?php

namespace Huge\IoC\Exceptions;

class InvalidBeanException extends \Exception{

        private $beanId;
        
        public function __construct($beanId = '', $message = '', $code = '') {
            parent::__construct($message, $code);
            
            $this->beanId = $beanId;
        }
}

