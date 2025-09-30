<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Store;
use App\Rules\ValidImageRule;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        $query = Employee::query()
            ->with(['educationLevel', 'position', 'store'])
            ->select([
                'id', 'name', 'short_name', 'profile_image',
                'admission_date', 'dismissal_date', 'position_id', 'store_id',
                'birth_date', 'level', 'status_id', 'is_pcd', 'is_apprentice'
            ]);

        // Aplicar busca se fornecida
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%");
            });
        }

        // Aplicar ordenação
        $allowedSortFields = ['name', 'admission_date', 'level'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $employees = $query->paginate($perPage);

        // Buscar dados para o modal de cadastro
        $positions = Position::active()->orderBy('name')->get(['id', 'name', 'level']);
        $stores = Store::active()->orderBy('name')->get(['id', 'code', 'name']);

        return Inertia::render('Employees/Index', [
            'employees' => $employees->through(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'short_name' => $employee->short_name,
                    'avatar_url' => $employee->avatar_url,
                    'admission_date' => $employee->admission_date?->format('d/m/Y'),
                    'position' => $employee->position?->name ?? 'Não informado',
                    'level' => $employee->level,
                    'is_active' => $employee->is_active,
                    'status' => $employee->is_active ? 'Ativo' : 'Inativo',
                    'age' => $employee->birth_date ? $employee->age : null,
                    'years_of_service' => $employee->years_of_service,
                    'is_pcd' => $employee->is_pcd,
                    'is_apprentice' => $employee->is_apprentice,
                ];
            }),
            'positions' => $positions,
            'stores' => $stores,
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request)
    {
        // Limpar CPF removendo máscara
        $cleanCpf = $request->cpf ? preg_replace('/[^0-9]/', '', $request->cpf) : '';

        // Substituir o CPF no request pelo CPF limpo
        $request->merge(['cpf' => $cleanCpf]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:40',
            'cpf' => 'required|string|size:11|unique:employees,cpf',
            'admission_date' => 'required|date',
            'birth_date' => 'nullable|date|before:today',
            'dismissal_date' => 'nullable|date|after:admission_date',
            'position_id' => 'required|exists:positions,id',
            'level' => 'nullable|in:Junior,Pleno,Senior',
            'store_id' => 'required|exists:stores,code',
            'site_coupon' => 'nullable|string|max:25',
            'education_level_id' => 'nullable|integer',
            'gender_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
            'is_pcd' => 'boolean',
            'is_apprentice' => 'boolean',
            'profile_image' => ['nullable', ValidImageRule::avatar()],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $data = $request->all();

            // Processar upload de imagem se existir
            if ($request->hasFile('profile_image')) {
                $image = $request->file('profile_image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $image->storeAs('employees', $imageName, 'public');
                $data['profile_image'] = $imageName;
            }

            // Definir valores padrão para campos obrigatórios
            $data['short_name'] = $data['short_name'] ?? $data['name'];
            $data['birth_date'] = $data['birth_date'] ?? '1990-01-01';
            $data['store_id'] = $data['store_id'] ?? 'Z999';
            $data['education_level_id'] = $data['education_level_id'] ?? 1;
            $data['gender_id'] = $data['gender_id'] ?? 1;
            $data['area_id'] = $data['area_id'] ?? 1;
            $data['level'] = $data['level'] ?? 'Junior';
            $data['status_id'] = $data['status_id'] ?? 2;

            // Remover campos vazios (mas manter os que têm valores padrão)
            $data = array_filter($data, function($value, $key) {
                if (in_array($key, ['short_name', 'birth_date', 'position_id', 'store_id', 'education_level_id', 'gender_id', 'area_id', 'level', 'status_id'])) {
                    return true; // Manter campos obrigatórios mesmo se vazios
                }
                return $value !== null && $value !== '';
            }, ARRAY_FILTER_USE_BOTH);

            $employee = Employee::create($data);

            return redirect()->route('employees.index')->with('success', 'Funcionário cadastrado com sucesso!');

        } catch (Exception $e) {
            return redirect()->back()->withErrors([
                'general' => 'Erro ao cadastrar funcionário: ' . $e->getMessage()
            ])->withInput();
        }
    }

    public function show($id)
    {
        $employee = Employee::with(['educationLevel', 'position', 'store'])
            ->findOrFail($id);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'short_name' => $employee->short_name,
                'avatar_url' => $employee->avatar_url,
                'cpf' => $employee->formatted_cpf,
                'admission_date' => $employee->admission_date?->format('d/m/Y'),
                'dismissal_date' => $employee->dismissal_date?->format('d/m/Y'),
                'birth_date' => $employee->birth_date?->format('d/m/Y'),
                'position' => $employee->position?->name ?? 'Não informado',
                'level' => $employee->level,
                'is_active' => $employee->is_active,
                'status' => $employee->is_active ? 'Ativo' : 'Inativo',
                'age' => $employee->birth_date ? $employee->age : null,
                'years_of_service' => $employee->years_of_service,
                'is_pcd' => $employee->is_pcd,
                'is_apprentice' => $employee->is_apprentice,
                'education_level' => $employee->educationLevel?->description_name ?? 'Não informado',
                'site_coupon' => $employee->site_coupon,
                'store' => $employee->store?->display_name ?? $employee->store_id ?? 'Não informado',
            ]
        ]);
    }

    public function edit($id)
    {
        $employee = Employee::with(['educationLevel', 'position', 'store'])
            ->findOrFail($id);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'short_name' => $employee->short_name,
                'cpf' => $employee->cpf, // CPF sem máscara para edição
                'admission_date' => $employee->admission_date?->format('Y-m-d'),
                'dismissal_date' => $employee->dismissal_date?->format('Y-m-d'),
                'birth_date' => $employee->birth_date?->format('Y-m-d'),
                'position_id' => $employee->position_id,
                'level' => $employee->level,
                'store_id' => $employee->store_id,
                'site_coupon' => $employee->site_coupon,
                'education_level_id' => $employee->education_level_id ? (string)$employee->education_level_id : '',
                'gender_id' => $employee->gender_id ? (string)$employee->gender_id : '',
                'area_id' => $employee->area_id ? (string)$employee->area_id : '',
                'is_pcd' => (bool)$employee->is_pcd,
                'is_apprentice' => (bool)$employee->is_apprentice,
                'avatar_url' => $employee->avatar_url,
                'profile_image' => $employee->profile_image, // Nome do arquivo da imagem
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        Log::info('Update employee request', [
            'id' => $id,
            'data' => $request->all(),
            'has_file' => $request->hasFile('profile_image'),
            'file_info' => $request->hasFile('profile_image') ? [
                'name' => $request->file('profile_image')->getClientOriginalName(),
                'size' => $request->file('profile_image')->getSize(),
                'mime' => $request->file('profile_image')->getMimeType(),
            ] : null
        ]);

        $employee = Employee::findOrFail($id);

        // Limpar CPF removendo máscara
        $cleanCpf = $request->cpf ? preg_replace('/[^0-9]/', '', $request->cpf) : '';

        // Substituir o CPF no request pelo CPF limpo
        $request->merge(['cpf' => $cleanCpf]);

        Log::info('Cleaned CPF', ['original_cpf' => $request->cpf, 'cleaned' => $cleanCpf, 'length' => strlen($cleanCpf)]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:40',
            'cpf' => 'required|string|size:11',
            'admission_date' => 'required|date',
            'birth_date' => 'nullable|date|before:today',
            'dismissal_date' => 'nullable|date|after:admission_date',
            'position_id' => 'required|exists:positions,id',
            'level' => 'nullable|in:Junior,Pleno,Senior',
            'store_id' => 'required|exists:stores,code',
            'site_coupon' => 'nullable|string|max:25',
            'education_level_id' => 'nullable|integer',
            'gender_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
            'is_pcd' => 'boolean',
            'is_apprentice' => 'boolean',
            'profile_image' => ['nullable', ValidImageRule::avatar()],
        ]);

        if ($validator->fails()) {
            Log::error('Update validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all(),
                'cpf_length' => strlen($request->cpf ?? ''),
                'cpf_value' => $request->cpf
            ]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Verificar unicidade do CPF (agora já está limpo)
        $existingEmployee = Employee::where('cpf', $cleanCpf)
            ->where('id', '!=', $employee->id)
            ->first();

        if ($existingEmployee) {
            return redirect()->back()->withErrors([
                'cpf' => 'Este CPF já está cadastrado para outro funcionário.'
            ])->withInput();
        }

        try {
            $data = $request->except(['_method', '_token']);

            // Processar upload de imagem se existir
            if ($request->hasFile('profile_image')) {
                Log::info('Processing profile image upload');

                // Remover imagem antiga se existir
                if ($employee->profile_image) {
                    $oldImagePath = storage_path('app/public/employees/' . $employee->profile_image);
                    Log::info('Checking old image', ['path' => $oldImagePath, 'exists' => file_exists($oldImagePath)]);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                        Log::info('Old image deleted');
                    }
                }

                $image = $request->file('profile_image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();

                Log::info('Attempting to save image', [
                    'original_name' => $image->getClientOriginalName(),
                    'temp_path' => $image->getRealPath(),
                    'new_name' => $imageName,
                    'target_dir' => storage_path('app/public/employees')
                ]);

                // Usar o disk 'public' explicitamente
                $storedPath = $image->storeAs('employees', $imageName, 'public');

                if ($storedPath === false) {
                    Log::error('Failed to store image');
                    throw new \Exception('Falha ao salvar imagem');
                }

                $data['profile_image'] = $imageName;

                Log::info('New image saved', [
                    'name' => $imageName,
                    'stored_path' => $storedPath,
                    'full_path' => storage_path('app/public/employees/' . $imageName),
                    'file_exists' => file_exists(storage_path('app/public/employees/' . $imageName))
                ]);
            }

            // Definir valores padrão para campos obrigatórios se estiverem vazios
            if (empty($data['short_name'])) {
                $data['short_name'] = $data['name'];
            }
            if (empty($data['birth_date'])) {
                $data['birth_date'] = '1990-01-01';
            }
            if (empty($data['education_level_id'])) {
                $data['education_level_id'] = 1;
            }
            if (empty($data['gender_id'])) {
                $data['gender_id'] = 1;
            }
            if (empty($data['area_id'])) {
                $data['area_id'] = 1;
            }
            if (empty($data['level'])) {
                $data['level'] = 'Junior';
            }
            if (empty($data['status_id'])) {
                $data['status_id'] = 2;
            }

            // Converter booleanos
            $data['is_pcd'] = isset($data['is_pcd']) && ($data['is_pcd'] === '1' || $data['is_pcd'] === true);
            $data['is_apprentice'] = isset($data['is_apprentice']) && ($data['is_apprentice'] === '1' || $data['is_apprentice'] === true);

            Log::info('Updating employee with data', ['data' => $data]);

            $employee->update($data);

            Log::info('Employee updated successfully', ['id' => $employee->id]);

            return redirect()->route('employees.index')->with('success', 'Funcionário atualizado com sucesso!');

        } catch (Exception $e) {
            return redirect()->back()->withErrors([
                'general' => 'Erro ao atualizar funcionário: ' . $e->getMessage()
            ])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $employee = Employee::findOrFail($id);

            Log::info('Deleting employee', [
                'id' => $employee->id,
                'name' => $employee->name
            ]);

            // Deletar imagem do perfil se existir
            if ($employee->profile_image) {
                $imagePath = storage_path('app/public/employees/' . $employee->profile_image);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                    Log::info('Profile image deleted', ['path' => $imagePath]);
                }
            }

            $employee->delete();

            Log::info('Employee deleted successfully', ['id' => $id]);

            return redirect()->route('employees.index')->with('success', 'Funcionário excluído com sucesso!');

        } catch (\Exception $e) {
            Log::error('Error deleting employee', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'general' => 'Erro ao excluir funcionário: ' . $e->getMessage()
            ]);
        }
    }
}
