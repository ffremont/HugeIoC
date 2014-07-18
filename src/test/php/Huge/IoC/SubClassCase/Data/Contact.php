<?php

namespace Huge\IoC\SubClassCase\Data;

use Huge\IoC\Annotations\Component;

/**
 * @Component
 */
class Contact extends Person{ 

    public function __construct() {
        parent::__construct();
    }

}

