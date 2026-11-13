<?php

namespace App\Services;

use App\Models\PaymentMethod;

class PaymentMethodService
{
    public function __construct(private UniqueActiveName $names) {}

    public function create(array $data): PaymentMethod
    {
        $name = trim($data['name']);
        $this->names->assert(PaymentMethod::class, $name, 'Já existe uma forma de pagamento ativa com este nome.');

        $method = new PaymentMethod;
        $method->name = $name;
        $method->requires_card = $data['requires_card'];
        $method->save();

        return $method;
    }

    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        $name = trim($data['name']);

        if ($method->archived_at === null) {
            $this->names->assert(
                PaymentMethod::class,
                $name,
                'Já existe uma forma de pagamento ativa com este nome.',
                $method->id,
            );
        }

        $method->name = $name;
        $method->requires_card = $data['requires_card'];
        $method->save();

        return $method;
    }

    public function archive(PaymentMethod $method): void
    {
        $method->archived_at = now();
        $method->save();
    }

    public function restore(PaymentMethod $method): void
    {
        $this->names->assert(
            PaymentMethod::class,
            $method->name,
            'Já existe uma forma de pagamento ativa com este nome.',
            $method->id,
        );

        $method->archived_at = null;
        $method->save();
    }
}
