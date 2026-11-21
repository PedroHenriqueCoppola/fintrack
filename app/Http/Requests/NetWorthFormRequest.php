<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesClosingMonth;
use Illuminate\Foundation\Http\FormRequest;

class NetWorthFormRequest extends FormRequest
{
    use ValidatesClosingMonth;

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
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.required' => 'Informe um mês válido.',
            'month.regex' => 'Informe um mês válido.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $error = $this->closingMonthError($this->input('month'));

            if ($error !== null) {
                $validator->errors()->add('month', $error);
            }
        });
    }

    public function month(): string
    {
        return $this->validated('month');
    }
}
