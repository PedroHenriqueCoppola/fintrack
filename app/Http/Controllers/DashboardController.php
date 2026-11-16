<?php

namespace App\Http\Controllers;

use App\Services\TransactionOptionsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(TransactionOptionsService $options): Response
    {
        return Inertia::render('Dashboard/Index', [
            'options' => $options->catalog(),
        ]);
    }
}
