<?php

namespace Litesupabase\Library;

class Utility
{
    const ENV_DEV = "dev";
    const ENV_QA = "qa";
    const ENV_PRO = "pro";

    public static function test() : void
    {
        echo 123,PHP_EOL;
    }
}