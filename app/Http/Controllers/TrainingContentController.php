<?php

namespace App\Http\Controllers;

use App\Models\TrainingContent;
use App\Models\TrainingContentCategory;
use App\Services\TrainingContentUploadService;
use Illuminate\Http\Request;

class TrainingContentController extends Controller
{
    public function __construct(
        private TrainingContentUploadService $uploadService,
    ) {}

    // ==========================================
    // CRUD
    // ==========================================

    public function index(Request $request)
    {
        $query = TrainingContent::with(['category', 'createdBy'])
            ->active()
            ->latest();

        if ($request->filled('content_type')) {
            $query->forType($request->content_type);
        }
        if ($request->filled('category_id')) {
            $query->forCategory($request->category_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $contents = $query->paginate(15)->through(fn ($content) => $this->formatContent($content));

        $typeCounts = [];
        foreach (array_keys(TrainingContent::TYPE_LABELS) as $type) {
            $typeCounts[$type] = TrainingContent::active()->forType($type)->count();
        }

        return response()->json([
            'contents' => $contents,
            'filters' => $request->only(['search', 'content_type', 'category_id']),
            'typeOptions' => TrainingContent::TYPE_LABELS,
            'typeCounts' => $typeCounts,
            'categories' => TrainingContentCategory::active()->orderBy('name')->get(['id', 'name', 'icon', 'color']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'required|string|in:video,audio,document,link,text',
            'file' => 'nullable|file',
            'external_url' => 'nullable|url|max:500',
            'text_content' => 'nullable|string',
            'duration_seconds' => 'nullable|integer|min:0',
            'thumbnail' => 'nullable|image|max:5120',
            'category_id' => 'nullable|exists:training_content_categories,id',
        ]);

        $data = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content_type' => $validated['content_type'],
            'external_url' => $validated['external_url'] ?? null,
            'text_content' => $validated['text_content'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'created_by_user_id' => auth()->id(),
        ];

        // Handle file upload
        if ($request->hasFile('file') && $this->uploadService->isFileType($validated['content_type'])) {
            try {
                $fileData = $this->uploadService->upload($request->file('file'), $validated['content_type']);
                $data['file_path'] = $fileData['path'];
                $data['file_name'] = $fileData['name'];
                $data['file_size'] = $fileData['size'];
                $data['file_mime_type'] = $fileData['mime'];
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['file' => $e->getMessage()]);
            }
        }

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $this->uploadService->uploadThumbnail($request->file('thumbnail'));
        }

        $content = TrainingContent::create($data);

        return response()->json([
            'content' => $this->formatContent($content->load('category')),
            'message' => 'Conteúdo criado com sucesso.',
        ]);
    }

    public function show(TrainingContent $trainingContent)
    {
        $trainingContent->load(['category', 'createdBy', 'updatedBy']);

        return response()->json([
            'content' => $this->formatContentDetail($trainingContent),
        ]);
    }

    public function update(Request $request, TrainingContent $trainingContent)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'external_url' => 'nullable|url|max:500',
            'text_content' => 'nullable|string',
            'duration_seconds' => 'nullable|integer|min:0',
            'thumbnail' => 'nullable|image|max:5120',
            'category_id' => 'nullable|exists:training_content_categories,id',
            'is_active' => 'boolean',
        ]);

        $data = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'external_url' => $validated['external_url'] ?? null,
            'text_content' => $validated['text_content'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'is_active' => $validated['is_active'] ?? $trainingContent->is_active,
            'updated_by_user_id' => auth()->id(),
        ];

        // Handle file replacement
        if ($request->hasFile('file') && $trainingContent->is_file_type) {
            try {
                // Delete old file
                $this->uploadService->delete($trainingContent->file_path);

                $fileData = $this->uploadService->upload($request->file('file'), $trainingContent->content_type);
                $data['file_path'] = $fileData['path'];
                $data['file_name'] = $fileData['name'];
                $data['file_size'] = $fileData['size'];
                $data['file_mime_type'] = $fileData['mime'];
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        // Handle thumbnail replacement
        if ($request->hasFile('thumbnail')) {
            $this->uploadService->delete($trainingContent->thumbnail_path);
            $data['thumbnail_path'] = $this->uploadService->uploadThumbnail($request->file('thumbnail'));
        }

        $trainingContent->update($data);

        return response()->json([
            'content' => $this->formatContent($trainingContent->load('category')),
            'message' => 'Conteúdo atualizado com sucesso.',
        ]);
    }

    public function destroy(TrainingContent $trainingContent)
    {
        $trainingContent->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
        ]);

        return response()->json(['message' => 'Conteúdo excluído com sucesso.']);
    }

    // ==========================================
    // Categories CRUD
    // ==========================================

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        $validated['created_by_user_id'] = auth()->id();

        $category = TrainingContentCategory::create($validated);

        return response()->json(['category' => $category]);
    }

    public function updateCategory(Request $request, TrainingContentCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json(['category' => $category]);
    }

    // ==========================================
    // Private helpers
    // ==========================================

    private function formatContent(TrainingContent $content): array
    {
        return [
            'id' => $content->id,
            'hash_id' => $content->hash_id,
            'title' => $content->title,
            'description' => $content->description,
            'content_type' => $content->content_type,
            'type_label' => $content->type_label,
            'type_icon' => $content->type_icon,
            'type_color' => $content->type_color,
            'file_name' => $content->file_name,
            'file_size_formatted' => $content->file_size_formatted,
            'external_url' => $content->external_url,
            'duration_formatted' => $content->duration_formatted,
            'thumbnail_path' => $content->thumbnail_path,
            'category' => $content->category ? [
                'id' => $content->category->id,
                'name' => $content->category->name,
                'icon' => $content->category->icon,
                'color' => $content->category->color,
            ] : null,
            'is_active' => $content->is_active,
            'created_by' => $content->createdBy?->name,
            'created_at' => $content->created_at->format('d/m/Y H:i'),
        ];
    }

    private function formatContentDetail(TrainingContent $content): array
    {
        return array_merge($this->formatContent($content), [
            'file_path' => $content->file_path,
            'file_size' => $content->file_size,
            'file_mime_type' => $content->file_mime_type,
            'text_content' => $content->text_content,
            'duration_seconds' => $content->duration_seconds,
            'updated_by' => $content->updatedBy?->name,
            'updated_at' => $content->updated_at?->format('d/m/Y H:i'),
        ]);
    }
}
