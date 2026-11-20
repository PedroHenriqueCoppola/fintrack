<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoicePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['bail', 'required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Informe a data.',
            'date.date_format' => 'Informe uma data válida.',
        ];
    }
}
