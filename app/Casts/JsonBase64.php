<?php
 
namespace App\Casts;
 
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
 
class JsonBase64 implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return array
     */
    public function get($model, $key, $value, $attributes)
    {
        $value = json_decode($value, true);
        return array_map('base64_decode', $value);
    }
 
    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  array  $value
     * @param  array  $attributes
     * @return string
     */
    public function set($model, $key, $value, $attributes)
    {
        if (!is_array($value)) {
            return base64_encode($value);
        }
        $value = array_map('base64_encode', $value);
        return json_encode($value);
    }
}