<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cores permitidas
    |--------------------------------------------------------------------------
    |
    | A paleta fica no servidor. O valor gravado é o HSL, e a tela só oferece
    | estas opções.
    |
    */

    'colors' => [
        ['key' => 'purple', 'value' => 'hsl(268 83% 62%)', 'label' => 'Roxo'],
        ['key' => 'violet', 'value' => 'hsl(258 70% 66%)', 'label' => 'Violeta'],
        ['key' => 'indigo', 'value' => 'hsl(230 65% 62%)', 'label' => 'Índigo'],
        ['key' => 'blue', 'value' => 'hsl(207 80% 56%)', 'label' => 'Azul'],
        ['key' => 'cyan', 'value' => 'hsl(187 70% 42%)', 'label' => 'Ciano'],
        ['key' => 'green', 'value' => 'hsl(152 55% 42%)', 'label' => 'Verde'],
        ['key' => 'amber', 'value' => 'hsl(38 92% 50%)', 'label' => 'Âmbar'],
        ['key' => 'orange', 'value' => 'hsl(24 90% 56%)', 'label' => 'Laranja'],
        ['key' => 'rose', 'value' => 'hsl(340 75% 60%)', 'label' => 'Rosa'],
        ['key' => 'magenta', 'value' => 'hsl(300 60% 58%)', 'label' => 'Magenta'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ícones permitidos
    |--------------------------------------------------------------------------
    |
    | O valor é o nome do componente na lucide-react. A tela não aceita outro.
    |
    */

    'icons' => [
        ['value' => 'ShoppingCart', 'label' => 'Mercado'],
        ['value' => 'House', 'label' => 'Moradia'],
        ['value' => 'Car', 'label' => 'Transporte'],
        ['value' => 'HeartPulse', 'label' => 'Saúde'],
        ['value' => 'Ticket', 'label' => 'Lazer'],
        ['value' => 'Ellipsis', 'label' => 'Outros'],
        ['value' => 'Shield', 'label' => 'Proteção'],
        ['value' => 'TrendingUp', 'label' => 'Investimento'],
        ['value' => 'Wallet', 'label' => 'Carteira'],
        ['value' => 'Banknote', 'label' => 'Dinheiro'],
        ['value' => 'Utensils', 'label' => 'Refeição'],
        ['value' => 'Coffee', 'label' => 'Café'],
        ['value' => 'Shirt', 'label' => 'Roupa'],
        ['value' => 'GraduationCap', 'label' => 'Estudo'],
        ['value' => 'Baby', 'label' => 'Família'],
        ['value' => 'Plane', 'label' => 'Viagem'],
        ['value' => 'Gift', 'label' => 'Presente'],
        ['value' => 'Smartphone', 'label' => 'Telefone'],
        ['value' => 'Zap', 'label' => 'Conta'],
        ['value' => 'PiggyBank', 'label' => 'Cofrinho'],
        ['value' => 'Landmark', 'label' => 'Banco'],
        ['value' => 'CreditCard', 'label' => 'Cartão'],
        ['value' => 'Briefcase', 'label' => 'Trabalho'],
        ['value' => 'CircleDollarSign', 'label' => 'Valor'],
    ],

    'pocket_types' => [
        ['value' => 'balance', 'label' => 'Saldo'],
        ['value' => 'piggy_bank', 'label' => 'Cofrinho'],
        ['value' => 'investment', 'label' => 'Investimento'],
    ],

    'category_types' => [
        ['value' => 'expense', 'label' => 'Despesa'],
        ['value' => 'income', 'label' => 'Receita'],
    ],

    'natures' => [
        ['value' => 'spending', 'label' => 'Gasto'],
        ['value' => 'reserve', 'label' => 'Reserva'],
    ],

];
