<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Services\PeriodRange;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportLimitException;
use App\Services\Reports\ReportService;
use App\Services\TransactionOptionsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function index(
        ReportRequest $request,
        ReportService $reports,
        TransactionOptionsService $options,
        PeriodRange $periods,
    ): Response {
        $definition = $request->definition();
        $range = $periods->resolve($definition['period'], $definition['from'], $definition['to']);

        return Inertia::render('Reports/Index', [
            'definition' => $definition,
            'period' => [
                'key' => $definition['period'],
                'from' => $range['from'],
                'to' => $range['to'],
                'label' => $range['label'],
            ],
            'report' => $reports->build($definition, 'preview')->toArray(),
            'options' => $options->catalog(),
            'limits' => $reports->limits(),
        ]);
    }

    public function download(
        ReportRequest $request,
        ReportService $reports,
        ReportExporter $exporter,
    ): HttpResponse|RedirectResponse {
        $definition = $request->definition();

        try {
            $report = $reports->build($definition, $request->downloadFormat());
        } catch (ReportLimitException $exception) {
            return redirect()
                ->route('reports', $request->redirectQuery())
                ->withErrors(['download' => $exception->getMessage()]);
        }

        if ($report->empty) {
            return redirect()
                ->route('reports', $request->redirectQuery())
                ->withErrors(['download' => 'Nenhum dado neste período.']);
        }

        return $exporter->download($report, $request->downloadFormat());
    }
}
