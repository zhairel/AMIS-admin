<?php

namespace App\Services\Admin\Enrollment;

use App\Models\EnrollmentApplicant;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Schema;

class ApplicationQuery
{
    public const GRADE_LEVELS = [
        'Kinder 1',
        'Kinder 2',
        'Grade 1',
        'Grade 2',
        'Grade 3',
        'Grade 4',
        'Grade 5',
        'Grade 6',
        'Grade 7',
        'Grade 8',
        'Grade 9',
        'Grade 10',
        'Grade 11',
        'Grade 12',
    ];

    public function dashboardStats(?string $schoolYear = null): array
    {
        $schoolYear ??= app(\App\Services\SchoolYearService::class)->current();

        $applicantQuery = fn () => EnrollmentApplicant::whereNotIn('status', ['draft'])
            ->when($schoolYear, fn ($q) => $q->where('school_year', $schoolYear));

        $studentQuery = fn () => Student::query()
            ->when($schoolYear, fn ($q) => $q->where('school_year', $schoolYear));

        return [
            'total' => $applicantQuery()->count(),
            'pending' => $applicantQuery()->whereIn('status', ['ready_for_submission', 'pending', 'submitted'])->count(),
            'under_review' => $applicantQuery()->where('status', 'under_review')->count(),
            'approved' => $applicantQuery()->where('status', 'approved')->count(),
            'rejected' => $applicantQuery()->where('status', 'rejected')->count(),
            'payments_pending' => Payment::where('status', 'pending')->whereNotNull('receipt_url')->count(),
            'students' => $studentQuery()->count(),
        ];
    }

    public function recentApplications(int $limit = 10, ?string $schoolYear = null): Collection
    {
        $schoolYear ??= app(\App\Services\SchoolYearService::class)->current();

        return EnrollmentApplicant::with('user', 'payment')
            ->whereNotIn('status', ['draft'])
            ->when($schoolYear, fn ($q) => $q->where('school_year', $schoolYear))
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function paginateApplicants(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = EnrollmentApplicant::with('user', 'payment', 'student')
            ->whereNotIn('enrollment_applicants.status', ['draft']);

        $schoolYear = app(\App\Services\SchoolYearService::class)->current();
        if ($schoolYear && preg_match('/^\d{4}-\d{4}$/', (string) $schoolYear)) {
            $query->where('enrollment_applicants.school_year', $schoolYear);
        }

        $this->applyTeacherGradeScope($query, 'enrollment_applicants.grade_level', $request);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $cleanSearch = preg_replace('/\s+/', ' ', $search);
            $searchWithoutCommaClean = preg_replace('/\s+/', ' ', str_replace(',', '', $cleanSearch));

            $query->where(function ($q) use ($cleanSearch, $searchWithoutCommaClean) {
                $q->where('enrollment_applicants.first_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('enrollment_applicants.last_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('enrollment_applicants.middle_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('enrollment_applicants.father_first_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('enrollment_applicants.father_last_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('enrollment_applicants.mother_first_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('enrollment_applicants.mother_last_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('enrollment_applicants.parent_email', 'like', "%{$cleanSearch}%")
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$cleanSearch}%"));

                $q->orWhereRaw("CONCAT(enrollment_applicants.first_name, ' ', enrollment_applicants.last_name) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(enrollment_applicants.last_name, ' ', enrollment_applicants.first_name) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(enrollment_applicants.last_name, ', ', enrollment_applicants.first_name) LIKE ?", ["%{$cleanSearch}%"])
                    ->orWhereRaw("CONCAT(enrollment_applicants.first_name, ' ', COALESCE(enrollment_applicants.middle_name, ''), ' ', enrollment_applicants.last_name) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(enrollment_applicants.first_name, ' ', LEFT(COALESCE(enrollment_applicants.middle_name, ''), 1), '. ', enrollment_applicants.last_name) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(enrollment_applicants.last_name, ' ', enrollment_applicants.first_name, ' ', COALESCE(enrollment_applicants.middle_name, '')) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(enrollment_applicants.last_name, ', ', enrollment_applicants.first_name, ' ', COALESCE(enrollment_applicants.middle_name, '')) LIKE ?", ["%{$cleanSearch}%"])
                    ->orWhereRaw("CONCAT(enrollment_applicants.last_name, ', ', enrollment_applicants.first_name, ' ', LEFT(COALESCE(enrollment_applicants.middle_name, ''), 1), '.') LIKE ?", ["%{$cleanSearch}%"]);

                $tokens = explode(' ', $searchWithoutCommaClean);
                if (count($tokens) > 1) {
                    $q->orWhere(function ($sub) use ($tokens) {
                        $sub->where(function ($inner) use ($tokens) {
                            foreach ($tokens as $token) {
                                $tokenClean = rtrim($token, '.');
                                $inner->where(function ($tokenQ) use ($tokenClean) {
                                    $tokenQ->where('enrollment_applicants.first_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('enrollment_applicants.last_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('enrollment_applicants.father_first_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('enrollment_applicants.father_last_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('enrollment_applicants.mother_first_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('enrollment_applicants.mother_last_name', 'like', "%{$tokenClean}%");

                                    if (strlen($tokenClean) === 1) {
                                        $tokenQ->orWhere('enrollment_applicants.middle_name', 'like', "{$tokenClean}%")
                                            ->orWhere('enrollment_applicants.father_middle_name', 'like', "{$tokenClean}%")
                                            ->orWhere('enrollment_applicants.mother_middle_name', 'like', "{$tokenClean}%");
                                    } else {
                                        $tokenQ->orWhere('enrollment_applicants.middle_name', 'like', "%{$tokenClean}%")
                                            ->orWhere('enrollment_applicants.father_middle_name', 'like', "%{$tokenClean}%")
                                            ->orWhere('enrollment_applicants.mother_middle_name', 'like', "%{$tokenClean}%");
                                    }
                                });
                            }
                        });
                    });
                }
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->status;
            match ($status) {
                'pending_review' => $query->whereIn('enrollment_applicants.status', ['ready_for_submission', 'pending', 'submitted']),
                'under_verification' => $query->whereIn('enrollment_applicants.status', ['under_review', 'pending_verification']),
                default => $query->where('enrollment_applicants.status', $status),
            };
        }

        if ($request->filled('readiness')) {
            $readiness = (string) $request->readiness;
            if ($readiness === 'ready') {
                $query->where('enrollment_applicants.document_statuses->photo_2x2', 'approved')
                    ->where(function ($q) {
                        $q->where('enrollment_applicants.student_type', 'Old')
                            ->orWhere(function ($sub) {
                                $sub->where('enrollment_applicants.student_type', '!=', 'Old')
                                    ->where('enrollment_applicants.document_statuses->birth_cert', 'approved')
                                    ->where(function ($sub2) {
                                        $sub2->where('enrollment_applicants.document_statuses->report_card', 'approved')
                                             ->orWhere('enrollment_applicants.document_statuses->affidavit', 'approved');
                                    });
                            });
                    });
            } elseif ($readiness === 'not_ready') {
                $query->where(function ($q) {
                    $q->where('enrollment_applicants.document_statuses->photo_2x2', '!=', 'approved')
                        ->orWhereNull('enrollment_applicants.document_statuses->photo_2x2')
                        ->orWhere(function ($sub) {
                            $sub->where('enrollment_applicants.student_type', '!=', 'Old')
                                ->where(function ($sub2) {
                                    $sub2->where('enrollment_applicants.document_statuses->birth_cert', '!=', 'approved')
                                         ->orWhereNull('enrollment_applicants.document_statuses->birth_cert')
                                         ->orWhere(function ($sub3) {
                                             $sub3->where(function ($sub4) {
                                                 $sub4->where('enrollment_applicants.document_statuses->report_card', '!=', 'approved')
                                                      ->orWhereNull('enrollment_applicants.document_statuses->report_card');
                                             })
                                             ->where(function ($sub5) {
                                                 $sub5->where('enrollment_applicants.document_statuses->affidavit', '!=', 'approved')
                                                      ->orWhereNull('enrollment_applicants.document_statuses->affidavit');
                                             });
                                         });
                                });
                        });
                });
            }
        }

        if ($request->filled('grade')) {
            $query->where('enrollment_applicants.grade_level', $request->grade);
        }

        if ($request->filled('payment_status')) {
            $paymentStatus = (string) $request->payment_status;
            if ($paymentStatus === 'no_payment') {
                $query->whereDoesntHave('payment');
            } else {
                $query->whereHas('payment', function ($q) use ($paymentStatus) {
                    $q->where('status', $paymentStatus);
                });
            }
        }

        if ($request->filled('inbox_status') && Schema::hasColumn('enrollment_applicants', 'onboarding_email_status')) {
            match ((string) $request->inbox_status) {
                'sent' => $query->where('enrollment_applicants.onboarding_email_status', 'sent'),
                'failed' => $query->where('enrollment_applicants.onboarding_email_status', 'failed'),
                'missing' => $query->where('enrollment_applicants.status', 'approved')
                    ->where(function ($q) {
                        $q->whereNull('enrollment_applicants.onboarding_email_status')
                            ->orWhereIn('enrollment_applicants.onboarding_email_status', [
                                'failed',
                                'missing_recipient',
                                'missing_payment_proof',
                                'disabled',
                            ]);
                    }),
                default => null,
            };
        }

        $query->orderByRaw('CASE WHEN enrollment_applicants.created_at >= ? THEN 0 ELSE 1 END', [now()->subHours(24)]);

        $sort = (string) $request->query('sort', 'number');
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        if ($sort === 'payment') {
            $query->leftJoin('payments', 'payments.enrollment_applicant_id', '=', 'enrollment_applicants.id')
                ->select('enrollment_applicants.*')
                ->orderBy('payments.status', $direction);
        } elseif ($sort === 'applicant') {
            $query->orderBy('enrollment_applicants.last_name', $direction)
                ->orderBy('enrollment_applicants.first_name', $direction);
        } elseif (in_array($sort, ['grade_level', 'status'], true)) {
            $query->orderBy('enrollment_applicants.'.$sort, $direction);
        } else {
            $query->orderBy('enrollment_applicants.created_at', 'desc')
                ->orderBy('enrollment_applicants.id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginateFamilies(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $families = $this->filteredApplicants($request)
            ->get()
            ->groupBy(fn ($applicant) => $this->familyKey($applicant))
            ->map(fn ($children) => $this->familyRow($children))
            ->values();

        $newFamilies = $families->filter(fn ($f) => $f['is_new'] ?? false)
            ->sortByDesc(fn ($f) => $f['latest_created_timestamp'] ?? 0)
            ->values();

        $olderFamilies = $families->filter(fn ($f) => ! ($f['is_new'] ?? false));

        if ($request->filled('sort') && $request->query('sort') !== 'number') {
            $sort = (string) $request->query('sort');
            $desc = $request->query('dir', 'desc') !== 'asc';
            $olderFamilies = $olderFamilies->sortBy(fn ($family) => match ($sort) {
                'parent' => $family['family_label'],
                'children' => $family['children_count'],
                'progress' => $family['approved_count'],
                'payment' => $family['payment_status'],
                'status' => $family['overall_status'],
                default => $family['latest_created_timestamp'] ?? $family['family_no'],
            }, SORT_REGULAR, $desc)->values();
        } else {
            $olderFamilies = $olderFamilies->sortByDesc(fn ($f) => $f['latest_created_timestamp'] ?? $f['family_no'])->values();
        }

        $families = $newFamilies->concat($olderFamilies)->values();

        $page = Paginator::resolveCurrentPage();

        return new Paginator(
            $families->forPage($page, $perPage),
            $families->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );
    }

    public function familyChildren(EnrollmentApplicant $applicant): SupportCollection
    {
        return EnrollmentApplicant::with('user', 'payment')
            ->whereNotIn('status', ['draft'])
            ->get()
            ->filter(fn ($child) => $this->familyKey($child) === $this->familyKey($applicant))
            ->sortBy('id')
            ->values();
    }

    private function filteredApplicants(Request $request)
    {
        $query = EnrollmentApplicant::with('user', 'payment')
            ->whereNotIn('status', ['draft']);

        $schoolYear = app(\App\Services\SchoolYearService::class)->current();
        if ($schoolYear && preg_match('/^\d{4}-\d{4}$/', (string) $schoolYear)) {
            $query->where('school_year', $schoolYear);
        }

        $this->applyTeacherGradeScope($query, 'grade_level', $request);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $cleanSearch = preg_replace('/\s+/', ' ', $search);
            $searchWithoutCommaClean = preg_replace('/\s+/', ' ', str_replace(',', '', $cleanSearch));

            $query->where(function ($q) use ($cleanSearch, $searchWithoutCommaClean) {
                $q->where('first_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('last_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('middle_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('father_first_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('father_last_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('mother_first_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('mother_last_name', 'like', "%{$cleanSearch}%")
                    ->orWhere('parent_email', 'like', "%{$cleanSearch}%")
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$cleanSearch}%"));

                $q->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name) LIKE ?", ["%{$cleanSearch}%"])
                    ->orWhereRaw("CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(first_name, ' ', LEFT(COALESCE(middle_name, ''), 1), '. ', last_name) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(last_name, ' ', first_name, ' ', COALESCE(middle_name, '')) LIKE ?", ["%{$searchWithoutCommaClean}%"])
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, '')) LIKE ?", ["%{$cleanSearch}%"])
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', LEFT(COALESCE(middle_name, ''), 1), '.') LIKE ?", ["%{$cleanSearch}%"]);

                $tokens = explode(' ', $searchWithoutCommaClean);
                if (count($tokens) > 1) {
                    $q->orWhere(function ($sub) use ($tokens) {
                        $sub->where(function ($inner) use ($tokens) {
                            foreach ($tokens as $token) {
                                $tokenClean = rtrim($token, '.');
                                $inner->where(function ($tokenQ) use ($tokenClean) {
                                    $tokenQ->where('first_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('last_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('father_first_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('father_last_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('mother_first_name', 'like', "%{$tokenClean}%")
                                        ->orWhere('mother_last_name', 'like', "%{$tokenClean}%");

                                    if (strlen($tokenClean) === 1) {
                                        $tokenQ->orWhere('middle_name', 'like', "{$tokenClean}%")
                                            ->orWhere('father_middle_name', 'like', "{$tokenClean}%")
                                            ->orWhere('mother_middle_name', 'like', "{$tokenClean}%");
                                    } else {
                                        $tokenQ->orWhere('middle_name', 'like', "%{$tokenClean}%")
                                            ->orWhere('father_middle_name', 'like', "%{$tokenClean}%")
                                            ->orWhere('mother_middle_name', 'like', "%{$tokenClean}%");
                                    }
                                });
                            }
                        });
                    });
                }
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->status;
            match ($status) {
                'pending_review' => $query->whereIn('status', ['ready_for_submission', 'pending', 'submitted']),
                'under_verification' => $query->whereIn('status', ['under_review', 'pending_verification']),
                default => $query->where('status', $status),
            };
        }

        if ($request->filled('grade')) {
            $query->where('grade_level', $request->grade);
        }

        if ($request->filled('learning_mode')) {
            $mode = (string) $request->input('learning_mode');
            if ($mode === 'f2f') {
                $query->where('learning_mode', 'Face-to-Face');
            } elseif ($mode === 'flexible_1st') {
                $query->where('learning_mode', 'like', '%1st Shift%');
            } elseif ($mode === 'flexible_2nd') {
                $query->where('learning_mode', 'like', '%2nd Shift%');
            }
        }

        return $query->orderByDesc('id');
    }

    private function applyTeacherGradeScope($query, string $column, Request $request): void
    {
        $user = $request->user();

        if (! $user?->isTeacherAdminViewer()) {
            return;
        }

        $visibleGrades = $user->adminVisibleGradeLevels();

        if (empty($visibleGrades)) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($request->filled('grade')) {
            $requestedGrade = (string) $request->input('grade');

            if (! in_array($requestedGrade, $visibleGrades, true)) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($column, $requestedGrade);

            return;
        }

        $query->where($column, $visibleGrades[0]);
    }

    private function familyRow(SupportCollection $children): array
    {
        $children = $children->sortBy('id')->values();
        $first = $children->first();
        $familyId = $first->family_application_id ?: $first->id;
        $representative = $children->firstWhere('id', $familyId) ?: $first;
        $approved = $children->where('status', 'approved')->count();
        $rejected = $children->where('status', 'rejected')->count();

        // Fetch family payments directly
        $familyPayments = Payment::where(function ($query) use ($children, $representative) {
            $query->whereIn('enrollment_applicant_id', $children->pluck('id'));
            if ($representative->user_id) {
                $query->orWhere('user_id', $representative->user_id);
            }
        })->get();

        $latestCreated = $children->max('created_at');
        $regTime = $latestCreated ? \Illuminate\Support\Carbon::parse($latestCreated) : null;
        $isNew = $regTime && ($regTime->greaterThanOrEqualTo(now()->subHours(24)) || $regTime->isYesterday() || $regTime->isToday());
        $latestTimestamp = $regTime ? $regTime->timestamp : 0;

        return [
            'family_no' => $familyId,
            'family_label' => $this->familyLabel($representative),
            'parent_name' => $this->parentName($representative),
            'parent_email' => $representative->user->email ?? ($representative->parent_email ?: $representative->email),
            'parent_mobile' => trim(($representative->parent_country_code ? $representative->parent_country_code.' ' : '').($representative->parent_mobile ?? '')),
            'children_count' => $children->count(),
            'approved_count' => $approved,
            'pending_count' => $children->count() - $approved - $rejected,
            'payment_status' => $this->familyPaymentStatus($children, $familyPayments),
            'overall_status' => $rejected > 0 ? 'Rejected' : ($approved === $children->count() ? 'Approved' : 'Pending'),
            'email_sent_at' => $children->max('registry_email_sent_at'),
            'is_new' => $isNew,
            'latest_created_timestamp' => $latestTimestamp,
            'representative' => $representative,
            'children' => $children,
            'family_payments' => $familyPayments,
        ];
    }

    private function familyKey(EnrollmentApplicant $applicant): string
    {
        if ($applicant->family_application_id) {
            return 'family:'.$applicant->family_application_id;
        }

        if ($applicant->user_id) {
            return 'user:'.$applicant->user_id;
        }

        $email = strtolower(trim((string) $applicant->parent_email));
        if ($email !== '') {
            return 'email:'.$email;
        }

        $phone = preg_replace('/\D+/', '', (string) $applicant->parent_mobile);

        return $phone !== '' ? 'phone:'.$phone : 'applicant:'.$applicant->id;
    }

    private function parentName(EnrollmentApplicant $applicant): string
    {
        $mother = trim(($applicant->mother_first_name ?? '').' '.($applicant->mother_middle_name ?? '').' '.($applicant->mother_last_name ?? ''));
        $father = trim(($applicant->father_first_name ?? '').' '.($applicant->father_middle_name ?? '').' '.($applicant->father_last_name ?? ''));

        return $mother ?: ($father ?: 'Parent / Guardian');
    }

    private function familyLabel(EnrollmentApplicant $applicant): string
    {
        $last = $applicant->mother_last_name ?: $applicant->father_last_name ?: $applicant->last_name ?: 'Family';
        $first = $applicant->mother_first_name ?: $applicant->father_first_name ?: strtok($this->parentName($applicant), ' ') ?: 'Guardian';

        return trim($last).', '.trim($first);
    }

    private function familyPaymentStatus(SupportCollection $children, $familyPayments = null): string
    {
        $statuses = $familyPayments ? $familyPayments->pluck('status') : $children->map(fn ($child) => $child->payment->status ?? null);
        if ($statuses->contains('verified')) {
            return 'Paid';
        }
        if ($statuses->contains('pending')) {
            return 'Pending';
        }

        return 'No Payment';
    }
}
