<?php

namespace App\Rules;

class PasswordPolicy
{
    /**
     * Unified password policy rules for Victorious POS.
     * Enforces:
     * - Minimum 8 characters
     * - At least one uppercase letter ([A-Z])
     * - At least one number ([0-9])
     *
     * @param bool $required
     * @return array
     */
    public static function rules(bool $required = true): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return array_merge($rules, [
            'string',
            'min:8',
            'regex:/[A-Z]/', // Requires at least one uppercase letter
            'regex:/[0-9]/', // Requires at least one numeric digit
        ]);
    }

    /**
     * Human-readable error messages for password policy violations.
     *
     * @return array
     */
    public static function messages(): array
    {
        return [
            'password.min' => 'Password must be at least 8 characters in length.',
            'password.regex' => 'Password must contain at least one uppercase letter and at least one number.',
            'new_password.min' => 'Password must be at least 8 characters in length.',
            'new_password.regex' => 'Password must contain at least one uppercase letter and at least one number.',
            'admin_password.min' => 'Password must be at least 8 characters in length.',
            'admin_password.regex' => 'Password must contain at least one uppercase letter and at least one number.',
        ];
    }
}
