<?php

namespace Inilim\Router\Test\ForTest;

class RouterTestController
{
    function show($id)
    {
        echo $id;
    }

    function returnFalse()
    {
        echo 'returnFalse';

        return false;
    }

    static function staticReturnFalse()
    {
        echo 'staticReturnFalse';

        return false;
    }
}
