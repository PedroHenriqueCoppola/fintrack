<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Validation\ValidationException;

class BankService
{
    public function __construct(private UniqueActiveName $names) {}

    public function create(array $data): Bank
    {
        $name = trim($data['name']);
        $this->names->assert(Bank::class, $name, 'Já existe um banco ativo com este nome.');

        $bank = new Bank;
        $bank->name = $name;
        $bank->color = $data['color'] ?? null;
        $bank->save();

        return $bank;
    }

    public function update(Bank $bank, array $data): Bank
    {
        $name = trim($data['name']);

        if ($bank->archived_at === null) {
            $this->names->assert(Bank::class, $name, 'Já existe um banco ativo com este nome.', $bank->id);
        }

        $bank->name = $name;
        $bank->color = $data['color'] ?? null;
        $bank->save();

        return $bank;
    }

    public function archive(Bank $bank): void
    {
        $inUse = $bank->pockets()->whereNull('archived_at')->exists()
            || $bank->cards()->whereNull('archived_at')->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'bank' => 'Arquive antes as caixinhas e cartões deste banco.',
            ]);
        }

        $bank->archived_at = now();
        $bank->save();
    }

    public function restore(Bank $bank): void
    {
        $this->names->assert(Bank::class, $bank->name, 'Já existe um banco ativo com este nome.', $bank->id);

        $bank->archived_at = null;
        $bank->save();
    }
}
