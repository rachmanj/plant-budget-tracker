<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'employee_no',
        'division',
        'project_code_scope',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->username === '') {
                $user->username = null;
            }

            if ($user->username === null) {
                return;
            }

            $user->username = strtolower($user->username);

            $validator = Validator::make(
                ['username' => $user->username],
                [
                    'username' => [
                        'required',
                        'string',
                        'min:3',
                        'max:32',
                        'regex:/^[a-z0-9._-]+$/',
                        Rule::unique('users', 'username')->ignore($user->id),
                        function (string $attribute, mixed $value, \Closure $fail): void {
                            if (str_contains((string) $value, '@') || filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $fail('The username may not be an email address.');
                            }
                        },
                    ],
                ]
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
            }
        });
    }
}
