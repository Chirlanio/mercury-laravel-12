<?php

namespace App\Http\Controllers;

use App\Models\HdSavedView;
use Illuminate\Http\Request;

class HelpdeskSavedViewController extends Controller
{
    public function index()
    {
        return response()->json(
            HdSavedView::where('user_id', auth()->id())
                ->orderBy('position')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'filters' => 'required|array',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_default'])) {
            HdSavedView::where('user_id', auth()->id())->update(['is_default' => false]);
        }

        $view = HdSavedView::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'filters' => $validated['filters'],
            'is_default' => $validated['is_default'] ?? false,
            'position' => HdSavedView::where('user_id', auth()->id())->max('position') + 1,
        ]);

        return response()->json($view, 201);
    }

    public function update(Request $request, HdSavedView $savedView)
    {
        abort_unless($savedView->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'filters' => 'sometimes|array',
            'is_default' => 'sometimes|boolean',
        ]);

        if (! empty($validated['is_default'])) {
            HdSavedView::where('user_id', auth()->id())
                ->where('id', '!=', $savedView->id)
                ->update(['is_default' => false]);
        }

        $savedView->update($validated);

        return response()->json($savedView);
    }

    public function destroy(HdSavedView $savedView)
    {
        abort_unless($savedView->user_id === auth()->id(), 403);

        $savedView->delete();

        return response()->json(['message' => 'Visão removida.']);
    }
}
