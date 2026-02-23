<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $user = request()->user();
        $query = User::query()->with(['roles', 'company']);

        if ($user?->hasRole('company_admin') && ! $user->hasRole('group_admin')) {
            $query->where('company_id', $user->company_id);
        }

        $users = $query->orderBy('name')->get()->map(function (User $u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'surname' => $u->surname,
                'email' => $u->email,
                'company' => $u->company ? [
                    'id' => $u->company->id,
                    'name' => $u->company->name,
                ] : null,
                'roles' => $u->roles->pluck('name'),
            ];
        });

        return response()->json($users);
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $roles = Role::orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
        $companies = Company::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'roles' => $roles,
            'companies' => $companies,
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'email' => $data['email'],
            'company_id' => $data['company_id'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles'] ?? []);

        return response()->json($this->transform($user->fresh(['roles', 'company'])));
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json($this->transform($user->load(['roles', 'company'])));
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $payload = [
            'name' => $data['name'],
            'surname' => $data['surname'],
            'email' => $data['email'],
        ];

        // company change allowed only for group admins; company admins are scoped in request validation
        if (array_key_exists('company_id', $data)) {
            $payload['company_id'] = $data['company_id'];
        }

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $user->syncRoles($data['roles'] ?? []);

        return response()->json($this->transform($user->fresh(['roles', 'company'])));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        $user->delete();

        return response()->json(['ok' => true]);
    }

    protected function transform(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'surname' => $u->surname,
            'email' => $u->email,
            'company' => $u->company ? [
                'id' => $u->company->id,
                'name' => $u->company->name,
            ] : null,
            'roles' => $u->roles->pluck('name')->values(),
        ];
    }
}
