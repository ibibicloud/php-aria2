<?php

namespace ibibicloud\facade;

use think\Facade;

class Aria2 extends Facade
{
    protected static function getFacadeClass()
    {
    	return 'ibibicloud\Aria2';
    }
}