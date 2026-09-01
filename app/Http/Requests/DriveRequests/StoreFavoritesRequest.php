<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreFavoritesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'local_file_ids' => ['required', 'array', 'min:1'],
            'local_file_ids.*' => CommonRequest::localFileIdRules(),
        ];
    }
}
