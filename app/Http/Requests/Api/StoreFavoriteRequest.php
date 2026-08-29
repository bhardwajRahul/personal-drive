<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'local_file_ids' => ['required', 'array', 'min:1'],
            'local_file_ids.*' => ['required', 'string', 'ulid'],
        ];
    }
}
