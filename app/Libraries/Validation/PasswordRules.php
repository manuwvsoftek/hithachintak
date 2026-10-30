<?php

declare(strict_types=1);

namespace App\Libraries\Validation;

/**
 * Custom CodeIgniter validation rule for the account password policy:
 * at least 8 characters, containing both a letter and a digit (special
 * characters are allowed but never required). Registered in
 * Config\Validation::$ruleSets so every form that sets or changes a
 * password can use it as `strong_password` alongside the built-in rules.
 */
class PasswordRules
{
    public function strong_password(?string $str, ?string &$error = null): bool
    {
        $str = $str ?? '';

        if (mb_strlen($str) < 8 || ! preg_match('/[A-Za-z]/', $str) || ! preg_match('/\d/', $str)) {
            $error = 'The {field} field must be at least 8 characters and include both letters and numbers.';

            return false;
        }

        return true;
    }
}
