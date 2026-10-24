<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * Allowed attachment extensions, checked against the file contents.
     *
     * Executable formats are never accepted since files are publicly served.
     */
    private const ALLOWED_MIMES = 'jpg,jpeg,png,webp,gif,heic,m4a,mp3,mp4,aac,wav,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip';

    /**
     * Upload a file to attach to a message of the conversation.
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.self::ALLOWED_MIMES, 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store("chat-media/{$conversation->id}", 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ], 201);
    }
}
