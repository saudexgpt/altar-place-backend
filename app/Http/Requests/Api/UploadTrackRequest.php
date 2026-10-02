<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

class UploadTrackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The 'file' rule's "failed to upload" message hides PHP's actual
     * UPLOAD_ERR_* code, which is the only way to tell a real size/temp-dir/
     * disk problem on the server apart from, e.g., a genuinely malformed
     * request — logged here so a failure is diagnosable from the app logs
     * alone, without needing shell access to the production container.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $audio = $this->file('audio');

            if ($audio instanceof UploadedFile && ! $audio->isValid()) {
                Log::warning('Track upload rejected: audio file failed PHP upload check', [
                    'error_code' => $audio->getError(),
                    'error_message' => $audio->getErrorMessage(),
                    'user_id' => $this->user()?->id,
                ]);
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:music,podcast,sermon'],
            'genre_id' => ['nullable', 'exists:genres,id'],
            'album_id' => ['nullable', 'exists:albums,id'],
            'language' => ['nullable', 'string', 'max:50'],
            'release_date' => ['nullable', 'date'],
            'is_explicit' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            // 200MB — long-form sermons/podcasts can comfortably run past an
            // hour at a reasonable bitrate; matches docker/nginx.conf and
            // docker/php.ini's upload limits.
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,aac', 'max:204800'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
