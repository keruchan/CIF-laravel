<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\RecruitmentReports;
use App\Services\Recruitment\ReportWorkbook;
use App\Services\Recruitment\Security;
use Illuminate\Http\Request;

final class ReportsController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! $request->session()->has('admin')) {
            return response()->json(['error' => 'Please sign in to HR Recruitment to continue.'], 401);
        }
        if (! $request->isMethod('post')) {
            return response()->json(['error' => 'Method not allowed.'], 405, ['Allow' => 'POST']);
        }
        $pdo = null;
        try {
            $input = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                throw new \InvalidArgumentException('Choose valid report options.');
            }
            $format = $input['format'] ?? 'xlsx';
            if (! in_array($format, ['xlsx', 'print'], true)) {
                throw new \InvalidArgumentException('Choose Excel or Print.');
            }
            $pdo = RecruitmentDatabase::pdo();
            $filters = RecruitmentReports::cifReportFilters($input, (int) $request->session()->get('admin.id'));
            $pdo->beginTransaction();
            $sheets = RecruitmentReports::cifRecruitmentReports($pdo, $filters, $request->session()->get('admin.name'));
            $pdo->commit();
            $result = $format === 'xlsx' ? ReportWorkbook::cifReportWorkbook($sheets) : ReportWorkbook::cifReportPrint($sheets);
            Security::cifAuditWrite($pdo, 'Reports', $format === 'xlsx' ? 'Excel report downloaded' : 'Print report prepared', 'Report', '', implode(', ', $filters['reports']), [
                'filters' => $filters, 'worksheets' => array_map(static fn ($sheet) => ['name' => $sheet['name'], 'rows' => count($sheet['rows'])], $sheets),
            ]);
            $headers = ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];
            if ($format === 'xlsx') {
                $headers['Content-Type'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                $headers['Content-Disposition'] = 'attachment; filename="APEC-Homes-Reports-'.now('Asia/Manila')->format('Y-m-d').'.xlsx"';
            } else {
                $headers['Content-Type'] = 'text/html; charset=utf-8';
            }

            return response($result, 200, $headers);
        } catch (\InvalidArgumentException|\JsonException $error) {
            if ($pdo?->inTransaction()) {
                $pdo->rollBack();
            }

            return response()->json(['error' => $error->getMessage()], 422);
        } catch (\Throwable $error) {
            if ($pdo?->inTransaction()) {
                $pdo->rollBack();
            }
            report($error);

            return response()->json(['error' => 'Unable to prepare the report. Please try again or contact your administrator.'], 500);
        }
    }
}
