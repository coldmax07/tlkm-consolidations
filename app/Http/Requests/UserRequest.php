<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }
        if ($this->route('user')) {
            return $user->can('update', $this->route('user'));
        }

        return $user->can('create', User::class);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $user = $this->user();
        $isGroupAdmin = $user?->hasRole('group_admin');

        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'company_id' => [
                $isGroupAdmin ? 'required' : 'sometimes',
                'integer',
                'exists:companies,id',
                $isGroupAdmin ? null : Rule::in([$user?->company_id]),
            ],
            'password' => [$userId ? 'nullable' : 'required', 'confirmed', 'min:6'],
            'password_confirmation' => [$userId ? 'nullable' : 'required_with:password'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
