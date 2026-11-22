<?php

namespace App\Http\Requests;

use App\Support\AnalysisFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    private const BREAKDOWNS = ['category', 'bank', 'card'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $breakdown = $this->query('breakdown');
        $merge = [
            'period' => $this->query('period') ?: 'current_month',
            'breakdown' => in_array($breakdown, self::BREAKDOWNS, true) ? $breakdown : 'category',
        ];

        foreach (['from', 'to'] as $key) {
            if ($this->query($key) === '') {
                $merge[$key] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', 'string', Rule::in(AnalysisFilters::PERIODS)],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'breakdown' => ['required', 'string', Rule::in(self::BREAKDOWNS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.in' => 'Escolha um período da lista.',
            'from.required_if' => 'Informe a data inicial.',
            'from.date_format' => 'Informe uma data inicial válida.',
            'to.required_if' => 'Informe a data final.',
            'to.date_format' => 'Informe uma data final válida.',
            'to.after_or_equal' => 'A data inicial não pode ser maior que a final.',
            'breakdown.in' => 'Escolha categoria, banco ou cartão.',
        ];
    }

    /**
     * @return array{period: string, from: ?string, to: ?string, breakdown: string}
     */
    public function filters(): array
    {
        $data = $this->validated();
        $period = $data['period'];

        return [
            'period' => $period,
            'from' => $period === 'custom' ? $data['from'] : null,
            'to' => $period === 'custom' ? $data['to'] : null,
            'breakdown' => $data['breakdown'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('dashboard')->withErrors($validator)
        );
    }
}
