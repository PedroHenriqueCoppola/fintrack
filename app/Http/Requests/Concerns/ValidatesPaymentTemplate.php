<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use App\Support\CatalogGuard;
use App\Rules\PlainText;
use Illuminate\Validation\Validator;

trait ValidatesPaymentTemplate
{
    protected function prepareTemplateFields(): void
    {
        $merge = [];

        foreach (['notes', 'card_id', 'bank_id', 'end_month'] as $key) {
            if ($this->input($key) === '') {
                $merge[$key] = null;
            }
        }

        if (is_string($this->input('name'))) {
            $merge['name'] = PlainText::collapse($this->input('name'));
        }

        if (is_string($this->input('notes'))) {
            $notes = PlainText::collapse($this->input('notes'), multiline: true);
            $merge['notes'] = $notes === '' ? null : $notes;
        }

        $this->merge($merge);
    }

    protected function validateSpendingCategory(Validator $validator, ?int $currentCategoryId): void
    {
        $category = Category::query()->find($this->integer('category_id'));

        if (! $category instanceof Category) {
            $validator->errors()->add('category_id', 'Escolha uma categoria de gasto ativa.');

            return;
        }

        if ($category->applies_to !== 'expense' || $category->nature !== 'spending') {
            $validator->errors()->add('category_id', 'Escolha uma categoria de gasto.');

            return;
        }

        if ($category->archived_at !== null && $currentCategoryId !== (int) $category->id) {
            $validator->errors()->add('category_id', 'Escolha uma categoria de gasto ativa.');
        }
    }

    protected function validatePayment(Validator $validator, ?int $currentMethodId, ?int $currentBankId, ?int $currentCardId): void
    {
        CatalogGuard::payment(
            $validator,
            $this->input('payment_method_id'),
            $this->input('bank_id'),
            $this->input('card_id'),
            $currentMethodId,
            $currentBankId,
            $currentCardId,
        );
    }
}
