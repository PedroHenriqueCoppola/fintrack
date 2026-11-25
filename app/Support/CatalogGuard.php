<?php

namespace App\Support;

use App\Models\Bank;
use App\Models\Card;
use App\Models\PaymentMethod;
use Illuminate\Validation\Validator;

class CatalogGuard
{
    /**
     * Same bank, payment method and card rules as a transaction.
     * A card forces its own bank. Archived rows are refused unless they
     * are already the saved value of the record being edited.
     */
    public static function payment(
        Validator $validator,
        mixed $methodId,
        mixed $bankId,
        mixed $cardId,
        ?int $currentMethodId,
        ?int $currentBankId,
        ?int $currentCardId,
    ): void {
        $method = PaymentMethod::query()->find($methodId);

        if (! $method instanceof PaymentMethod || ! self::usable($method, $currentMethodId)) {
            $validator->errors()->add('payment_method_id', 'Escolha uma forma de pagamento ativa.');

            return;
        }

        $card = null;

        if ($method->requires_card) {
            if (! $cardId) {
                $validator->errors()->add('card_id', 'Escolha o cartão.');
            }
        } elseif ($cardId) {
            $validator->errors()->add('card_id', 'Essa forma de pagamento não usa cartão.');
        }

        if ($cardId && ! $validator->errors()->has('card_id')) {
            $card = Card::query()->find($cardId);

            if (! $card instanceof Card || ! self::usable($card, $currentCardId)) {
                $validator->errors()->add('card_id', 'Escolha um cartão ativo.');
                $card = null;
            }
        }

        if ($card instanceof Card && $bankId && (int) $card->bank_id !== (int) $bankId) {
            self::cardBelongsToBank($validator, $bankId, $card->id);

            return;
        }

        $resolvedBankId = $card instanceof Card ? $card->bank_id : $bankId;

        if (! $resolvedBankId) {
            $validator->errors()->add('bank_id', 'Escolha o banco.');

            return;
        }

        $bank = Bank::query()->find($resolvedBankId);

        if (! $bank instanceof Bank || ! self::usable($bank, $currentBankId)) {
            $validator->errors()->add('bank_id', 'Escolha um banco ativo.');

            return;
        }

        if ($bank->kind === 'investment') {
            $validator->errors()->add('bank_id', 'Escolha um banco de conta.');
        }
    }

    public static function cardBelongsToBank(Validator $validator, mixed $bankId, mixed $cardId, string $field = 'card_id'): void
    {
        if ($bankId === null || $bankId === '' || $cardId === null || $cardId === '') {
            return;
        }

        $card = Card::query()->find($cardId);

        if ($card instanceof Card && (int) $card->bank_id !== (int) $bankId) {
            $validator->errors()->add($field, 'Esse cartão não é desse banco.');
        }
    }

    public static function accountBank(Validator $validator, mixed $bankId, string $field = 'bank_id'): void
    {
        if ($bankId === null || $bankId === '') {
            return;
        }

        $bank = Bank::query()->find($bankId);

        if ($bank instanceof Bank && $bank->kind === 'investment') {
            $validator->errors()->add($field, 'Escolha um banco de conta.');
        }
    }

    private static function usable(object $model, ?int $currentId): bool
    {
        if ($model->archived_at === null) {
            return true;
        }

        return $currentId !== null && (int) $currentId === (int) $model->getKey();
    }
}
