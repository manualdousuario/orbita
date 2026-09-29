<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\ViewErrorBag;

class FormField
{
    public static function aria(ViewErrorBag $errors, string $name, ?string $hintId = null): HtmlString
    {
        $invalid = $errors->has($name);

        $describedBy = array_filter([$invalid ? self::errorId($name) : null, $hintId]);

        $attributes = $invalid ? 'aria-invalid="true"' : '';
        if ($describedBy !== []) {
            $attributes .= ' aria-describedby="'.e(implode(' ', $describedBy)).'"';
        }

        return new HtmlString(trim($attributes));
    }

    public static function errorId(string $name): string
    {
        return $name.'-error';
    }
}
