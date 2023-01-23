<?php

namespace App\Http\StreamServices\Mux;

class Signing extends Handler
{
    public static function doCreateSigningKey()
    {
        return self::request()->post('/system/v1/signing-keys')->object();
    }
}
