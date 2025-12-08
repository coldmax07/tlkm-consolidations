<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Models\IcTransaction;
use App\Models\Message;
use App\Models\MessageRoleContext;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ThreadController extends Controller
{
    public function show(Request $request, IcTransaction $transaction): JsonResponse
    {
        $thread = $this->getOrCreateThread($transaction, $request->user()?->id);

        $this->authorize('view', $thread);

        $thread->load([
            'messages' => fn ($query) => $query->orderBy('created_at'),
            'messages.author',
            'messages.company',
            'messages.roleContext',
            'messages.attachments',
        ]);

        return response()->json($this->transformThread($thread));
    }

    public function storeMessage(StoreMessageRequest $request, IcTransaction $transaction): JsonResponse
    {
        $thread = $this->getOrCreateThread($transaction, $request->user()?->id);

        $this->authorize('view', $thread);

        $roleContextId = $request->input('role_context_id')
            ?? $this->inferRoleContextId($request->user());

        $message = $thread->messages()->create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'role_context_id' => $roleContextId,
            'body' => $request->input('body'),
        ]);

        $message->load(['author', 'company', 'roleContext', 'attachments']);

        return response()->json([
            'message' => $this->transformMessage($message),
        ], 201);
    }

    public function storeAttachment(StoreAttachmentRequest $request, Message $message): JsonResponse
    {
        $message->load('thread.transaction');

        $this->authorize('view', $message);

        $file = $request->file('file');
        $disk = config('filesystems.default', 'public');
        $path = $file->store('attachments', $disk);

        $attachment = $message->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'attachment' => $this->transformAttachment($attachment),
        ], 201);
    }

    protected function getOrCreateThread(IcTransaction $transaction, ?int $userId): Thread
    {
        $thread = $transaction->thread;

        if (! $thread) {
            $thread = $transaction->thread()->create([
                'created_by_id' => $userId,
            ]);
        }

        return $thread;
    }

    protected function inferRoleContextId($user): ?int
    {
        static $contextMap;

        if ($contextMap === null) {
            $contextMap = MessageRoleContext::pluck('id', 'name');
        }

        if ($user?->hasRole('group_admin')) {
            return $contextMap['ADMIN'] ?? null;
        }

        if ($user?->hasRole('company_admin')) {
            return $contextMap['ADMIN'] ?? null;
        }

        return $contextMap['PREPARER'] ?? null;
    }

    protected function transformThread(Thread $thread): array
    {
        return [
            'id' => $thread->id,
            'transaction_id' => $thread->ic_transaction_id,
            'messages' => $thread->messages->map(fn ($message) => $this->transformMessage($message))->values(),
        ];
    }

    protected function transformMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'company' => [
                'id' => $message->company?->id,
                'name' => $message->company?->name,
                'code' => $message->company?->code,
            ],
            'author' => [
                'id' => $message->author?->id,
                'name' => $message->author?->name,
            ],
            'role_context' => $message->roleContext?->display_label,
            'created_at' => $message->created_at?->toIso8601String(),
            'attachments' => $message->attachments->map(fn ($attachment) => $this->transformAttachment($attachment))->values(),
        ];
    }

    protected function transformAttachment($attachment): array
    {
        return [
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'url' => Storage::disk($attachment->disk ?? 'public')->url($attachment->path),
        ];
    }
}
