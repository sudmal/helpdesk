<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    /** Загрузка вложения (вызывается AJAX из компонента AttachmentUpload) */
    public function store(Request $request)
    {
        $request->validate([
            'file'       => ['required', 'file', 'max:102400', // 100MB
                'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mp3,ogg,wav,m4a,pdf,doc,docx,xls,xlsx'],
            'ticket_id'  => 'required|exists:tickets,id',
            'context'    => 'nullable|in:attachment,close,comment',
            'comment_id' => 'nullable|exists:ticket_comments,id',
        ]);

        $ticket = \App\Models\Ticket::findOrFail($request->ticket_id);
        $this->authorize('comment', $ticket);

        $attachment = $this->ticketService->storeAttachment(
            $ticket,
            $request->file('file'),
            auth()->user(),
            $request->context ?? 'attachment',
            $request->comment_id
        );

        return response()->json([
            'id'            => $attachment->id,
            'original_name' => $attachment->original_name,
            'url'           => $attachment->url,
            'mime_type'     => $attachment->mime_type,
            'size'          => $attachment->size,
            'is_image'      => $attachment->isImage(),
            'is_video'      => $attachment->isVideo(),
            'is_audio'      => $attachment->isAudio(),
        ]);
    }

    /** Скачивание файла */
    public function download(int $id)
    {
        $attachment = TicketAttachment::findOrFail($id);
        $this->authorize('view', $attachment->ticket);

        return Storage::disk('public')->download(
            $attachment->stored_path,
            $attachment->original_name
        );
    }

    /** Удаление вложения */
    public function destroy(int $id)
    {
        $attachment = TicketAttachment::findOrFail($id);
        $this->authorize('update', $attachment->ticket);

        Storage::disk('public')->delete($attachment->stored_path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }
}
