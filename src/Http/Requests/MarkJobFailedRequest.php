<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkJobFailedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'error_message' => ['required', 'string', 'max:2000'],
        ];
    }
}
