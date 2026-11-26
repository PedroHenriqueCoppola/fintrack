<?php

use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fintrack:demo {--force : Recreate the demo account even in production}', function () {
    if (app()->environment('production') && ! $this->option('force')) {
        $this->components->error('Este comando não roda em produção sem --force.');

        return 1;
    }

    $summary = app(DemoUserSeeder::class)->seed();

    $this->newLine();
    $this->info('Conta demo recriada.');
    $this->line('Transações: '.$summary['transactions']);
    $this->line('  Receitas: '.$summary['income']);
    $this->line('  Despesas: '.$summary['expenses']);
    $this->line('  Reservas: '.$summary['reserves']);
    $this->line('  Pendentes: '.$summary['pending']);
    $this->line('Fixos: '.$summary['fixed']);
    $this->line('Parcelados: '.$summary['installments']);
    $this->line('Metas: '.$summary['goals']);
    $this->line('Fechamentos: '.$summary['closings']);
    $this->line('Planos: '.$summary['plans']);
    $this->line('Faturas pagas: '.$summary['invoices']);
    $this->line('Tags: '.$summary['tags'].' em '.$summary['tagged'].' transações');
    $this->line('Filtros salvos: '.$summary['filters']);
    $this->newLine();
    $this->line('E-mail: '.DemoUserSeeder::EMAIL);
    $this->line('Senha: '.DemoUserSeeder::PASSWORD);

    return 0;
})->purpose('Recreate the demo account with a year of realistic data');
