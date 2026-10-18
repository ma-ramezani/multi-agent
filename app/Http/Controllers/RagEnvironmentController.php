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
        $rag->load(['documents' => fn ($q) => $q->orderBy('id', 'desc')]);
        $stats = [
            'docs' => $rag->documents()->count(),
            'chunks' => $rag->chunks()->count(),
            'embedded' => $rag->chunks()->whereNotNull('embedding')->count(),
        ];
        $chunks = $rag->chunks()->with('document')->orderBy('id', 'desc')->limit(30)->get();
        return view('rags.show', ['env' => $rag, 'stats' => $stats, 'chunks' => $chunks]);
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
            'files.*' => 'required|file|max:51200',
            'raw_text' => 'nullable|string|max:500000',
            'raw_name' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if (!\App\Neuron\DocumentReader::isSupported($file->getClientOriginalName())) {
                    return back()->withErrors(['files' => 'فرمت پشتیبانی نمی‌شود: ' . $file->getClientOriginalName() . ' — فرمت‌های مجاز: txt, md, csv, json, html, pdf, docx, xlsx, xls, pptx, png, jpg, webp']);
                }
            }
        }

        if ($request->filled('raw_text')) {
            $doc = RagDocument::create([
                'rag_environment_id' => $rag->id,
                'original_name' => $request->input('raw_name', 'متن دستی ' . now()->format('Y-m-d H:i')),
                'mime' => 'text/plain',
                'size' => strlen($request->input('raw_text')),
                'status' => 'pending',
                'raw_text' => $request->input('raw_text'),
            ]);
            RagService::ingestSoon($doc);
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
                RagService::ingestSoon($doc);
            }
        }

        return redirect()->route('rags.show', $rag)->with('ok', 'اسناد ثبت شدند — سبک‌ها فوری ایندکس می‌شوند، PDF و فایل‌های سنگین در پس‌زمینه پردازش می‌شوند');
    }

    public function reindex(RagEnvironment $rag, RagDocument $document)
    {
        RagService::ingestSoon($document->fresh());
        return back()->with('ok', $document->fresh()->status === 'processing'
            ? 'سند در صف پردازش پس‌زمینه قرار گرفت — چند دقیقه بعد رفرش کنید'
            : 'سند دوباره ایندکس شد');
    }

    public function deleteDoc(RagEnvironment $rag, RagDocument $document)
    {
        if ($document->rag_environment_id !== $rag->id) {
            abort(404);
        }
        if ($document->stored_path) {
            \Storage::disk('local')->delete($document->stored_path);
        }
        $document->chunks()->delete();
        $document->delete();
        return back()->with('ok', 'سند حذف شد');
    }

    public function editDoc(RagEnvironment $rag, RagDocument $document)
    {
        if ($document->rag_environment_id !== $rag->id) {
            abort(404);
        }
        $extracted = $document->raw_text
            ?: $document->chunks()->orderBy('id')->pluck('content')->implode("\n\n");
        $readKind = $document->chunks()->orderBy('id')->first()?->metadata['read_kind'] ?? null;
        return view('rags.doc-form', ['env' => $rag, 'doc' => $document, 'extracted' => mb_substr($extracted, 0, 200000), 'readKind' => $readKind]);
    }

    public function updateDoc(Request $request, RagEnvironment $rag, RagDocument $document)
    {
        if ($document->rag_environment_id !== $rag->id) {
            abort(404);
        }
        $data = $request->validate([
            'original_name' => 'required|string|max:255',
            'content' => 'nullable|string|max:1000000',
            'replace_file' => 'nullable|file|max:51200',
            'force_ocr' => 'nullable|boolean',
        ]);

        if ($request->hasFile('replace_file')) {
            $file = $request->file('replace_file');
            if (!\App\Neuron\DocumentReader::isSupported($file->getClientOriginalName())) {
                return back()->withErrors(['replace_file' => 'فرمت پشتیبانی نمی‌شود: ' . $file->getClientOriginalName()]);
            }
            if ($document->stored_path) {
                \Storage::disk('local')->delete($document->stored_path);
            }
            $document->stored_path = $file->store('rag-uploads/' . $rag->id, 'local');
            $document->mime = $file->getMimeType();
            $document->size = $file->getSize();
            $document->raw_text = null; // re-read from the new file
            if (empty($data['original_name']) || $data['original_name'] === $document->original_name) {
                $document->original_name = $file->getClientOriginalName();
            }
        } else {
            $document->original_name = $data['original_name'];
            // edited text becomes the source of truth
            $document->raw_text = $data['content'] ?? '';
        }
        $document->force_ocr = $request->boolean('force_ocr');
        $document->save();

        RagService::ingestSoon($document->fresh());
        $document->refresh();
        if ($document->status === 'failed') {
            return redirect()->route('rags.show', $rag)->withErrors(['doc' => 'ذخیره شد ولی ایندکس خطا داد: ' . $document->error]);
        }
        if ($document->status === 'processing') {
            return redirect()->route('rags.show', $rag)->with('ok', 'سند ذخیره شد و در صف پردازش پس‌زمینه قرار گرفت (PDF/فایل سنگین) — صفحه را رفرش کنید');
        }
        return redirect()->route('rags.show', $rag)->with('ok', 'سند ویرایش و دوباره ایندکس شد (' . fa_num($document->chunks_count) . ' چانک)');
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
