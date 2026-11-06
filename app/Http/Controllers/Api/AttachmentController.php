<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * Like WhatsApp, documents of any format can be sent, except the ones
     * the web server or a browser would run: files are publicly served from
     * the API's domain. Both the name the phone gave and the format guessed
     * from the contents are checked.
     */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
        'html', 'htm', 'xhtml', 'shtml', 'xht', 'svg', 'svgz', 'xml', 'xsl', 'xslt',
        'js', 'mjs', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'htaccess',
    ];

    /**
     * Upload a file to attach to a message of the conversation.
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        return self::storeIn($request, "chat-media/{$conversation->id}");
    }

    /**
     * Validate the uploaded file and store it in the chat's folder.
     */
    public static function storeIn(Request $request, string $folder): JsonResponse
    {
        $maxKilobytes = config('filesystems.chat_attachment_max_mb') * 1024;

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    if ($value instanceof UploadedFile && self::isBlocked($value, $request->input('name'))) {
                        $fail('Ce type de fichier ne peut pas être envoyé.');
                    }
                },
            ],
            // Name of the file on the phone, when the upload itself has none
            // (Android's document picker gives content:// addresses)
            'name' => ['nullable', 'string', 'max:255'],
        ], [
            'file.max' => 'Le fichier dépasse '.config('filesystems.chat_attachment_max_mb').' Mo.',
            'file.uploaded' => 'Le fichier est trop volumineux pour le serveur.',
        ]);

        $file = $request->file('file');
        $path = $file->store($folder, 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'name' => $validated['name'] ?? $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ], 201);
    }

    private static function isBlocked(UploadedFile $file, ?string $name): bool
    {
        $extensions = [
            strtolower($file->getClientOriginalExtension()),
            strtolower((string) $file->guessExtension()),
            strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)),
        ];

        return array_intersect($extensions, self::BLOCKED_EXTENSIONS) !== [];
    }
}
