<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Card;
use App\Models\FixedExpense;
use App\Models\Installment;
use App\Models\Transaction;
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
        $bank->kind = $data['kind'] ?? 'account';
        $bank->save();

        return $bank;
    }

    public function update(Bank $bank, array $data): Bank
    {
        $name = trim($data['name']);

        if ($bank->archived_at === null) {
            $this->names->assert(Bank::class, $name, 'Já existe um banco ativo com este nome.', $bank->id);
        }

        $kind = $data['kind'] ?? $bank->kind ?? 'account';

        if (($bank->kind ?? 'account') !== 'investment' && $kind === 'investment') {
            $this->assertCanBecomeInvestment($bank);
        }

        $bank->name = $name;
        $bank->color = $data['color'] ?? null;
        $bank->kind = $kind;
        $bank->save();

        return $bank;
    }

    public function archive(Bank $bank): void
    {
        if ($bank->cards()->whereNull('archived_at')->exists()) {
            throw ValidationException::withMessages([
                'bank' => 'Arquive antes os cartões deste banco.',
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

    private function assertCanBecomeInvestment(Bank $bank): void
    {
        $used = Transaction::query()->where('bank_id', $bank->id)->exists()
            || Card::query()->where('bank_id', $bank->id)->exists()
            || FixedExpense::query()->where('bank_id', $bank->id)->exists()
            || Installment::query()->where('bank_id', $bank->id)->exists();

        if ($used) {
            throw ValidationException::withMessages([
                'kind' => 'Um banco com transações, cartões, fixos ou parcelados não pode virar investimento.',
            ]);
        }
    }
}
