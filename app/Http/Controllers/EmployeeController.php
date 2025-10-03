<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmploymentContract;
use App\Models\Position;
use App\Models\Store;
use App\Rules\ValidImageRule;
use Carbon\Carbon;
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

    /**
     * Get employee history
     */
    public function history($id)
    {
        $employee = Employee::findOrFail($id);

        // Buscar históricos
        $histories = EmployeeHistory::where('employee_id', $id)
            ->with('createdBy:id,name')
            ->orderBy('event_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'event_type' => $history->event_type,
                    'event_type_label' => $history->event_type_label,
                    'title' => $history->title,
                    'description' => $history->description,
                    'old_value' => $history->old_value,
                    'new_value' => $history->new_value,
                    'event_date' => $history->event_date->format('d/m/Y'),
                    'created_by' => $history->createdBy?->name ?? 'Sistema',
                    'created_at' => $history->created_at->format('d/m/Y H:i'),
                ];
            });

        // Buscar contratos de trabalho
        $contracts = EmploymentContract::where('employee_id', $id)
            ->with(['position', 'movementType', 'store'])
            ->orderBy('start_date', 'desc')
            ->get();

        // Determinar qual é o último contrato (mais recente)
        $latestContractId = $contracts->first()?->id;

        $contracts = $contracts->map(function ($contract) use ($latestContractId) {
            $isLatest = $contract->id === $latestContractId;

            return [
                'id' => $contract->id,
                'position' => $contract->position?->name ?? 'Não informado',
                'movement_type' => $contract->movementType?->name ?? 'Não informado',
                'store' => $contract->store?->name ?? $contract->store_id,
                'start_date' => $contract->start_date->format('d/m/Y'),
                'end_date' => $contract->is_active ? 'Atual' : ($contract->end_date ? $contract->end_date->format('d/m/Y') : '-'),
                'end_date_formatted' => $contract->end_date ? $contract->end_date->format('d/m/Y') : null,
                'is_active' => $contract->is_active,
                'is_latest' => $isLatest,
                'status_label' => $contract->is_active && $isLatest ? 'Atual' : (!$contract->is_active && $isLatest ? 'Último contrato' : 'Encerrado'),
                'duration' => $contract->duration_text,
                'date_range' => $contract->date_range,
                'created_at' => $contract->created_at->format('d/m/Y H:i'),
            ];
        });

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
            ],
            'histories' => $histories,
            'contracts' => $contracts,
        ]);
    }

    /**
     * Store a new employment contract
     */
    public function storeContract(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'position_id' => 'required|exists:positions,id',
            'movement_type_id' => 'required|exists:type_moviments,id',
            'store_id' => 'required|exists:stores,code',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ], [
            'position_id.required' => 'O cargo é obrigatório.',
            'position_id.exists' => 'Cargo inválido.',
            'movement_type_id.required' => 'O tipo de movimentação é obrigatório.',
            'movement_type_id.exists' => 'Tipo de movimentação inválido.',
            'store_id.required' => 'A loja é obrigatória.',
            'store_id.exists' => 'Loja inválida.',
            'start_date.required' => 'A data de início é obrigatória.',
            'start_date.date' => 'Data de início inválida.',
            'end_date.date' => 'Data de término inválida.',
            'end_date.after' => 'A data de término deve ser posterior à data de início.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verificar o tipo de movimentação
            $isDismissal = $request->movement_type_id == 5; // ID 5 = Demissão
            $isAdmission = $request->movement_type_id == 1; // ID 1 = Admissão

            // Verificar se existe contrato anterior ativo
            $previousContract = EmploymentContract::where('employee_id', $id)
                ->where('is_active', true)
                ->first();

            // Se existir contrato anterior ativo, inativar e adicionar data de término
            if ($previousContract) {
                // Data de término = último dia antes do início do novo contrato
                $endDate = Carbon::parse($request->start_date)->subDay();

                $previousContract->update([
                    'is_active' => false,
                    'end_date' => $endDate
                ]);
            }

            // Criar o contrato
            $contract = EmploymentContract::create([
                'employee_id' => $id,
                'position_id' => $request->position_id,
                'movement_type_id' => $request->movement_type_id,
                'store_id' => $request->store_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date ?? ($isDismissal ? $request->start_date : null),
                'is_active' => !$isDismissal, // Se for demissão, já cria como inativo
            ]);

            // Se for demissão, inativar o funcionário e registrar data de demissão
            if ($isDismissal) {
                $employee->update([
                    'dismissal_date' => $request->start_date,
                    'status_id' => 3, // Status inativo
                ]);
            }

            // Se for admissão e funcionário está inativo, reativá-lo
            if ($isAdmission && $employee->status_id == 3) {
                $employee->update([
                    'status_id' => 2, // Status ativo
                    'dismissal_date' => null, // Limpar data de demissão
                ]);
            }

            // Verificar se é o último contrato
            $latestContract = EmploymentContract::where('employee_id', $id)
                ->orderBy('start_date', 'desc')
                ->first();
            $isLatest = $contract->id === $latestContract->id;

            return response()->json([
                'message' => 'Contrato criado com sucesso!',
                'contract' => [
                    'id' => $contract->id,
                    'position' => $contract->position?->name ?? 'Não informado',
                    'movement_type' => $contract->movementType?->name ?? 'Não informado',
                    'store' => $contract->store?->name ?? $contract->store_id,
                    'start_date' => $contract->start_date->format('d/m/Y'),
                    'end_date' => $contract->is_active ? 'Atual' : ($contract->end_date ? $contract->end_date->format('d/m/Y') : '-'),
                    'is_active' => $contract->is_active,
                    'is_latest' => $isLatest,
                    'status_label' => $contract->is_active && $isLatest ? 'Atual' : (!$contract->is_active && $isLatest ? 'Último contrato' : 'Encerrado'),
                    'duration' => $contract->duration_text,
                    'date_range' => $contract->date_range,
                    'created_at' => $contract->created_at->format('d/m/Y H:i'),
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Error creating employment contract', [
                'employee_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erro ao criar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an employment contract
     */
    public function updateContract(Request $request, $employeeId, $contractId)
    {
        $employee = Employee::findOrFail($employeeId);
        $contract = EmploymentContract::where('id', $contractId)
            ->where('employee_id', $employeeId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'position_id' => 'required|exists:positions,id',
            'movement_type_id' => 'required|exists:type_moviments,id',
            'store_id' => 'required|exists:stores,code',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ], [
            'position_id.required' => 'O cargo é obrigatório.',
            'position_id.exists' => 'Cargo inválido.',
            'movement_type_id.required' => 'O tipo de movimentação é obrigatório.',
            'movement_type_id.exists' => 'Tipo de movimentação inválido.',
            'store_id.required' => 'A loja é obrigatória.',
            'store_id.exists' => 'Loja inválida.',
            'start_date.required' => 'A data de início é obrigatória.',
            'start_date.date' => 'Data de início inválida.',
            'end_date.date' => 'Data de término inválida.',
            'end_date.after' => 'A data de término deve ser posterior à data de início.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contract->update([
                'position_id' => $request->position_id,
                'movement_type_id' => $request->movement_type_id,
                'store_id' => $request->store_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            // Reload relationships
            $contract->load(['position', 'movementType', 'store']);

            // Verificar se é o último contrato
            $latestContract = EmploymentContract::where('employee_id', $employeeId)
                ->orderBy('start_date', 'desc')
                ->first();
            $isLatest = $contract->id === $latestContract->id;

            return response()->json([
                'message' => 'Contrato atualizado com sucesso!',
                'contract' => [
                    'id' => $contract->id,
                    'position' => $contract->position?->name ?? 'Não informado',
                    'movement_type' => $contract->movementType?->name ?? 'Não informado',
                    'store' => $contract->store?->name ?? $contract->store_id,
                    'start_date' => $contract->start_date->format('d/m/Y'),
                    'end_date' => $contract->is_active ? 'Atual' : ($contract->end_date ? $contract->end_date->format('d/m/Y') : '-'),
                    'is_active' => $contract->is_active,
                    'is_latest' => $isLatest,
                    'status_label' => $contract->is_active && $isLatest ? 'Atual' : (!$contract->is_active && $isLatest ? 'Último contrato' : 'Encerrado'),
                    'duration' => $contract->duration_text,
                    'date_range' => $contract->date_range,
                    'created_at' => $contract->created_at->format('d/m/Y H:i'),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error updating employment contract', [
                'contract_id' => $contractId,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erro ao atualizar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an employment contract
     */
    public function destroyContract(Request $request, $employeeId, $contractId)
    {
        try {
            $contract = EmploymentContract::where('id', $contractId)
                ->where('employee_id', $employeeId)
                ->firstOrFail();

            Log::info('Deleting employment contract', [
                'contract_id' => $contractId,
                'employee_id' => $employeeId
            ]);

            // Verificar se é o último contrato ativo
            $isLastActiveContract = EmploymentContract::where('employee_id', $employeeId)
                ->where('is_active', true)
                ->count() === 1 && $contract->is_active;

            // Buscar contrato anterior (se existir)
            $previousContract = null;
            if ($isLastActiveContract) {
                $previousContract = EmploymentContract::where('employee_id', $employeeId)
                    ->where('id', '!=', $contractId)
                    ->orderBy('start_date', 'desc')
                    ->first();
            }

            $contract->delete();

            $response = [
                'message' => 'Contrato excluído com sucesso!',
                'isLastActiveContract' => $isLastActiveContract,
                'previousContract' => null
            ];

            // Se havia contrato anterior, retornar seus dados para possível reativação
            if ($previousContract) {
                $response['previousContract'] = [
                    'id' => $previousContract->id,
                    'position' => $previousContract->position?->name ?? 'Não informado',
                    'store' => $previousContract->store?->name ?? $previousContract->store_id,
                    'start_date' => $previousContract->start_date->format('d/m/Y'),
                ];
            }

            return response()->json($response, 200);

        } catch (Exception $e) {
            Log::error('Error deleting employment contract', [
                'contract_id' => $contractId,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erro ao excluir contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivate a contract
     */
    public function reactivateContract($employeeId, $contractId)
    {
        try {
            $contract = EmploymentContract::where('id', $contractId)
                ->where('employee_id', $employeeId)
                ->firstOrFail();

            $contract->update([
                'is_active' => true,
                'end_date' => null, // Remover data de término ao reativar
            ]);

            Log::info('Reactivated employment contract', [
                'contract_id' => $contractId,
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'message' => 'Contrato reativado com sucesso!',
                'contract' => [
                    'id' => $contract->id,
                    'is_active' => $contract->is_active,
                    'end_date' => null,
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error reactivating employment contract', [
                'contract_id' => $contractId,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erro ao reativar contrato: ' . $e->getMessage()
            ], 500);
        }
    }
}
