<?php

namespace Huge\IoC\Exceptions;

class InvalidBeanException extends \Exception{

        private $beanId;
        
        public function __construct($beanId = '', $message = '', $code = 0) {
            parent::__construct($message, $code);
            
            $this->beanId = $beanId;
        }
        
        public function getBeanId() {
            return $this->beanId;
        }
}

