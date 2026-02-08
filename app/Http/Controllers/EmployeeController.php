<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeeStatus;
use App\Models\EmployeeEvent;
use App\Models\EmployeeEventType;
use App\Models\EmployeeWorkSchedule;
use App\Models\EmploymentContract;
use App\Models\Gender;
use App\Models\Position;
use App\Models\Store;
use App\Exports\EmployeesExport;
use App\Rules\ValidImageRule;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        $storeFilter = $request->get('store');
        $statusFilter = $request->get('status');

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

        // Aplicar filtro de loja
        if ($storeFilter) {
            $query->where('store_id', $storeFilter);
        }

        // Aplicar filtro de status
        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status_id', $statusFilter);
        }

        // Aplicar ordenação
        $allowedSortFields = ['name', 'admission_date', 'level'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $employees = $query->paginate($perPage);

        // Buscar dados para o modal de cadastro e filtros
        $positions = Position::active()->orderBy('name')->get(['id', 'name', 'level']);
        $stores = Store::active()->orderBy('name')->get(['id', 'code', 'name']);
        $statuses = EmployeeStatus::orderBy('description_name')->get(['id', 'description_name as name']);

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
                    'is_active' => $employee->status_id == 2,
                    'status' => $employee->status_id == 2 ? 'Ativo' : ($employee->status_id == 3 ? 'Inativo' : ($employee->status_id == 1 ? 'Pendente' : ($employee->status_id == 4 ? 'Férias' : 'Licença'))),
                    'age' => $employee->birth_date ? $employee->age : null,
                    'years_of_service' => $employee->years_of_service,
                    'is_pcd' => $employee->is_pcd,
                    'is_apprentice' => $employee->is_apprentice,
                ];
            }),
            'positions' => $positions,
            'stores' => $stores,
            'statuses' => $statuses,
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'store' => $storeFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function listJson()
    {
        $employees = Employee::query()
            ->select(['id', 'name', 'short_name', 'status_id'])
            ->orderBy('name')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'name' => $emp->name,
                'short_name' => $emp->short_name,
                'is_active' => $emp->status_id == 2,
            ]);

        return response()->json(['employees' => $employees]);
    }

    public function store(Request $request)
    {
        // Limpar CPF removendo máscara
        $cleanCpf = $request->cpf ? preg_replace('/[^0-9]/', '', $request->cpf) : '';

        // Aplicar uppercase nos campos de texto
        $request->merge([
            'cpf' => $cleanCpf,
            'name' => $request->name ? strtoupper($request->name) : null,
            'short_name' => $request->short_name ? strtoupper($request->short_name) : null,
            'site_coupon' => $request->site_coupon ? strtoupper($request->site_coupon) : null,
        ]);

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
            'profile_image' => $request->hasFile('profile_image') ? ['nullable', ValidImageRule::avatar()] : 'nullable',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $data = $request->except(['profile_image', '_token']);

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

            // Criar contrato de admissão automaticamente
            EmploymentContract::create([
                'employee_id' => $employee->id,
                'position_id' => $employee->position_id,
                'movement_type_id' => 1, // ID 1 = Admissão
                'store_id' => $employee->store_id,
                'start_date' => $employee->admission_date,
                'end_date' => null,
                'is_active' => true,
            ]);

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
                'is_active' => $employee->status_id == 2,
                'status' => $employee->status_id == 2 ? 'Ativo' : ($employee->status_id == 3 ? 'Inativo' : ($employee->status_id == 1 ? 'Pendente' : ($employee->status_id == 4 ? 'Férias' : 'Licença'))),
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
                'status_id' => $employee->status_id ? (string)$employee->status_id : '',
                'is_pcd' => (bool)$employee->is_pcd,
                'is_apprentice' => (bool)$employee->is_apprentice,
                'avatar_url' => $employee->avatar_url,
                'profile_image' => $employee->profile_image, // Nome do arquivo da imagem
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        // Limpar CPF removendo máscara
        $cleanCpf = $request->cpf ? preg_replace('/[^0-9]/', '', $request->cpf) : '';

        // Aplicar uppercase nos campos de texto
        $request->merge([
            'cpf' => $cleanCpf,
            'name' => $request->name ? strtoupper($request->name) : null,
            'short_name' => $request->short_name ? strtoupper($request->short_name) : null,
            'site_coupon' => $request->site_coupon ? strtoupper($request->site_coupon) : null,
        ]);

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
            'profile_image' => $request->hasFile('profile_image') ? ['nullable', ValidImageRule::avatar()] : 'nullable',
        ]);

        if ($validator->fails()) {
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
            $data = $request->except(['_method', '_token', 'profile_image']);

            // Processar upload de imagem se existir
            if ($request->hasFile('profile_image')) {
                // Remover imagem antiga se existir
                if ($employee->profile_image) {
                    $oldImagePath = storage_path('app/public/employees/' . $employee->profile_image);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $image = $request->file('profile_image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $image->storeAs('employees', $imageName, 'public');
                $data['profile_image'] = $imageName;
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

            $employee->update($data);

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

        // Determinar qual é o último contrato (mais recente) e o mais antigo (admissão inicial)
        $latestContractId = $contracts->first()?->id;
        $oldestContractId = $contracts->last()?->id;

        $contracts = $contracts->map(function ($contract) use ($latestContractId, $oldestContractId) {
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
                'is_deletable' => $contract->id !== $oldestContractId,
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

            // Atualizar dados do funcionário com base no novo contrato (exceto demissão)
            if (!$isDismissal) {
                $updateData = [
                    'position_id' => $request->position_id,
                    'store_id' => $request->store_id,
                ];

                // Se for admissão, atualizar também a data de admissão
                if ($isAdmission) {
                    $updateData['admission_date'] = $request->start_date;
                }

                $employee->update($updateData);
            }

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

            // Verificar se é o contrato de admissão inicial (mais antigo)
            $oldestContract = EmploymentContract::where('employee_id', $employeeId)
                ->orderBy('id', 'asc')
                ->first();

            if ($contract->id === $oldestContract->id) {
                return response()->json([
                    'message' => 'O contrato de admissão inicial não pode ser excluído.'
                ], 422);
            }

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

            // Atualizar funcionário com dados do contrato mais recente restante
            $latestRemainingContract = EmploymentContract::where('employee_id', $employeeId)
                ->orderBy('start_date', 'desc')
                ->first();

            if ($latestRemainingContract) {
                $employee = Employee::find($employeeId);
                $employee->update([
                    'position_id' => $latestRemainingContract->position_id,
                    'store_id' => $latestRemainingContract->store_id,
                ]);
            }

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

            // Atualizar funcionário com dados do contrato reativado
            $employee = Employee::find($employeeId);
            $employee->update([
                'position_id' => $contract->position_id,
                'store_id' => $contract->store_id,
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

    /**
     * Get work schedule assignments for an employee
     */
    public function getWorkSchedule($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $assignments = EmployeeWorkSchedule::where('employee_id', $employeeId)
            ->with(['workSchedule.days', 'dayOverrides', 'createdBy:id,name'])
            ->orderBy('effective_date', 'desc')
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'schedule_name' => $assignment->workSchedule->name,
                    'schedule_id' => $assignment->work_schedule_id,
                    'weekly_hours' => $assignment->workSchedule->formatted_weekly_hours,
                    'effective_date' => $assignment->effective_date->format('d/m/Y'),
                    'end_date' => $assignment->end_date?->format('d/m/Y'),
                    'is_current' => $assignment->is_current,
                    'notes' => $assignment->notes,
                    'created_by' => $assignment->createdBy?->name ?? 'Sistema',
                    'days' => $assignment->workSchedule->days->map(function ($day) use ($assignment) {
                        $override = $assignment->dayOverrides->firstWhere('day_of_week', $day->day_of_week);
                        return [
                            'day_of_week' => $day->day_of_week,
                            'day_name' => $day->day_name,
                            'day_short_name' => $day->day_short_name,
                            'is_work_day' => $override ? $override->is_work_day : $day->is_work_day,
                            'entry_time' => $override ? $override->entry_time : $day->entry_time,
                            'exit_time' => $override ? $override->exit_time : $day->exit_time,
                            'has_override' => $override !== null,
                            'override_reason' => $override?->reason,
                        ];
                    }),
                    'overrides' => $assignment->dayOverrides->map(function ($override) {
                        return [
                            'id' => $override->id,
                            'day_of_week' => $override->day_of_week,
                            'is_work_day' => $override->is_work_day,
                            'entry_time' => $override->entry_time,
                            'exit_time' => $override->exit_time,
                            'reason' => $override->reason,
                        ];
                    }),
                ];
            });

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
            ],
            'assignments' => $assignments,
        ]);
    }

    /**
     * Export employees to Excel
     */
    public function export(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'store' => $request->get('store'),
            'status' => $request->get('status'),
            'sort' => $request->get('sort', 'name'),
            'direction' => $request->get('direction', 'asc'),
        ];

        $fileName = 'funcionarios_' . date('Y-m-d_His') . '.xlsx';

        return (new EmployeesExport($filters))->download($fileName);
    }

    /**
     * Listar eventos de um funcionário
     */
    public function getEvents($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $events = EmployeeEvent::where('employee_id', $employeeId)
            ->with(['eventType', 'creator'])
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($event) {
                $returnDate = $event->end_date
                    ? $event->end_date->copy()->addDay()->format('d/m/Y')
                    : ($event->start_date ? $event->start_date->copy()->addDay()->format('d/m/Y') : null);

                return [
                    'id' => $event->id,
                    'event_type' => $event->eventType->name,
                    'event_type_id' => $event->event_type_id,
                    'start_date' => $event->start_date?->format('d/m/Y'),
                    'end_date' => $event->end_date?->format('d/m/Y'),
                    'return_date' => $returnDate,
                    'period' => $event->period,
                    'duration_in_days' => $event->duration_in_days,
                    'document_url' => $event->document_url,
                    'has_document' => !is_null($event->document_path),
                    'notes' => $event->notes,
                    'created_by' => $event->creator ? $event->creator->name : 'Sistema',
                    'created_at' => $event->created_at?->format('d/m/Y H:i'),
                ];
            });

        $eventTypes = EmployeeEventType::active()->get();

        return response()->json([
            'events' => $events,
            'event_types' => $eventTypes,
        ]);
    }

    /**
     * Criar novo evento para um funcionário
     */
    public function storeEvent(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $eventType = EmployeeEventType::findOrFail($request->event_type_id);

        // Validação dinâmica baseada no tipo de evento
        $rules = [
            'event_type_id' => 'required|exists:employee_event_types,id',
            'notes' => 'nullable|string|max:1000',
        ];

        // Atestado Medico: requer data inicio + quantidade de dias
        $isAtestado = strtolower($eventType->name) === 'atestado médico';

        if ($isAtestado) {
            $rules['start_date'] = 'required|date';
            $rules['days'] = 'required|integer|min:1|max:365';
        } elseif ($eventType->requires_single_date) {
            $rules['start_date'] = 'required|date';
        }

        if (!$isAtestado && $eventType->requires_date_range) {
            $rules['start_date'] = 'required|date';
            $rules['end_date'] = 'required|date|after_or_equal:start_date';
        }

        if ($eventType->requires_document) {
            $rules['document'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'; // Max 10MB
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        // Calcular end_date para Falta baseado em days
        $endDate = $request->end_date;
        if ($isAtestado && $request->days) {
            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = $startDate->copy()->addDays((int) $request->days - 1)->toDateString();
        }

        $data = [
            'employee_id' => $employeeId,
            'event_type_id' => $request->event_type_id,
            'start_date' => $request->start_date,
            'end_date' => $endDate,
            'notes' => $request->notes,
            'created_by' => auth()->id(),
        ];

        // Upload do documento se fornecido
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $filename = 'employee_' . $employeeId . '_event_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('employee_events', $filename, 'public');
            $data['document_path'] = $path;
        }

        $event = EmployeeEvent::create($data);

        // Atualizar status do funcionário com base no tipo de evento
        $eventType = EmployeeEventType::find($request->event_type_id);
        if ($eventType) {
            $statusMap = [
                'Férias' => 4,
                'Licença' => 5,
                'Atestado Médico' => 5,
            ];

            if (isset($statusMap[$eventType->name])) {
                $employee = Employee::findOrFail($employeeId);
                $employee->update(['status_id' => $statusMap[$eventType->name]]);
            }
        }

        return response()->json([
            'message' => 'Evento criado com sucesso',
            'event' => $event->load('eventType', 'creator'),
        ], 201);
    }

    /**
     * Excluir evento de um funcionário
     */
    public function destroyEvent($employeeId, $eventId)
    {
        $employee = Employee::findOrFail($employeeId);
        $event = EmployeeEvent::where('employee_id', $employeeId)
            ->findOrFail($eventId);

        // Remover documento se existir
        if ($event->document_path) {
            Storage::disk('public')->delete($event->document_path);
        }

        $event->delete();

        // Recalcular status do funcionário após exclusão
        $statusMap = ['Férias' => 4, 'Licença' => 5, 'Atestado Médico' => 5];

        $activeEvent = EmployeeEvent::where('employee_id', $employeeId)
            ->whereHas('eventType', function ($q) use ($statusMap) {
                $q->whereIn('name', array_keys($statusMap));
            })
            ->where(function ($q) {
                $q->where('end_date', '>=', now()->toDateString())
                  ->orWhereNull('end_date');
            })
            ->with('eventType')
            ->orderBy('start_date', 'desc')
            ->first();

        $newStatusId = $activeEvent
            ? ($statusMap[$activeEvent->eventType->name] ?? 2)
            : 2;

        Employee::where('id', $employeeId)->update(['status_id' => $newStatusId]);

        return response()->json([
            'message' => 'Evento excluído com sucesso',
        ]);
    }

    public function exportEvents(Request $request, $employeeId)
    {
        $employee = Employee::with(['position', 'store'])->findOrFail($employeeId);

        $query = EmployeeEvent::where('employee_id', $employeeId)
            ->with(['eventType', 'creator']);

        // Filtrar por tipos de eventos
        if ($request->has('event_type_ids') && is_array($request->event_type_ids)) {
            $query->whereIn('event_type_id', $request->event_type_ids);
        }

        // Filtrar por período
        if ($request->has('start_date') && $request->start_date) {
            $query->where('start_date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->where(function ($q) use ($request) {
                $q->where('end_date', '<=', $request->end_date)
                  ->orWhere(function ($q2) use ($request) {
                      $q2->whereNull('end_date')
                         ->where('start_date', '<=', $request->end_date);
                  });
            });
        }

        $events = $query->orderBy('start_date', 'desc')->get();

        // Preparar dados para o PDF
        $data = [
            'employee' => [
                'name' => $employee->name,
                'cpf' => $employee->cpf,
                'position' => $employee->position?->name,
                'store' => $employee->store?->name,
                'admission_date' => $employee->admission_date?->format('d/m/Y'),
            ],
            'events' => $events->map(function ($event) {
                return [
                    'event_type' => $event->eventType->name,
                    'start_date' => $event->start_date?->format('d/m/Y'),
                    'end_date' => $event->end_date?->format('d/m/Y'),
                    'period' => $event->period,
                    'duration_in_days' => $event->duration_in_days,
                    'has_document' => !is_null($event->document_path),
                    'notes' => $event->notes,
                    'created_by' => $event->creator ? $event->creator->name : 'Sistema',
                    'created_at' => $event->created_at?->format('d/m/Y H:i'),
                ];
            }),
            'filters' => [
                'event_types' => $request->has('event_type_ids')
                    ? EmployeeEventType::whereIn('id', $request->event_type_ids)->pluck('name')->toArray()
                    : null,
                'start_date' => $request->start_date ? Carbon::parse($request->start_date)->format('d/m/Y') : null,
                'end_date' => $request->end_date ? Carbon::parse($request->end_date)->format('d/m/Y') : null,
            ],
            'generated_at' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = PDF::loadView('pdf.employee-events', $data);

        return $pdf->download('eventos_' . str_replace(' ', '_', strtolower($employee->name)) . '_' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportAllEvents(Request $request)
    {
        $query = EmployeeEvent::with(['employee.position', 'employee.store', 'eventType', 'creator']);

        // Filtrar por tipos de eventos
        if ($request->has('event_type_ids') && is_array($request->event_type_ids)) {
            $query->whereIn('event_type_id', $request->event_type_ids);
        }

        // Filtrar por lojas
        if ($request->has('store_ids') && is_array($request->store_ids)) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->whereIn('store_id', $request->store_ids);
            });
        }

        // Filtrar por período
        if ($request->has('start_date') && $request->start_date) {
            $query->where('start_date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->where(function ($q) use ($request) {
                $q->where('end_date', '<=', $request->end_date)
                  ->orWhere(function ($q2) use ($request) {
                      $q2->whereNull('end_date')
                         ->where('start_date', '<=', $request->end_date);
                  });
            });
        }

        $events = $query->orderBy('start_date', 'desc')->get();

        // Agrupar eventos por funcionário
        $eventsByEmployee = $events->groupBy('employee_id')->map(function ($employeeEvents) {
            $employee = $employeeEvents->first()->employee;
            return [
                'employee' => [
                    'name' => $employee->name,
                    'cpf' => $employee->cpf,
                    'position' => $employee->position?->name,
                    'store' => $employee->store?->name,
                ],
                'events' => $employeeEvents->map(function ($event) {
                    return [
                        'event_type' => $event->eventType->name,
                        'start_date' => $event->start_date?->format('d/m/Y'),
                        'end_date' => $event->end_date?->format('d/m/Y'),
                        'period' => $event->period,
                        'duration_in_days' => $event->duration_in_days,
                        'has_document' => !is_null($event->document_path),
                        'notes' => $event->notes,
                        'created_by' => $event->creator ? $event->creator->name : 'Sistema',
                        'created_at' => $event->created_at?->format('d/m/Y H:i'),
                    ];
                }),
                'totals' => [
                    'total_events' => $employeeEvents->count(),
                    'vacation_days' => $employeeEvents->where('eventType.name', 'Férias')->sum('duration_in_days'),
                    'leave_days' => $employeeEvents->where('eventType.name', 'Licença')->sum('duration_in_days'),
                    'absences' => $employeeEvents->where('eventType.name', 'Falta')->count(),
                ],
            ];
        })->values();

        // Preparar dados para o PDF
        $data = [
            'employees_data' => $eventsByEmployee,
            'filters' => [
                'event_types' => $request->has('event_type_ids')
                    ? EmployeeEventType::whereIn('id', $request->event_type_ids)->pluck('name')->toArray()
                    : null,
                'stores' => $request->has('store_ids')
                    ? Store::whereIn('id', $request->store_ids)->pluck('name')->toArray()
                    : null,
                'start_date' => $request->start_date ? Carbon::parse($request->start_date)->format('d/m/Y') : null,
                'end_date' => $request->end_date ? Carbon::parse($request->end_date)->format('d/m/Y') : null,
            ],
            'summary' => [
                'total_employees' => $eventsByEmployee->count(),
                'total_events' => $events->count(),
                'total_vacation_days' => $events->where('eventType.name', 'Férias')->sum('duration_in_days'),
                'total_leave_days' => $events->where('eventType.name', 'Licença')->sum('duration_in_days'),
                'total_absences' => $events->where('eventType.name', 'Falta')->count(),
            ],
            'generated_at' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = PDF::loadView('pdf.all-employee-events', $data);

        return $pdf->download('eventos_todos_funcionarios_' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Generate full employee report PDF
     */
    public function generateReport($id)
    {
        $employee = Employee::with(['educationLevel', 'position', 'store', 'employeeStatus'])
            ->findOrFail($id);

        $gender = $employee->gender_id ? Gender::find($employee->gender_id) : null;

        // Buscar contratos
        $contracts = EmploymentContract::where('employee_id', $id)
            ->with(['position', 'movementType', 'store'])
            ->orderBy('start_date', 'desc')
            ->get();

        $latestContractId = $contracts->first()?->id;

        $contractsData = $contracts->map(function ($contract) use ($latestContractId) {
            $isLatest = $contract->id === $latestContractId;

            return [
                'position' => $contract->position?->name ?? 'Não informado',
                'movement_type' => $contract->movementType?->name ?? 'Não informado',
                'store' => $contract->store?->name ?? $contract->store_id,
                'date_range' => $contract->date_range,
                'duration' => $contract->duration_text,
                'status_label' => $contract->is_active && $isLatest ? 'Atual' : (!$contract->is_active && $isLatest ? 'Último contrato' : 'Encerrado'),
            ];
        });

        // Buscar eventos
        $events = EmployeeEvent::where('employee_id', $id)
            ->with(['eventType', 'creator'])
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($event) {
                return [
                    'event_type' => $event->eventType->name,
                    'period' => $event->period,
                    'duration_in_days' => $event->duration_in_days,
                    'notes' => $event->notes,
                    'created_by' => $event->creator ? $event->creator->name : 'Sistema',
                    'created_at' => $event->created_at?->format('d/m/Y H:i'),
                ];
            });

        // Buscar historico de mudancas
        $histories = EmployeeHistory::where('employee_id', $id)
            ->with('createdBy:id,name')
            ->orderBy('event_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($history) {
                return [
                    'event_type_label' => $history->event_type_label,
                    'title' => $history->title,
                    'description' => $history->description,
                    'old_value' => $history->old_value,
                    'new_value' => $history->new_value,
                    'event_date' => $history->event_date->format('d/m/Y'),
                    'created_by' => $history->createdBy?->name ?? 'Sistema',
                ];
            });

        $data = [
            'employee' => [
                'name' => $employee->name,
                'cpf' => $employee->formatted_cpf,
                'birth_date' => $employee->birth_date?->format('d/m/Y'),
                'age' => $employee->age,
                'education_level' => $employee->educationLevel?->description_name ?? 'Não informado',
                'gender' => $gender?->description_name ?? 'Não informado',
                'is_pcd' => (bool) $employee->is_pcd,
                'is_apprentice' => (bool) $employee->is_apprentice,
                'position' => $employee->position?->name ?? 'Não informado',
                'level' => $employee->level ?? 'Não informado',
                'store' => $employee->store?->display_name ?? $employee->store_id ?? 'Não informado',
                'admission_date' => $employee->admission_date?->format('d/m/Y'),
                'dismissal_date' => $employee->dismissal_date?->format('d/m/Y'),
                'years_of_service' => $employee->years_of_service,
                'is_active' => $employee->is_active,
                'status' => $employee->employeeStatus?->description_name ?? ($employee->is_active ? 'Ativo' : 'Inativo'),
                'site_coupon' => $employee->site_coupon,
            ],
            'contracts' => $contractsData,
            'events' => $events,
            'histories' => $histories,
            'generated_at' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = PDF::loadView('pdf.employee-report', $data);

        return $pdf->download('relatorio_' . str_replace(' ', '_', strtolower($employee->name)) . '_' . now()->format('Y-m-d') . '.pdf');
    }
}
