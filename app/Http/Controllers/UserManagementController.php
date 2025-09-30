<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\Permission;
use App\Models\User;
use App\Models\Store;
use App\Models\ActivityLog;
use App\Rules\ValidImageRule;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserManagementController extends Controller
{

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        // Validar campos de ordenação permitidos
        $allowedSortFields = ['name', 'email', 'role', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'name';
        }

        // Validar direção da ordenação
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = User::query();

        // Aplicar busca se fornecida
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Aplicar ordenação
        $query->orderBy($sortField, $sortDirection);

        $users = $query->paginate($perPage);

        // Buscar lojas ativas
        $stores = Store::active()->orderBy('name')->get(['code', 'name'])->values();

        return Inertia::render('UserManagement/Index', [
            'users' => $users->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role->value,
                    'created_at' => $user->created_at,
                    'email_verified_at' => $user->email_verified_at,
                    'avatar_url' => $user->avatar_url,
                    'has_custom_avatar' => $user->hasCustomAvatar(),
                    'store_id' => $user->store_id,
                    'status_id' => $user->status_id,
                ];
            }),
            'roles' => Role::options(),
            'stores' => $stores,
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('UserManagement/Create', [
            'roles' => Role::options(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:220',
            'email' => 'required|string|email|max:255|unique:users',
            'username' => 'nullable|string|max:220|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|string|in:super_admin,admin,support,user',
            'avatar' => ['nullable', ValidImageRule::avatar()],
            'store_id' => 'required|string|exists:stores,code',
            'status_id' => 'nullable|integer',
        ]);

        $userData = [
            'name' => $request->name,
            'nickname' => $request->nickname,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'email_verified_at' => now(), // Auto-verificar email para usuários criados pelo admin
            'store_id' => $request->store_id,
            'status_id' => $request->status_id ?? 1,
        ];

        // Upload do avatar se fornecido
        if ($request->hasFile('avatar')) {
            try {
                $image = $request->file('avatar');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $image->storeAs('avatars', $imageName, 'public');
                $userData['avatar'] = $imageName;
            } catch (Exception $e) {
                return back()->withErrors(['avatar' => 'Erro no upload da imagem: ' . $e->getMessage()]);
            }
        }

        $user = User::create($userData);

        // Log da criação do usuário
        ActivityLog::log(
            'create',
            "Criou um novo usuário: {$user->name} ({$user->email}) com nível {$user->role->label()}",
            $user
        );

        Log::info('Redirecting after user creation...');

        return redirect()->route('users.index');
    }

    public function show(User $user)
    {
        return Inertia::render('UserManagement/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'avatar_url' => $user->avatar_url,
                'has_custom_avatar' => $user->hasCustomAvatar(),
            ],
            'roles' => Role::options(),
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('UserManagement/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'avatar_url' => $user->avatar_url,
                'has_custom_avatar' => $user->hasCustomAvatar(),
            ],
            'roles' => Role::options(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();

        // Verificar se pode editar este usuário
        if (!$currentUser->canEditUser($user)) {
            abort(403, 'Você não tem permissão para editar este usuário.');
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'nickname' => 'nullable|string|max:220',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'username' => 'nullable|string|max:220|unique:users,username,' . $user->id,
            'role' => 'sometimes|required|string|in:super_admin,admin,support,user',
            'avatar' => ['nullable', ValidImageRule::avatar()],
            'remove_avatar' => 'boolean',
            'store_id' => 'required|string|exists:stores,code',
            'status_id' => 'nullable|integer',
        ]);

        $updateData = [];

        // Atualizar dados principais se presentes
        if (isset($validatedData['name'])) {
            $updateData['name'] = $validatedData['name'];
        }
        if (isset($validatedData['nickname'])) {
            $updateData['nickname'] = $validatedData['nickname'];
        }
        if (isset($validatedData['email'])) {
            $updateData['email'] = $validatedData['email'];
        }
        if (isset($validatedData['username'])) {
            $updateData['username'] = $validatedData['username'];
        }
        if (isset($validatedData['store_id'])) {
            $updateData['store_id'] = $validatedData['store_id'];
        }
        if (isset($validatedData['status_id'])) {
            $updateData['status_id'] = $validatedData['status_id'];
        }

        // Verificar e atualizar role se presente e permitido
        if (isset($validatedData['role'])) {
            $newRole = Role::from($validatedData['role']);

            if (!$currentUser->canManageRole($newRole) || !$currentUser->canManageRole($user->role)) {
                return back()->withErrors([
                    'role' => 'Você não pode alterar o nível de acesso deste usuário.'
                ]);
            }
            $updateData['role'] = $newRole;
        }

        // Remover avatar se solicitado
        if ($request->boolean('remove_avatar') && $user->avatar) {
            try {
                $imagePath = storage_path('app/public/avatars/' . $user->avatar);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                $updateData['avatar'] = null;
            } catch (Exception $e) {
                return back()->withErrors(['avatar' => 'Erro ao remover a imagem: ' . $e->getMessage()]);
            }
        }

        // Upload do avatar se fornecido
        if ($request->hasFile('avatar')) {
            try {
                // Remover imagem antiga se existir
                if ($user->avatar) {
                    $oldImagePath = storage_path('app/public/avatars/' . $user->avatar);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $image = $request->file('avatar');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $image->storeAs('avatars', $imageName, 'public');
                $updateData['avatar'] = $imageName;
            } catch (Exception $e) {
                return back()->withErrors(['avatar' => 'Erro no upload da imagem: ' . $e->getMessage()]);
            }
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return redirect()->route('users.index')->with('success', 'Usuário atualizado com sucesso!');
    }



    public function destroy(User $user)
    {
        $currentUser = auth()->user();

        // Verificar se pode deletar este usuário
        if (!$currentUser->canEditUser($user) || $user->id === $currentUser->id) {
            return back()->withErrors([
                'delete' => 'Você não pode deletar este usuário.'
            ]);
        }

        // Remove avatar antes de deletar o usuário
        if ($user->avatar) {
            $imagePath = storage_path('app/public/avatars/' . $user->avatar);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuário deletado com sucesso!');
    }

    public function updateRole(Request $request, User $user)
    {
        $currentUser = auth()->user();

        $request->validate([
            'role' => 'required|string|in:super_admin,admin,support,user',
        ]);

        $newRole = Role::from($request->role);

        // Verificar se pode alterar o role
        if (!$currentUser->canManageRole($newRole) || !$currentUser->canManageRole($user->role)) {
            return back()->withErrors([
                'role' => 'Você não pode alterar o nível de acesso deste usuário.'
            ]);
        }

        $user->update(['role' => $request->role]);

        return back()->with('success', 'Nível de acesso atualizado com sucesso!');
    }

    /**
     * Remove o avatar do usuário
     */
    public function removeAvatar(Request $request, User $user)
    {
        $currentUser = auth()->user();

        // Verificar se pode editar este usuário
        if (!$currentUser->canEditUser($user)) {
            abort(403, 'Você não tem permissão para editar este usuário.');
        }

        if ($user->avatar) {
            $imagePath = storage_path('app/public/avatars/' . $user->avatar);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            $user->update(['avatar' => null]);

            // Log da remoção do avatar
            ActivityLog::log(
                'update',
                "Removeu avatar do usuário: {$user->name}",
                $user
            );
        }

        return back()->with('success', 'Avatar removido com sucesso!');
    }
}