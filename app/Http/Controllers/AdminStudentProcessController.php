<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class AdminStudentProcessController extends Controller
{
    public function accounts(Request $request)
    {
        $statusFilter = $request->input('status_filter', 'all');
        $activeSy = app(\App\Services\SchoolYearService::class)->current();

        $query = Student::with(['applicant.payment', 'studentSection.section', 'applicant.user'])
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('student_number', 'like', "%{$s}%")
                    ->orWhere('school_email', 'like', "%{$s}%")
                    ->orWhereHas('applicant', fn ($a) => $a->where('first_name', 'like', "%{$s}%")
                        ->orWhere('middle_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                    );
            });
        }

        $paymentUserIdsQuery = Payment::whereNotNull('receipt_url')
            ->whereNotIn('receipt_url', ['', '[]', '[""]'])
            ->select('user_id');

        $isCompletedApplicant = function ($a) use ($paymentUserIdsQuery) {
            $a->whereNotNull('student_type')->where('student_type', '!=', '')
                ->whereNotNull('grade_level')->where('grade_level', '!=', '')
                ->whereNotNull('first_name')->where('first_name', '!=', '')
                ->whereNotNull('last_name')->where('last_name', '!=', '')
                ->whereNotNull('gender')->where('gender', '!=', '')
                ->whereNotNull('date_of_birth')
                ->whereNotNull('place_of_birth')->where('place_of_birth', '!=', '')
                ->whereNotNull('religion')->where('religion', '!=', '')
                ->whereNotNull('country')->where('country', '!=', '')
                ->whereNotNull('street_address')->where('street_address', '!=', '')
                ->whereNotNull('mobile_number')->where('mobile_number', '!=', '')
                ->whereNotNull('parent_mobile')->where('parent_mobile', '!=', '')
                ->whereNotNull('emergency_name')->where('emergency_name', '!=', '')
                ->whereNotNull('emergency_relationship')->where('emergency_relationship', '!=', '')
                ->whereNotNull('emergency_phone')->where('emergency_phone', '!=', '')
                ->whereNotNull('photo_2x2_url')->where('photo_2x2_url', '!=', '')
                ->where(function ($sub) {
                    $sub->where('student_type', 'Old')
                        ->orWhere(fn ($s) => $s->whereNotNull('report_card_url')->where('report_card_url', '!=', ''))
                        ->orWhere(fn ($s) => $s->whereNotNull('affidavit_url')->where('affidavit_url', '!=', ''));
                })
                ->whereIn('user_id', $paymentUserIdsQuery);
        };

        $completedScope = function ($q) use ($isCompletedApplicant) {
            $q->whereNotNull('ms_user_id')
                ->where('ms_user_id', '!=', '')
                ->whereNotNull('ms_teams_enrolled_at')
                ->whereHas('applicant', $isCompletedApplicant);
        };

        $pendingScope = function ($q) use ($isCompletedApplicant) {
            $q->where(function ($sub) use ($isCompletedApplicant) {
                $sub->whereNull('ms_user_id')
                    ->orWhere('ms_user_id', '')
                    ->orWhereNull('ms_teams_enrolled_at')
                    ->orWhereDoesntHave('applicant', $isCompletedApplicant);
            });
        };

        if ($statusFilter === 'completed') {
            $query->where($completedScope);
        } elseif ($statusFilter === 'pending') {
            $query->where($pendingScope);
        }

        $students = $query->latest('students.created_at')->paginate(20);

        $totalCount = Student::when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))->count();
        $completedCount = Student::when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy))->where($completedScope)->count();

        $stats = [
            'total' => $totalCount,
            'pending' => $totalCount - $completedCount,
            'completed' => $completedCount,
        ];

        $paginatedUserIds = $students->pluck('applicant.user_id')->filter()->toArray();
        $paymentUserIds = Payment::whereIn('user_id', $paginatedUserIds)
            ->whereNotNull('receipt_url')
            ->whereNotIn('receipt_url', ['', '[]', '[""]'])
            ->pluck('user_id')
            ->toArray();

        return view('admin.students.accounts', compact('students', 'stats', 'statusFilter', 'paymentUserIds'));
    }

    public function documents(Request $request)
    {
        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $query = Student::with(['applicant', 'applicant.user'])
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('student_number', 'like', "%{$s}%")
                    ->orWhereHas('applicant', fn ($a) => $a->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                    );
            });
        }
        $students = $query->paginate(20);

        return view('admin.students.documents', compact('students'));
    }

    public function verification(Request $request)
    {
        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $query = Student::with(['applicant', 'applicant.user'])
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('student_number', 'like', "%{$s}%")
                    ->orWhereHas('applicant', fn ($a) => $a->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                    );
            });
        }

        // Fetch all matching students to perform family-based grouping
        $allStudents = $query->get();

        $groups = [];
        foreach ($allStudents as $student) {
            $applicant = $student->applicant;
            $familyId = ($applicant && $applicant->family_application_id !== null) ? trim($applicant->family_application_id) : '';
            $groupKey = ($familyId !== '') ? 'fam_'.$familyId : 'solo_'.$student->id;

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'family_id' => $familyId,
                    'students' => [],
                ];
            }
            $groups[$groupKey]['students'][] = $student;
        }

        // Paginate the family and student groups
        $groupedCollection = collect(array_values($groups));
        $perPage = 25;
        $page = Paginator::resolveCurrentPage() ?: 1;

        $students = new LengthAwarePaginator(
            $groupedCollection->forPage($page, $perPage),
            $groupedCollection->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return view('admin.students.verification', compact('students'));
    }

    public function promotions(Request $request)
    {
        $activeSy = app(\App\Services\SchoolYearService::class)->current();
        $query = Student::with(['applicant', 'applicant.user'])
            ->when($activeSy && preg_match('/^\d{4}-\d{4}$/', (string) $activeSy), fn ($q) => $q->where('students.school_year', $activeSy));
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('student_number', 'like', "%{$s}%")
                    ->orWhereHas('applicant', fn ($a) => $a->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                    );
            });
        }
        $students = $query->paginate(20);

        return view('admin.students.promotions', compact('students'));
    }

    public function history(Request $request)
    {
        $query = AdminAuditLog::with('user')
            ->whereIn('event', [
                'application_approved',
                'application_status_updated',
                'onboarding_email_resent',
                'section_verified',
                'documents_approved',
                'documents_rejected',
                'document_approved',
                'document_rejected',
                'license_assigned',
                'credentials_sent',
                'credentials_resent',
                'email_renamed',
            ])
            ->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('message', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('event', 'like', "%{$s}%");
            });
        }

        $logs = $query->paginate(15);

        return view('admin.students.history', compact('logs'));
    }
}
