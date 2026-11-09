<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\CandidateView;
use App\Services\Recruitment\Mrf;
use App\Services\Recruitment\MrfCandidateFunnel;
use App\Services\Recruitment\MrfDepartment;
use App\Services\Recruitment\RecruitmentDatabase;
use Illuminate\Http\Request;

final class PortalController extends Controller
{
    public function candidate()
    {
        return view('recruitment.candidate.index');
    }

    public function admin()
    {
        return view('recruitment.admin.index');
    }

    public function department()
    {
        return view('recruitment.mrf-requestor.index');
    }

    public function portal()
    {
        return view('recruitment.portal.index');
    }

    public function superadmin()
    {
        return view('recruitment.superadmin.index');
    }

    public function form(Request $request)
    {
        if (! $request->session()->has('department')) {
            return redirect('/pages/mrf-requestor/#mrf');
        }

        return view('recruitment.mrf-requestor.index', ['requestorPage' => 'mrf-form']);
    }

    public function candidateRecord(Request $request)
    {
        abort_unless($request->session()->has('admin'), 401, 'Sign in as HR to view or print candidate records.');
        $id = filter_var($request->query('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $candidate = $id ? CandidateView::loadCifCandidate(RecruitmentDatabase::pdo(), $id) : null;
        abort_unless($candidate, 404, 'Candidate record not found.');

        return view('recruitment.admin.index', ['adminPage' => 'cif-record', 'adminCandidate' => $candidate]);
    }

    public function adminMrf(Request $request)
    {
        return $this->mrfRecord($request, 'admin');
    }

    public function departmentMrf(Request $request)
    {
        return $this->mrfRecord($request, 'department');
    }

    private function mrfRecord(Request $request, string $portal)
    {
        abort_unless($request->session()->has($portal), 401, 'Sign in to this portal to view or print manpower requests.');
        $id = filter_var($request->query('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $pdo = RecruitmentDatabase::pdo();
        $record = $id ? Mrf::loadMrf($pdo, $id, $portal === 'department' ? (int) $request->session()->get('department.id') : null) : null;
        abort_unless($record, 404, 'Manpower request not found.');
        if ($portal === 'department') {
            $record = MrfDepartment::departmentMrfRecord($pdo, $record);
            if ((int) $record['id'] !== $id) {
                $parameters = ['id' => $record['id']];
                foreach (['submitted', 'print'] as $key) {
                    if ($request->query->has($key)) {
                        $parameters[$key] = '1';
                    }
                }

                return redirect('/pages/mrf-requestor/mrf.php?'.http_build_query($parameters), 303);
            }

            return view('recruitment.mrf-requestor.index', ['requestorPage' => 'mrf-record', 'requestorRecord' => $record]);
        }

        $record['candidate_funnel'] = MrfCandidateFunnel::cifMrfCandidateFunnel($pdo, (int) $record['id']);

        return view('recruitment.admin.index', ['adminPage' => 'mrf-record', 'adminRecord' => $record]);
    }
}
