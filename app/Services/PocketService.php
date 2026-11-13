<?php

namespace App\Services;

use App\Models\Pocket;
use Illuminate\Validation\ValidationException;

class PocketService
{
    public function __construct(private UniqueActiveName $names) {}

    public function create(array $data): Pocket
    {
        $name = trim($data['name']);
        $this->names->assert(
            Pocket::class,
            $name,
            'Já existe uma caixinha ativa com este nome neste banco.',
            null,
            'bank_id',
            $data['bank_id'],
        );

        $pocket = new Pocket;
        $pocket->bank_id = $data['bank_id'];
        $pocket->name = $name;
        $pocket->type = $data['type'] ?? null;
        $pocket->color = $data['color'] ?? null;
        $pocket->save();

        return $pocket;
    }

    public function update(Pocket $pocket, array $data): Pocket
    {
        $name = trim($data['name']);

        if ($pocket->archived_at === null) {
            $this->names->assert(
                Pocket::class,
                $name,
                'Já existe uma caixinha ativa com este nome neste banco.',
                $pocket->id,
                'bank_id',
                $data['bank_id'],
            );
        }

        $pocket->bank_id = $data['bank_id'];
        $pocket->name = $name;
        $pocket->type = $data['type'] ?? null;
        $pocket->color = $data['color'] ?? null;
        $pocket->save();

        return $pocket;
    }

    public function archive(Pocket $pocket): void
    {
        $pocket->archived_at = now();
        $pocket->save();
    }

    public function restore(Pocket $pocket): void
    {
        if ($pocket->bank->archived_at !== null) {
            throw ValidationException::withMessages([
                'bank_id' => 'Não é possível desarquivar uma caixinha ligada a um banco arquivado.',
            ]);
        }

        $this->names->assert(
            Pocket::class,
            $pocket->name,
            'Já existe uma caixinha ativa com este nome neste banco.',
            $pocket->id,
            'bank_id',
            $pocket->bank_id,
        );

        $pocket->archived_at = null;
        $pocket->save();
    }
}
