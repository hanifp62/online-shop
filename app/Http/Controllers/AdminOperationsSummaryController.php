<?php

namespace App\Http\Controllers;

use App\Services\OperationalMetrics;
use App\Support\AdminReadPageProps;
use App\Support\FrontendPage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AdminOperationsSummaryController extends Controller
{
    public function index(Request $request, OperationalMetrics $metrics): Response
    {
        $asOf = CarbonImmutable::now('UTC');
        $validator = Validator::make($request->query(), [
            'period' => ['sometimes', 'required', 'string', 'in:today,7d,30d,custom'],
            'from' => ['required_if:period,custom', 'nullable', 'string', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'string', 'date_format:Y-m-d'],
        ]);
        $period = $request->query('period', '7d');
        if (! $validator->fails() && $period === 'custom') {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', $request->query('from'), 'UTC');
            $toExclusive = CarbonImmutable::createFromFormat('!Y-m-d', $request->query('to'), 'UTC')->addDay();
            if ($from->greaterThanOrEqualTo($toExclusive) || $from->addDays(366)->lessThan($toExclusive)) {
                $validator->errors()->add('to', 'Pilih rentang tanggal berurutan, maksimal 366 hari.');
            }
        }
        $filters = [
            'period' => is_string($period) && in_array($period, ['today', '7d', '30d', 'custom'], true) ? $period : '7d',
            'from' => is_string($request->query('from')) ? $request->query('from') : '',
            'to' => is_string($request->query('to')) ? $request->query('to') : '',
        ];
        if ($validator->errors()->isNotEmpty()) {
            $errors = $validator->errors()->all();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Periksa rentang tanggal UTC.', 'errors' => $validator->errors()], 422)->header('Cache-Control', 'no-store');
            }

            return $this->page($request, null, $filters, 422, $errors);
        }
        if ($period !== 'custom') {
            $from = $asOf->startOfDay()->subDays(match ($period) {
                'today' => 0, '30d' => 29, default => 6,
            });
            $toExclusive = $asOf->addDay()->startOfDay();
        }
        $report = $metrics->summary($from, $toExclusive, $asOf);
        $report['window']['period'] = $period;
        $status = $report['summary']['main_source_available'] ? 200 : 503;
        if ($status === 503) {
            $report['message'] = 'Ringkasan belum tersedia. Coba segarkan kembali; sumber data utama belum dapat diperiksa.';
        }
        if ($request->expectsJson()) {
            return response()->json($report, $status)->header('Cache-Control', 'no-store');
        }

        return $this->page($request, $report, $filters, $status);
    }

    private function page(Request $request, ?array $report, array $filters, int $status, array $validationErrors = []): Response
    {
        $page = FrontendPage::render('Admin/OperationsSummary', 'admin.operations-summary',
            compact('report', 'filters', 'validationErrors'), AdminReadPageProps::operations($report, $filters, $validationErrors));
        $response = $page instanceof \Inertia\Response ? $page->toResponse($request) : response($page);

        return $response->setStatusCode($status)->header('Cache-Control', 'no-store');
    }
}
