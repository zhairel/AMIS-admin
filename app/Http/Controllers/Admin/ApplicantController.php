<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EnrollmentApplicant;
use App\Models\EnrollmentSetting;
use App\Services\Admin\Enrollment\ApplicationQuery;
use App\Services\Admin\Enrollment\EnrollmentAnalyticsService;
use App\Services\Admin\Enrollment\EnrollmentReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ApplicantController extends Controller
{
    public function __construct(
        private readonly ApplicationQuery $applications,
        private readonly EnrollmentReviewService $reviewService,
        private readonly EnrollmentAnalyticsService $analyticsService,
    ) {}

    public function dashboard(Request $request)
    {
        $schoolYear = app(\App\Services\SchoolYearService::class)->current();
        $gradeCounts = EnrollmentApplicant::select('grade_level', DB::raw('COUNT(*) as total'))
            ->whereNotIn('status', ['draft'])
            ->where('school_year', $schoolYear)
            ->whereNotNull('grade_level')
            ->groupBy('grade_level')
            ->orderBy('grade_level')
            ->pluck('total', 'grade_level');

        $gradeSlots = $this->analyticsService->gradeSlotData($schoolYear, $gradeCounts);
        $shiftSlots = $this->analyticsService->shiftSlotData($schoolYear);
        $demandCounts = $this->analyticsService->learningModeDemandData($schoolYear);

        $slotRows = $this->analyticsService->slotMatrixData($gradeSlots, $shiftSlots, $demandCounts);
        $slotCollections = $slotRows->flatMap(fn ($row) => [$row['face_to_face'], $row['first_shift'], $row['second_shift']]);
        $capacity = (int) $slotCollections->sum('capacity');
        $enrolled = (int) $slotCollections->sum('enrolled');

        $available = max(0, $capacity - $enrolled);
        $utilization = $capacity > 0 ? min(100, round(($enrolled / $capacity) * 100)) : 0;

        return view('admin.applications.dashboard', [
            'schoolYear' => $schoolYear,
            'familiesCount' => $this->applications->paginateFamilies($request, 1)->total(),
            'totalApplications' => EnrollmentApplicant::whereNotIn('status', ['draft'])->where('school_year', $schoolYear)->count(),
            'reviewQueue' => EnrollmentApplicant::whereIn('status', ['ready_for_submission', 'pending', 'submitted', 'under_review'])->where('school_year', $schoolYear)->count(),
            'approvedCount' => EnrollmentApplicant::where('status', 'approved')->where('school_year', $schoolYear)->count(),
            'capacityStats' => compact('capacity', 'enrolled', 'available', 'utilization'),
            'gradeSlots' => $gradeSlots,
            'slotRows' => $slotRows,
            'applicationCharts' => $this->applicationCharts($gradeSlots),
        ]);
    }

    public function index(Request $request)
    {
        return view('admin.applicants.index', $this->registryData($request));
    }

    public function enrollment(Request $request)
    {
        return view('admin.applications.enrollment', $this->registryData($request));
    }

    public function printNoPayment(Request $request)
    {
        $familiesPaginator = $this->applications->paginateFamilies($request, 999999);
        $families = collect($familiesPaginator->items());

        $families = $families->filter(function ($family) {
            return $family['payment_status'] === 'No Payment';
        })->values();

        return view('admin.applications.print-no-payment', [
            'families' => $families,
        ]);
    }

    public function review(Request $request)
    {
        return view('admin.applications.review', $this->applicantData($request));
    }

    public function requirements(Request $request)
    {
        return view('admin.applications.requirements', $this->applicantData($request));
    }

    public function approval(Request $request)
    {
        return view('admin.applications.approval', $this->applicantData($request));
    }

    private function registryData(Request $request): array
    {
        $schoolYear = app(\App\Services\SchoolYearService::class)->current();
        $gradeLevels = $this->visibleGradeLevels($request);
        $teacherGradeScope = $this->teacherCurrentGradeScope($request, $gradeLevels);
        $countQuery = fn () => EnrollmentApplicant::query()
            ->when($schoolYear, fn ($q) => $q->where('school_year', $schoolYear))
            ->when($request->user()?->isTeacherAdminViewer(), function ($query) use ($teacherGradeScope) {
                if ($teacherGradeScope === null) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('grade_level', $teacherGradeScope);
                }
            });

        return [
            'families' => $this->applications->paginateFamilies($request),
            'gradeLevels' => $gradeLevels,
            'statusLabels' => EnrollmentReviewService::STATUS_LABELS,
            'statusBadges' => EnrollmentReviewService::STATUS_BADGES,
            'pmLabels' => EnrollmentReviewService::PAYMENT_LABELS,
            'pmBadges' => EnrollmentReviewService::PAYMENT_BADGES,
            'approvedCount' => $countQuery()->where('status', 'approved')->count(),
            'reviewQueueCount' => $countQuery()->whereIn('status', ['ready_for_submission', 'pending', 'submitted', 'under_review'])->count(),
            'rejectedCount' => $countQuery()->where('status', 'rejected')->count(),
            'duplicateCount' => (function () {
                // Count applicants that share the same first_name + last_name + date_of_birth
                // (read-only — no edits or deletes)
                return EnrollmentApplicant::query()
                    ->whereNotIn('status', ['draft'])
                    ->whereNotNull('date_of_birth')
                    ->selectRaw('LOWER(TRIM(first_name)) as fn, LOWER(TRIM(last_name)) as ln, date_of_birth, COUNT(*) as cnt')
                    ->groupByRaw('LOWER(TRIM(first_name)), LOWER(TRIM(last_name)), date_of_birth')
                    ->havingRaw('COUNT(*) > 1')
                    ->get()
                    ->sum('cnt');
            })(),
        ];
    }

    private function applicantData(Request $request): array
    {
        return [
            'applicants' => $this->applications->paginateApplicants($request, 15),
            'gradeLevels' => $this->visibleGradeLevels($request),
            'statusLabels' => EnrollmentReviewService::STATUS_LABELS,
            'statusBadges' => EnrollmentReviewService::STATUS_BADGES,
            'reviewService' => $this->reviewService,
        ];
    }

    private function visibleGradeLevels(Request $request): array
    {
        return $request->user()?->isTeacherAdminViewer()
            ? $request->user()->adminVisibleGradeLevels()
            : ApplicationQuery::GRADE_LEVELS;
    }

    private function teacherCurrentGradeScope(Request $request, array $gradeLevels): ?string
    {
        if (! $request->user()?->isTeacherAdminViewer()) {
            return null;
        }

        if (empty($gradeLevels)) {
            return null;
        }

        if ($request->filled('grade')) {
            $requestedGrade = (string) $request->input('grade');

            return in_array($requestedGrade, $gradeLevels, true) ? $requestedGrade : null;
        }

        return $gradeLevels[0];
    }

    private function applicationCharts($gradeSlots): array
    {
        $capacity = (int) $gradeSlots->sum('capacity');
        $enrolled = (int) $gradeSlots->sum('enrolled');
        $months = collect(range(5, 0))->map(fn ($i) => CarbonImmutable::now()->startOfMonth()->subMonths($i));
        $rows = EnrollmentApplicant::whereNotIn('status', ['draft'])
            ->where('created_at', '>=', $months->first())
            ->get(['created_at'])
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->created_at)->format('Y-m'));
        $typeCounts = EnrollmentApplicant::select('student_type', DB::raw('COUNT(*) as total'))
            ->whereNotIn('status', ['draft'])
            ->groupBy('student_type')
            ->pluck('total', 'student_type');

        return [
            'capacity' => [
                'series' => [$capacity > 0 ? min(100, round(($enrolled / $capacity) * 100)) : 0],
                'capacity' => $capacity,
                'enrolled' => $enrolled,
            ],
            'gradeCapacity' => [
                'labels' => $gradeSlots->pluck('grade')->values(),
                'enrolled' => $gradeSlots->pluck('enrolled')->values(),
                'available' => $gradeSlots->pluck('available')->values(),
            ],
            'applicationFlow' => [
                'labels' => $months->map(fn ($month) => $month->format('M'))->values(),
                'data' => $months->map(fn ($month) => $rows->get($month->format('Y-m'), collect())->count())->values(),
            ],
            'typeBreakdown' => [
                'labels' => $typeCounts->keys()->map(fn ($type) => strtoupper((string) ($type ?: 'Not Set')))->values(),
                'data' => $typeCounts->values(),
            ],
        ];
    }

    public function show(EnrollmentApplicant $applicant)
    {
        abort_unless(auth()->user()?->canViewAdminGrade($applicant->grade_level), 403);

        if (! auth()->user()?->isViewOnlyAccess() && $applicant->status === 'submitted') {
            $applicant->update(['status' => 'under_review']);
        }

        $applicant->load('user', 'payment', 'student');

        $siblings = $this->scopedSiblingQuery($applicant)
            ->where('id', '!=', $applicant->id)
            ->whereNotIn('status', ['draft'])
            ->get();

        return view('admin.applicants.show', [
            'applicant' => $applicant,
            'siblings' => $siblings,
            'enrollmentSetting' => EnrollmentSetting::current(),
            ...$this->reviewService->detailData($applicant),
        ]);
    }

    public function reviewApplicant(EnrollmentApplicant $applicant)
    {
        if (! auth()->user()?->isViewOnlyAccess() && $applicant->status === 'submitted') {
            $applicant->update(['status' => 'under_review']);
        }

        return redirect()->route('admin.applicants.show', $applicant);
    }

    private function scopedSiblingQuery(EnrollmentApplicant $applicant)
    {
        $query = EnrollmentApplicant::where('user_id', $applicant->user_id);

        if (auth()->user()?->isTeacherAdminViewer()) {
            $query->whereIn('grade_level', auth()->user()->adminVisibleGradeLevels());
        }

        return $query;
    }

    public function emailRegistry(Request $request)
    {
        $validated = $request->validate([
            'recipient_email' => 'required|email',
            'payment_filter' => 'nullable|string|in:all,paid,pending,no_payment',
            'limit_count' => 'nullable|integer|min:1',
            'message_body' => 'nullable|string',
            'fetch_families_only' => 'nullable|boolean',
            'family_no' => 'nullable|integer',
        ]);

        $recipientEmail = $validated['recipient_email'];
        $paymentFilter = $validated['payment_filter'] ?? 'all';
        $limitCount = $validated['limit_count'] ?? null;
        $messageBody = $validated['message_body'] ?? "Assalamualaikum Sir,\n\nHere is the list of enrollment families.\n\n-IT Staff";

        try {
            // Retrieve all families from the query (using a very high perPage limit to avoid pagination)
            $familiesPaginator = $this->applications->paginateFamilies($request, 999999);
            $families = collect($familiesPaginator->items());

            // Apply payment status filter
            if ($paymentFilter !== 'all') {
                $families = $families->filter(function ($family) use ($paymentFilter) {
                    if ($paymentFilter === 'paid') {
                        return $family['payment_status'] === 'Paid';
                    }
                    if ($paymentFilter === 'pending') {
                        return $family['payment_status'] === 'Pending';
                    }
                    if ($paymentFilter === 'no_payment') {
                        return $family['payment_status'] === 'No Payment';
                    }

                    return true;
                })->values();
            }

            // Apply limit count if provided
            if ($limitCount && $limitCount > 0) {
                $families = $families->take($limitCount)->values();
            }

            // Return family list only if requested
            if ($request->boolean('fetch_families_only')) {
                return response()->json([
                    'success' => true,
                    'families' => $families->map(function ($f) {
                        return [
                            'family_no' => $f['family_no'],
                            'family_label' => $f['family_label'],
                        ];
                    })->values(),
                ]);
            }

            // Filter by specific family_no if requested
            if ($request->has('family_no')) {
                $targetNo = $request->integer('family_no');
                $families = $families->filter(function ($f) use ($targetNo) {
                    return $f['family_no'] == $targetNo;
                })->values();
            }

            // Send mail per family
            if ($families->isEmpty()) {
                // If it is a specific family request and not found, we don't send fallback email.
                // Otherwise, send the fallback "No Families Found" email.
                if (! $request->has('family_no')) {
                    Mail::send('emails.applicants-registry', [
                        'messageBody' => $messageBody,
                        'families' => [],
                    ], function ($message) use ($recipientEmail) {
                        $message->to($recipientEmail)
                            ->subject('AMIS Families Registry Report - No Families Found');
                    });
                }
            } else {
                $tempFilesToDelete = [];
                try {
                    foreach ($families as $family) {
                        $attachments = [];
                        $payments = $family['family_payments'] ?? collect();
                        foreach ($payments as $p) {
                            $receipts = $p->receipt_urls ?? [];
                            foreach ($receipts as $idx => $rUrl) {
                                if (blank($rUrl)) {
                                    continue;
                                }

                                // Normalize if it is a full URL
                                if (filter_var($rUrl, FILTER_VALIDATE_URL)) {
                                    $parsedPath = parse_url($rUrl, PHP_URL_PATH);
                                    if (str_starts_with($parsedPath, '/storage/')) {
                                        $rUrlPath = substr($parsedPath, 9);
                                    } else {
                                        $rUrlPath = ltrim($parsedPath, '/');
                                    }
                                } else {
                                    $rUrlPath = ltrim($rUrl, '/');
                                }

                                // Check multiple possible paths to locate the file in local/cPanel environment
                                $searchPaths = [
                                    base_path('../amis_enrollment/storage/app/public/'.$rUrlPath),
                                    base_path('../amis_enrollment/public/storage/'.$rUrlPath),
                                    base_path('../enrollment/storage/app/public/'.$rUrlPath),
                                    base_path('../enrollment/public/storage/'.$rUrlPath),
                                    base_path('../../amis_enrollment/storage/app/public/'.$rUrlPath),
                                    base_path('../../public_html/amis_enrollment/storage/app/public/'.$rUrlPath),
                                    base_path('../../public_html/storage/'.$rUrlPath),
                                    storage_path('app/public/'.$rUrlPath),
                                    public_path('storage/'.$rUrlPath),
                                    public_path($rUrlPath),
                                ];

                                $localPath = null;
                                foreach ($searchPaths as $path) {
                                    if (file_exists($path)) {
                                        $localPath = $path;
                                        break;
                                    }
                                }

                                // Fallback: Download file if it is a URL and not found locally
                                if (! $localPath && filter_var($rUrl, FILTER_VALIDATE_URL)) {
                                    try {
                                        $tempContent = @file_get_contents($rUrl);
                                        if ($tempContent !== false) {
                                            $tempDir = storage_path('app/temp_attachments');
                                            if (! file_exists($tempDir)) {
                                                @mkdir($tempDir, 0755, true);
                                            }
                                            $ext = strtolower(pathinfo(parse_url($rUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
                                            $tempFile = $tempDir.'/'.uniqid('proof_', true).'.'.$ext;
                                            if (@file_put_contents($tempFile, $tempContent) !== false) {
                                                $localPath = $tempFile;
                                                $tempFilesToDelete[] = $tempFile;
                                            }
                                        }
                                    } catch (\Throwable $e) {
                                        \Log::warning('Failed to download receipt from URL: '.$rUrl.' - '.$e->getMessage());
                                    }
                                }

                                if ($localPath) {
                                    $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                                    $mime = match ($ext) {
                                        'pdf' => 'application/pdf',
                                        'png' => 'image/png',
                                        'jpg', 'jpeg' => 'image/jpeg',
                                        'gif' => 'image/gif',
                                        'webp' => 'image/webp',
                                        default => 'application/octet-stream'
                                    };
                                    $appNo = str_pad($family['family_no'], 4, '0', STR_PAD_LEFT);
                                    $filename = 'payment-proof-'.$appNo.(count($receipts) > 1 ? '-'.($idx + 1) : '').'.'.$ext;

                                    $alreadyAdded = false;
                                    foreach ($attachments as $att) {
                                        if ($att['path'] === $localPath) {
                                            $alreadyAdded = true;
                                            break;
                                        }
                                    }

                                    if (! $alreadyAdded) {
                                        $attachments[] = [
                                            'path' => $localPath,
                                            'as' => $filename,
                                            'mime' => $mime,
                                        ];
                                    }
                                }
                            }
                        }

                        $lastName = strtoupper(trim(explode(',', $family['family_label'])[0]));
                        $appNo = str_pad($family['family_no'], 4, '0', STR_PAD_LEFT);
                        $subjectLine = 'AMIS Family Registry Report - '.$lastName.' - Application #'.$appNo;

                        Mail::send('emails.applicants-registry', [
                            'messageBody' => $messageBody,
                            'families' => [$family],
                        ], function ($message) use ($recipientEmail, $subjectLine, $attachments) {
                            $message->to($recipientEmail)
                                ->subject($subjectLine);

                            // Clear reply/thread headers to prevent grouping in Gmail
                            $symfonyMessage = $message->getSymfonyMessage();
                            $headers = $symfonyMessage->getHeaders();
                            $headers->remove('In-Reply-To');
                            $headers->remove('References');
                            $headers->remove('threadId');
                            $headers->remove('reply_message_id');

                            // Add unique header to force separate thread
                            $headers->addTextHeader('X-Entity-Ref-ID', uniqid('amis-', true));

                            foreach ($attachments as $attachment) {
                                $message->attach($attachment['path'], [
                                    'as' => $attachment['as'],
                                    'mime' => $attachment['mime'],
                                ]);
                            }
                        });

                        // Mark email as sent in database
                        foreach ($family['children'] as $child) {
                            $child->update(['registry_email_sent_at' => now()]);
                        }
                    }
                } finally {
                    // Clean up temp files
                    foreach ($tempFilesToDelete as $tempFile) {
                        if (file_exists($tempFile)) {
                            @unlink($tempFile);
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully sent '.count($families).' families registry email report(s) to '.$recipientEmail,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to send registry email: '.$exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: '.$exception->getMessage(),
            ], 500);
        }
    }

    public function updateDiscount(Request $request, EnrollmentApplicant $applicant)
    {
        $this->reviewService->updateDiscount($request, $applicant);

        return back()->with('success', 'Sibling discount override saved.');
    }

    public function destroy(EnrollmentApplicant $applicant)
    {
        abort_unless(auth()->user()?->canReviewEnrollmentApplications(), 403);

        $name = trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? ''));
        $id = $applicant->id;

        $applicant->delete();

        AdminAuditLog::record('applicant_archived', true, "Archived enrollment application #{$id} ({$name}). Retained for 7 days.", [
            'applicant_id' => $id,
            'name' => $name,
        ]);

        return back()->with('success', "Enrollment application #{$id} ({$name}) was moved to Archive. It will be automatically deleted in 7 days.");
    }

    public function archive(Request $request)
    {
        abort_unless(auth()->user()?->canReviewEnrollmentApplications(), 403);

        // Auto-purge applications archived more than 7 days ago
        EnrollmentApplicant::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(7))
            ->get()
            ->each(function ($old) {
                if ($old->student) {
                    if ($old->student->account) {
                        $old->student->account->delete();
                    }
                    $old->student->delete();
                }
                if ($old->payment) {
                    $old->payment->delete();
                }
                $old->forceDelete();
            });

        $query = EnrollmentApplicant::onlyTrashed()
            ->with(['user', 'payment', 'student']);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhere('parent_email', 'like', "%{$search}%");
            });
        }

        $archivedApplicants = $query->orderBy('deleted_at', 'desc')->paginate(25);

        return view('admin.applications.archive', [
            'archivedApplicants' => $archivedApplicants,
            'search' => $search,
        ]);
    }

    public function restore($id)
    {
        abort_unless(auth()->user()?->canReviewEnrollmentApplications(), 403);

        $applicant = EnrollmentApplicant::onlyTrashed()->findOrFail($id);
        $name = trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? ''));
        $applicant->restore();

        AdminAuditLog::record('applicant_restored', true, "Restored enrollment application #{$id} ({$name}) from archive.", [
            'applicant_id' => $id,
            'name' => $name,
        ]);

        return back()->with('success', "Enrollment application #{$id} ({$name}) has been restored successfully.");
    }

    public function forceDelete($id)
    {
        abort_unless(auth()->user()?->canReviewEnrollmentApplications(), 403);

        $applicant = EnrollmentApplicant::onlyTrashed()->findOrFail($id);
        $name = trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? ''));

        if ($applicant->student) {
            if ($applicant->student->account) {
                $applicant->student->account->delete();
            }
            $applicant->student->delete();
        }

        if ($applicant->payment) {
            $applicant->payment->delete();
        }

        $applicant->forceDelete();

        AdminAuditLog::record('applicant_permanently_deleted', true, "Permanently deleted archived application #{$id} ({$name}).", [
            'applicant_id' => $id,
            'name' => $name,
        ]);

        return back()->with('success', "Application #{$id} ({$name}) was permanently deleted.");
    }
}
