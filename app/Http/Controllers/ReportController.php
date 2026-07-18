<?php

namespace App\Http\Controllers;

use App\Exports\GenericTableExport;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    protected array $reports = [
        'daily', 'monthly', 'quarterly', 'master', 'time',
        'opportunity', 'failure', 'deal', 'requirement', 'conversion',
    ];

    public function __construct(protected ReportService $reportService) {}

    public function index(): View
    {
        return view('reports.index', ['reports' => $this->reports]);
    }

    public function daily(Request $request): View
    {
        $data = $this->reportService->daily($request->input('date', now()->toDateString()));

        return view('reports.show', $data + ['type' => 'daily']);
    }

    public function monthly(Request $request): View
    {
        $data = $this->reportService->monthly($request->input('month', now()->format('Y-m')));

        return view('reports.show', $data + ['type' => 'monthly']);
    }

    public function quarterly(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        $quarter = (int) $request->input('quarter', ceil(now()->month / 3));

        $data = $this->reportService->quarterly($year, $quarter);

        return view('reports.show', $data + ['type' => 'quarterly']);
    }

    public function master(Request $request): View
    {
        $data = $this->reportService->master($request->only(['user_id', 'status_id', 'company', 'from', 'to']));

        return view('reports.show', $data + [
            'type' => 'master',
            'users' => User::orderBy('name')->get(),
            'statuses' => LeadStatus::ordered()->get(),
        ]);
    }

    public function time(): View
    {
        return view('reports.show', $this->reportService->time() + ['type' => 'time']);
    }

    public function opportunity(): View
    {
        return view('reports.show', $this->reportService->opportunity() + ['type' => 'opportunity']);
    }

    public function failure(): View
    {
        return view('reports.show', $this->reportService->failure() + ['type' => 'failure']);
    }

    public function deal(Request $request): View
    {
        return view('reports.show', $this->reportService->deal($request->only(['from', 'to'])) + ['type' => 'deal']);
    }

    public function requirement(Request $request): View
    {
        return view('reports.show', $this->reportService->requirement($request->only(['status'])) + ['type' => 'requirement']);
    }

    public function conversion(): View
    {
        return view('reports.show', $this->reportService->conversion() + ['type' => 'conversion']);
    }

    public function export(Request $request, string $type, string $format): Response|BinaryFileResponse
    {
        abort_unless(in_array($type, $this->reports), 404);

        $method = lcfirst(str_replace('-', '', ucwords($type, '-')));
        $args = match ($type) {
            'daily' => [$request->input('date', now()->toDateString())],
            'monthly' => [$request->input('month', now()->format('Y-m'))],
            'quarterly' => [(int) $request->input('year', now()->year), (int) $request->input('quarter', ceil(now()->month / 3))],
            'master' => [$request->only(['user_id', 'status_id', 'company', 'from', 'to'])],
            'deal' => [$request->only(['from', 'to'])],
            'requirement' => [$request->only(['status'])],
            default => [],
        };

        $data = $this->reportService->{$method}(...$args);
        $rows = collect($data['rows'])->map(fn ($row) => (array) $row)->all();
        $filename = str($data['title'])->slug();

        return match ($format) {
            'excel' => Excel::download(new GenericTableExport($data['headings'], $rows), "{$filename}.xlsx"),
            'csv' => Excel::download(new GenericTableExport($data['headings'], $rows), "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV),
            'pdf' => Pdf::loadView('reports.pdf', $data)->download("{$filename}.pdf"),
            default => abort(404),
        };
    }
}
