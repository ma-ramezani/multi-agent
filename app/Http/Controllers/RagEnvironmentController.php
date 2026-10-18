<?php

namespace App\Http\Controllers;

use App\Models\RagDocument;
use App\Models\RagEnvironment;
use App\Neuron\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RagEnvironmentController extends Controller
{
    public function index()
    {
        $envs = RagEnvironment::withCount(['documents', 'chunks'])->orderBy('id', 'desc')->get();
        return view('rags.index', compact('envs'));
    }

    public function create()
    {
        return view('rags.form', ['env' => new RagEnvironment(['embedding_provider' => 'openai', 'embedding_model' => 'text-embedding-3-small', 'chunk_size' => 1000, 'chunk_overlap' => 100, 'top_k' => 4, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']) . '-' . Str::random(4);
        $env = RagEnvironment::create($data);
        return redirect()->route('rags.show', $env)->with('ok', 'محیط RAG ساخته شد');
    }

    public function show(RagEnvironment $rag)
    {
        $rag->load(['documents' => fn ($q) => $q->orderBy('id', 'desc'), 'chunks']);
        return view('rags.show', ['env' => $rag]);
    }

    public function edit(RagEnvironment $rag)
    {
        return view('rags.form', ['env' => $rag]);
    }

    public function update(Request $request, RagEnvironment $rag)
    {
        $rag->update($this->validated($request, $rag->id));
        return redirect()->route('rags.show', $rag)->with('ok', 'محیط به‌روز شد');
    }

    public function destroy(RagEnvironment $rag)
    {
        $rag->delete();
        return redirect()->route('rags.index')->with('ok', 'محیط حذف شد');
    }

    public function upload(Request $request, RagEnvironment $rag)
    {
        $request->validate([
            'files.*' => 'required|file|max:20480',
            'raw_text' => 'nullable|string|max:200000',
            'raw_name' => 'nullable|string|max:255',
        ]);

        if ($request->filled('raw_text')) {
            $doc = RagDocument::create([
                'rag_environment_id' => $rag->id,
                'original_name' => $request->input('raw_name', 'متن دستی ' . now()->format('Y-m-d H:i')),
                'mime' => 'text/plain',
                'size' => strlen($request->input('raw_text')),
                'status' => 'pending',
                'raw_text' => $request->input('raw_text'),
            ]);
            RagService::ingestDocument($doc);
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('rag-uploads/' . $rag->id, 'local');
                $doc = RagDocument::create([
                    'rag_environment_id' => $rag->id,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'status' => 'pending',
                ]);
                RagService::ingestDocument($doc);
            }
        }

        return redirect()->route('rags.show', $rag)->with('ok', 'اسناد اضافه و ایندکس شدند');
    }

    public function reindex(RagEnvironment $rag, RagDocument $document)
    {
        RagService::ingestDocument($document);
        return back()->with('ok', 'سند دوباره ایندکس شد');
    }

    public function deleteDoc(RagEnvironment $rag, RagDocument $document)
    {
        $document->chunks()->delete();
        $document->delete();
        return back()->with('ok', 'سند حذف شد');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'embedding_provider' => 'required|string',
            'embedding_model' => 'required|string|max:255',
            'embedding_api_key' => 'nullable|string',
            'embedding_base_url' => 'nullable|string|max:500',
            'chunk_size' => 'nullable|integer|min:200|max:8000',
            'chunk_overlap' => 'nullable|integer|min:0|max:1000',
            'top_k' => 'nullable|integer|min:1|max:20',
            'is_active' => 'nullable|boolean',
        ]);
        if ($ignoreId && empty($v['embedding_api_key'])) {
            unset($v['embedding_api_key']);
        }
        $v['is_active'] = $request->boolean('is_active', true);
        return $v;
    }
}
