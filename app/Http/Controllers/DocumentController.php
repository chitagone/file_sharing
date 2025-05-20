<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DocumentAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Get all documents for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Document::with(['versions' => function($query) {
            $query->latest('version_number')->first();
        }, 'folder', 'tags'])
        ->where('owner_id', $user->id)
        ->where('is_deleted', false);

        // Apply filters
        if ($request->has('folder_id')) {
            $query->where('folder_id', $request->folder_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('favorites') && $request->favorites) {
            $query->where('is_favorite', true);
        }

        $documents = $query->orderBy('updated_at', 'desc')->paginate(20);

        return response()->json($documents);
    }

    /**
     * Upload a new document
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'folder_id' => 'nullable|exists:folders,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100'
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $title = $request->title ?? pathinfo($originalName, PATHINFO_FILENAME);
            
            // Generate unique filename to prevent conflicts
            $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Store file
            $filePath = $file->storeAs('documents', $filename, 'public');
            
            // Create document record
            $document = Document::create([
                'owner_id' => Auth::id(),
                'folder_id' => $request->folder_id,
                'title' => $title,
                'description' => $request->description,
                'latest_version' => 1,
            ]);

            // Create first version
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_name' => $originalName,
                'file_path' => $filePath,
                'file_type' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'storage_provider' => 'local',
                'uploaded_by' => Auth::id(),
                'change_summary' => 'Initial upload'
            ]);

            // Add tags if provided
            if ($request->has('tags') && is_array($request->tags)) {
                $tagIds = [];
                foreach ($request->tags as $tagName) {
                    $tag = \App\Models\Tag::firstOrCreate(['name' => $tagName]);
                    $tagIds[] = $tag->id;
                }
                $document->tags()->sync($tagIds);
            }

            // Log the upload action
            DocumentAccessLog::create([
                'document_id' => $document->id,
                'user_id' => Auth::id(),
                'version_id' => $document->versions->first()->id,
                'action' => 'upload',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            // Load relationships for response
            $document->load(['versions.uploader', 'folder', 'tags', 'owner']);

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => $document
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded file if document creation failed
            if (isset($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            return response()->json([
                'message' => 'Upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific document
     */
    public function show($id): JsonResponse
    {
        $document = Document::with(['versions.uploader', 'folder', 'tags', 'owner'])
            ->findOrFail($id);

        // Check if user has access to this document
        if (!$this->userCanAccessDocument($document)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update last accessed time
        $document->update(['last_accessed_at' => now()]);

        // Log the view action
        DocumentAccessLog::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'action' => 'view',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        return response()->json($document);
    }

    /**
     * Download a document
     */
    public function download($id, $versionId = null): StreamedResponse
    {
        $document = Document::findOrFail($id);

        // Check if user has access to this document
        if (!$this->userCanAccessDocument($document)) {
            abort(403, 'Unauthorized');
        }

        // Get the specific version or latest version
        if ($versionId) {
            $version = DocumentVersion::where('document_id', $document->id)
                ->where('id', $versionId)
                ->firstOrFail();
        } else {
            $version = $document->versions()->first();
        }

        if (!$version) {
            abort(404, 'Document version not found');
        }

        // Check if file exists
        if (!Storage::disk('public')->exists($version->file_path)) {
            abort(404, 'File not found');
        }

        // Log the download action
        DocumentAccessLog::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'version_id' => $version->id,
            'action' => 'download',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        // Return file download response
        return Storage::disk('public')->download(
            $version->file_path,
            $version->file_name,
            [
                'Content-Type' => $version->mime_type,
                'Content-Length' => $version->file_size
            ]
        );
    }

    /**
     * Preview a document (for images and PDFs)
     */
    public function preview($id): JsonResponse
    {
        $document = Document::findOrFail($id);

        // Check if user has access to this document
        if (!$this->userCanAccessDocument($document)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $version = $document->versions()->first();
        
        if (!$version) {
            return response()->json(['message' => 'No version found'], 404);
        }

        // Log the preview action
        DocumentAccessLog::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'version_id' => $version->id,
            'action' => 'preview',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        $previewUrl = Storage::disk('public')->url($version->file_path);

        return response()->json([
            'preview_url' => $previewUrl,
            'file_type' => $version->file_type,
            'mime_type' => $version->mime_type,
            'can_preview' => $this->canPreview($version)
        ]);
    }

    /**
     * Update document metadata
     */
    public function update(Request $request, $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        // Check if user owns this document
        if ($document->owner_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'folder_id' => 'nullable|exists:folders,id',
            'is_favorite' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100'
        ]);

        $document->update($request->only(['title', 'description', 'folder_id', 'is_favorite']));

        // Update tags if provided
        if ($request->has('tags')) {
            $tagIds = [];
            foreach ($request->tags as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }
            $document->tags()->sync($tagIds);
        }

        $document->load(['versions.uploader', 'folder', 'tags', 'owner']);

        return response()->json([
            'message' => 'Document updated successfully',
            'document' => $document
        ]);
    }

    /**
     * Soft delete a document
     */
    public function destroy($id): JsonResponse
    {
        $document = Document::findOrFail($id);

        // Check if user owns this document
        if ($document->owner_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $document->update(['is_deleted' => true, 'purge_at' => now()->addDays(30)]);

        // Log the delete action
        DocumentAccessLog::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'action' => 'delete',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        return response()->json(['message' => 'Document deleted successfully']);
    }

    /**
     * Upload a new version of an existing document
     */
    public function uploadVersion(Request $request, $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
            'change_summary' => 'nullable|string'
        ]);

        $document = Document::findOrFail($id);

        // Check if user owns this document
        if ($document->owner_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            
            // Generate unique filename
            $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Store file
            $filePath = $file->storeAs('documents', $filename, 'public');
            
            // Get next version number
            $nextVersion = $document->latest_version + 1;

            // Create new version
            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersion,
                'file_name' => $originalName,
                'file_path' => $filePath,
                'file_type' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'storage_provider' => 'local',
                'uploaded_by' => Auth::id(),
                'change_summary' => $request->change_summary ?? 'Version update'
            ]);

            // Update document's latest version
            $document->update(['latest_version' => $nextVersion]);

            // Log the version upload action
            DocumentAccessLog::create([
                'document_id' => $document->id,
                'user_id' => Auth::id(),
                'version_id' => $version->id,
                'action' => 'update',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            $document->load(['versions.uploader', 'folder', 'tags', 'owner']);

            return response()->json([
                'message' => 'New version uploaded successfully',
                'document' => $document,
                'version' => $version
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded file if version creation failed
            if (isset($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            return response()->json([
                'message' => 'Version upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can access a document
     */
    private function userCanAccessDocument(Document $document): bool
    {
        $userId = Auth::id();
        
        // Owner can always access
        if ($document->owner_id === $userId) {
            return true;
        }

        // Check if document is public
        if ($document->is_public) {
            return true;
        }

        // Check if document is shared with user
        $hasDirectShare = $document->shares()
            ->where('shared_with_user', $userId)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasDirectShare) {
            return true;
        }

        // Check if document is shared with user's groups
        // This would require additional logic based on user's group memberships

        return false;
    }

    /**
     * Check if a file can be previewed
     */
    private function canPreview(DocumentVersion $version): bool
    {
        $previewableTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'];
        return in_array(strtolower($version->file_type), $previewableTypes);
    }
}