<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCommentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'body'           => 'required|string|max:5000',
            'is_internal'    => 'boolean',
            'attachments'    => 'nullable|array|max:10',
            'attachments.*'  => 'file|max:102400|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mp3,ogg,wav,m4a,pdf,doc,docx,xls,xlsx',
        ];
    }

    public function messages(): array
    {
        return ['body.required' => 'Введите текст комментария'];
    }
}
