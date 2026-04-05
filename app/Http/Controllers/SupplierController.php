<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query()->latest();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $suppliers = $query->paginate(20)->through(fn ($s) => [
            'id' => $s->id,
            'razao_social' => $s->razao_social,
            'nome_fantasia' => $s->nome_fantasia,
            'cnpj' => $s->cnpj,
            'cnpj_formatted' => $s->formatted_cnpj,
            'contact' => $s->contact,
            'contact_formatted' => $s->formatted_contact,
            'email' => $s->email,
            'is_active' => $s->is_active,
            'created_at' => $s->created_at->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'razao_social' => 'required|string|max:255',
            'nome_fantasia' => 'required|string|max:255',
            'cnpj' => 'required|string|max:20',
            'contact' => 'required|string|max:20',
            'email' => 'required|email|max:255',
        ]);

        // Clean masks — store only digits for cnpj and contact
        $validated['cnpj'] = preg_replace('/\D/', '', $validated['cnpj']);
        $validated['contact'] = preg_replace('/\D/', '', $validated['contact']);
        $validated['is_active'] = true;

        // Check CNPJ/CPF uniqueness
        if (Supplier::where('cnpj', $validated['cnpj'])->exists()) {
            return redirect()->back()->withErrors(['cnpj' => 'CNPJ/CPF já cadastrado.']);
        }

        Supplier::create($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'Fornecedor cadastrado com sucesso.');
    }

    public function show(Supplier $supplier)
    {
        return response()->json([
            'id' => $supplier->id,
            'razao_social' => $supplier->razao_social,
            'nome_fantasia' => $supplier->nome_fantasia,
            'cnpj' => $supplier->cnpj,
            'cnpj_formatted' => $supplier->formatted_cnpj,
            'contact' => $supplier->contact,
            'contact_formatted' => $supplier->formatted_contact,
            'email' => $supplier->email,
            'is_active' => $supplier->is_active,
            'created_at' => $supplier->created_at->format('d/m/Y H:i'),
            'updated_at' => $supplier->updated_at->format('d/m/Y H:i'),
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'razao_social' => 'required|string|max:255',
            'nome_fantasia' => 'required|string|max:255',
            'cnpj' => 'required|string|max:20',
            'contact' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['cnpj'] = preg_replace('/\D/', '', $validated['cnpj']);
        $validated['contact'] = preg_replace('/\D/', '', $validated['contact']);

        // Check CNPJ uniqueness excluding current record
        $exists = Supplier::where('cnpj', $validated['cnpj'])
            ->where('id', '!=', $supplier->id)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['cnpj' => 'CNPJ/CPF já cadastrado por outro fornecedor.']);
        }

        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'Fornecedor atualizado com sucesso.');
    }

    public function destroy(Supplier $supplier)
    {
        // Check dependencies before deleting
        if ($supplier->orderPayments()->exists()) {
            return redirect()->back()->with('error', 'Não é possível excluir: fornecedor possui ordens de pagamento vinculadas.');
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'Fornecedor excluído com sucesso.');
    }
}
