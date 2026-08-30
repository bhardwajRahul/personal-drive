<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class MoveFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fileList' => 'required|array',
            'fileList.*' => 'ulid',
            'destination' => ['required', ...CommonRequest::pathRules(allowSlash: true)],
        ];
    }
}
