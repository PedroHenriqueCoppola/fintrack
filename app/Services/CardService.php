<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Validation\ValidationException;

class CardService
{
    public function __construct(private UniqueActiveName $names) {}

    public function create(array $data): Card
    {
        $name = trim($data['name']);
        $this->names->assert(
            Card::class,
            $name,
            'Já existe um cartão ativo com este nome neste banco.',
            null,
            'bank_id',
            $data['bank_id'],
        );

        $card = new Card;
        $card->bank_id = $data['bank_id'];
        $card->name = $name;
        $card->closing_day = $data['closing_day'];
        $card->due_day = $data['due_day'] ?? null;
        $card->color = $data['color'] ?? null;
        $card->save();

        return $card;
    }

    public function update(Card $card, array $data): Card
    {
        $name = trim($data['name']);

        if ($card->archived_at === null) {
            $this->names->assert(
                Card::class,
                $name,
                'Já existe um cartão ativo com este nome neste banco.',
                $card->id,
                'bank_id',
                $data['bank_id'],
            );
        }

        $card->bank_id = $data['bank_id'];
        $card->name = $name;
        $card->closing_day = $data['closing_day'];
        $card->due_day = $data['due_day'] ?? null;
        $card->color = $data['color'] ?? null;
        $card->save();

        return $card;
    }

    public function archive(Card $card): void
    {
        $card->archived_at = now();
        $card->save();
    }

    public function restore(Card $card): void
    {
        if ($card->bank->archived_at !== null) {
            throw ValidationException::withMessages([
                'bank_id' => 'Não é possível desarquivar um cartão ligado a um banco arquivado.',
            ]);
        }

        $this->names->assert(
            Card::class,
            $card->name,
            'Já existe um cartão ativo com este nome neste banco.',
            $card->id,
            'bank_id',
            $card->bank_id,
        );

        $card->archived_at = null;
        $card->save();
    }
}
