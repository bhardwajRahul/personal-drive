<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class ListFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => CommonRequest::pathRules(),
        ];
    }
}
