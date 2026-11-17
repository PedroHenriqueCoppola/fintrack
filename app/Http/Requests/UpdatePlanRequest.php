<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Services\PlanningService;
use App\Support\Month;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePlanRequest extends FormRequest
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
            'expected_income' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999999.99'],
            'items' => ['present', 'array'],
            'items.*.category_id' => ['required', 'integer', 'distinct'],
            'items.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999999.99'],
            'user_id' => ['missing'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_income.required' => 'Informe a renda esperada.',
            'expected_income.numeric' => 'Informe a renda esperada.',
            'expected_income.min' => 'A renda esperada não pode ser negativa.',
            'expected_income.decimal' => 'Informe a renda esperada com no máximo duas casas decimais.',
            'expected_income.max' => 'A renda esperada é alta demais.',
            'items.present' => 'Informe os valores do mês.',
            'items.array' => 'Informe os valores do mês.',
            'items.*.category_id.required' => 'Escolha a categoria.',
            'items.*.category_id.integer' => 'Escolha a categoria.',
            'items.*.category_id.distinct' => 'A categoria foi informada duas vezes.',
            'items.*.amount.required' => 'Informe o valor do mês.',
            'items.*.amount.numeric' => 'Informe o valor do mês.',
            'items.*.amount.min' => 'O valor do mês não pode ser negativo.',
            'items.*.amount.decimal' => 'Informe o valor do mês com no máximo duas casas decimais.',
            'items.*.amount.max' => 'O valor do mês é alto demais.',
            'user_id.missing' => 'O usuário não pode ser informado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $month = Month::startOrNull((string) $this->route('month'));

            if ($month === null) {
                $validator->errors()->add('month', 'Informe um mês entre 01/2000 e 12/2100.');

                return;
            }

            $allowed = app(PlanningService::class)->archivedCategoryIds($month);
            $ids = collect($this->input('items'))->pluck('category_id')->unique()->all();
            $categories = Category::query()->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($this->input('items') as $index => $item) {
                $category = $categories->get((int) $item['category_id']);

                if (! $category instanceof Category || $category->applies_to !== 'expense') {
                    $validator->errors()->add('items.'.$index.'.category_id', 'Escolha uma categoria de despesa.');
                    continue;
                }

                $known = in_array((int) $category->id, $allowed, true);

                if ($category->archived_at !== null && ! $known) {
                    $validator->errors()->add('items.'.$index.'.category_id', 'Escolha uma categoria de despesa ativa.');
                }
            }
        });
    }
}
