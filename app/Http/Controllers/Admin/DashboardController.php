<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentApplicant;
use App\Models\Payment;
use App\Models\Student;
use App\Services\Admin\Enrollment\ApplicationQuery;
use App\Services\Admin\Enrollment\EnrollmentReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke(ApplicationQuery $applications)
    {
        $schoolYear = app(\App\Services\SchoolYearService::class)->current();

        // Fetch application count by grade level for the enrollment trend area chart
        $gradeCounts = EnrollmentApplicant::select('grade_level', DB::raw('COUNT(*) as total'))
            ->whereNotIn('status', ['draft'])
            ->where('school_year', $schoolYear)
            ->whereNotNull('grade_level')
            ->groupBy('grade_level')
            ->orderBy('grade_level')
            ->pluck('total', 'grade_level');

        // Fetch slot stats if table exists
        $slotStats = [
            'capacity' => 0,
            'enrolled' => 0,
            'available' => 0,
        ];

        if (Schema::hasTable('grade_levels')) {
            $capacity = (int) DB::table('grade_levels')->where('school_year', $schoolYear)->where('is_active', true)->sum('capacity');
            $enrolled = (int) DB::table('grade_levels')->where('school_year', $schoolYear)->where('is_active', true)->sum('enrolled_count');

            // Also add shift capacity if present
            if (Schema::hasTable('grade_shift_slots')) {
                $shiftCapacity = (int) DB::table('grade_shift_slots')->where('school_year', $schoolYear)->where('is_active', true)->sum('capacity');
                $shiftEnrolled = (int) DB::table('grade_shift_slots')->where('school_year', $schoolYear)->where('is_active', true)->sum('enrolled_count');
                $capacity += $shiftCapacity;
                $enrolled += $shiftEnrolled;
            }

            $slotStats['capacity'] = $capacity;
            $slotStats['enrolled'] = $enrolled;
            $slotStats['available'] = max(0, $capacity - $enrolled);
        }

        return view('admin.dashboard', [
            'stats' => $applications->dashboardStats($schoolYear),
            'recent' => $applications->recentApplications(10, $schoolYear),
            'statusBadges' => EnrollmentReviewService::STATUS_BADGES,
            'statusLabels' => EnrollmentReviewService::STATUS_LABELS,
            'pmBadges' => EnrollmentReviewService::PAYMENT_BADGES,
            'gradeCounts' => $gradeCounts,
            'slotStats' => $slotStats,
            'schoolYear' => $schoolYear,
            'dashboardKpis' => $this->kpis($schoolYear),
            'dashboardCharts' => $this->charts($gradeCounts),
            'storageStats' => $this->storageStats(),
        ]);
    }

    private function kpis(?string $schoolYear = null): array
    {
        $appBase = fn () => EnrollmentApplicant::whereNotIn('status', ['draft'])
            ->when($schoolYear, fn ($q) => $q->where('school_year', $schoolYear));

        $total = $appBase()->count();
        $underReview = $appBase()->whereIn('status', ['ready_for_submission', 'pending', 'submitted', 'under_review'])->count();
        $approved = $appBase()->where('status', 'approved')->count();
        $students = Student::query()
            ->when($schoolYear, fn ($q) => $q->where('school_year', $schoolYear))
            ->count();
        $payments = Payment::whereIn('status', ['pending', 'verified'])->count();

        return [
            [
                'key' => 'applications',
                'label' => 'Applications',
                'value' => $total,
                'icon' => 'files',
                'trend' => $this->growthFor($appBase()),
                'sparkline' => $this->monthlySeries($appBase()),
            ],
            [
                'key' => 'review',
                'label' => 'Under Review',
                'value' => $underReview,
                'icon' => 'search-check',
                'trend' => $this->growthFor($appBase()->whereIn('status', ['ready_for_submission', 'pending', 'submitted', 'under_review'])),
                'sparkline' => $this->monthlySeries($appBase()->whereIn('status', ['ready_for_submission', 'pending', 'submitted', 'under_review'])),
            ],
            [
                'key' => 'approved',
                'label' => 'Approved',
                'value' => $approved,
                'icon' => 'badge-check',
                'trend' => $this->growthFor($appBase()->where('status', 'approved')),
                'sparkline' => $this->monthlySeries($appBase()->where('status', 'approved')),
            ],
            [
                'key' => 'students',
                'label' => 'Students',
                'value' => $students,
                'icon' => 'graduation-cap',
                'trend' => $this->growthFor(Student::query()->when($schoolYear, fn ($q) => $q->where('school_year', $schoolYear))),
                'sparkline' => $this->monthlySeries(Student::query()->when($schoolYear, fn ($q) => $q->where('school_year', $schoolYear))),
            ],
            [
                'key' => 'payments',
                'label' => 'Payments',
                'value' => $payments,
                'icon' => 'wallet-cards',
                'trend' => $this->growthFor(Payment::query()),
                'sparkline' => $this->monthlySeries(Payment::query()),
            ],
        ];
    }

    private function charts($gradeCounts): array
    {
        $pendingPayment = EnrollmentApplicant::whereNotIn('status', ['draft'])
            ->where(function ($query) {
                $query->whereDoesntHave('payment')
                    ->orWhereHas('payment', fn ($payment) => $payment->where('status', 'pending'));
            })
            ->count();

        return [
            'enrollmentTrend' => $this->monthLabelsAndData(EnrollmentApplicant::query()->whereNotIn('status', ['draft'])),
            'gradeDistribution' => [
                'labels' => $gradeCounts->keys()->values(),
                'data' => $gradeCounts->values(),
            ],
            'statusDonut' => [
                'labels' => ['Under Review', 'Approved', 'Rejected', 'Pending Payment'],
                'data' => [
                    EnrollmentApplicant::whereIn('status', ['ready_for_submission', 'pending', 'submitted', 'under_review'])->count(),
                    EnrollmentApplicant::where('status', 'approved')->count(),
                    EnrollmentApplicant::where('status', 'rejected')->count(),
                    $pendingPayment,
                ],
            ],
            'paymentTrend' => $this->weeklyPaymentSeries(),
        ];
    }

    private function monthLabelsAndData($query): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $rows = (clone $query)
            ->where('created_at', '>=', $months->first()->copy()->startOfMonth())
            ->get(['created_at'])
            ->groupBy(fn ($row) => $row->created_at?->format('Y-m'));

        return [
            'labels' => $months->map(fn ($month) => $month->format('M'))->values(),
            'data' => $months->map(fn ($month) => $rows->get($month->format('Y-m'), collect())->count())->values(),
        ];
    }

    private function monthlySeries($query): array
    {
        return $this->monthLabelsAndData($query)['data']->all();
    }

    private function weeklyPaymentSeries(): array
    {
        $weeks = collect(range(7, 0))->map(fn ($i) => CarbonImmutable::now()->startOfWeek()->subWeeks($i));
        $rows = Payment::where('created_at', '>=', $weeks->first())
            ->get(['amount', 'created_at'])
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->created_at)->startOfWeek()->format('Y-m-d'));

        return [
            'labels' => $weeks->map(fn ($week) => $week->format('M d'))->values(),
            'data' => $weeks->map(fn ($week) => (float) $rows->get($week->format('Y-m-d'), collect())->sum('amount'))->values(),
        ];
    }

    private function growthFor($query): float
    {
        $now = now();
        $current = (clone $query)->whereBetween('created_at', [$now->copy()->subDays(30), $now])->count();
        $previous = (clone $query)->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();

        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function storageStats(): array
    {
        $totalDisk = disk_total_space(base_path());
        $freeDisk = disk_free_space(base_path());
        $usedDisk = $totalDisk - $freeDisk;

        // Check enrollment documents storage (symlinked or direct)
        $documentsPath = public_path('storage/documents');
        $documentsSize = 0;
        if (is_dir($documentsPath)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($documentsPath, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $documentsSize += $file->getSize();
                }
            }
        }

        $percent = round(($usedDisk / $totalDisk) * 100, 1);

        return [
            'total' => $totalDisk,
            'used' => $usedDisk,
            'free' => $freeDisk,
            'documents' => $documentsSize,
            'percent' => $percent,
            'health' => $percent < 60 ? 'Healthy' : ($percent < 80 ? 'Warning' : 'Critical'),
            'healthColor' => $percent < 60 ? 'emerald' : ($percent < 80 ? 'amber' : 'red'),
        ];
    }
}
