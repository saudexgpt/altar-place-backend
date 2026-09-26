<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdvertisementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'completed'])],
            'headline' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'image' => ['sometimes', 'image', 'max:4096'],
            'audio' => ['sometimes', 'file', 'mimes:mp3,wav,m4a,ogg', 'max:20480'],
            'cta_label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'cta_url' => ['sometimes', 'nullable', 'url', 'max:255'],

            'targeting' => ['sometimes', 'nullable', 'array'],
            'targeting.countries' => ['nullable', 'array'],
            'targeting.countries.*' => ['string', 'max:255'],
            'targeting.states' => ['nullable', 'array'],
            'targeting.states.*' => ['string', 'max:255'],
            'targeting.cities' => ['nullable', 'array'],
            'targeting.cities.*' => ['string', 'max:255'],
            'targeting.device_types' => ['nullable', 'array'],
            'targeting.device_types.*' => ['string', 'max:255'],
            'targeting.genre_ids' => ['nullable', 'array'],
            'targeting.genre_ids.*' => ['integer', 'exists:genres,id'],
            'targeting.age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'targeting.age_max' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:targeting.age_min'],

            'daily_impression_cap' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
