<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
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
            'reportable_type' => ['required', 'string', 'in:track,comment'],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'in:spam,abuse,copyright,inappropriate,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
