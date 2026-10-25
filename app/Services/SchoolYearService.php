<?php

namespace App\Services;

use App\Models\EnrollmentApplicant;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchoolYearService
{
    public const DEFAULT_YEAR = '2026-2027';

    /**
     * Get the active/selected school year.
     */
    public function current(): string
    {
        $requested = request('school_year');
        if (is_string($requested) && $this->isValidFormat($requested)) {
            $cleanRequested = trim($requested);
            $this->set($cleanRequested);

            return $cleanRequested;
        }

        $sessionYear = session('admin_school_year');
        if (is_string($sessionYear) && $this->isValidFormat($sessionYear)) {
            return trim($sessionYear);
        }

        return (string) config('services.school.year', self::DEFAULT_YEAR);
    }

    /**
     * Store active school year in session.
     */
    public function set(string $schoolYear): void
    {
        $clean = trim($schoolYear);
        if ($this->isValidFormat($clean)) {
            session([
                'admin_school_year' => $clean,
                'academic_school_year' => $clean,
            ]);
        }
    }

    /**
     * Retrieve all available school years from DB and sensible defaults.
     *
     * @return array<string>
     */
    public function available(): array
    {
        $defaultCurrent = (string) config('services.school.year', self::DEFAULT_YEAR);

        // Sensible standard range around current
        $curStart = (int) substr($defaultCurrent, 0, 4);
        if ($curStart < 2000) {
            $curStart = 2026;
        }

        $defaults = collect([
            ($curStart + 1).'-'.($curStart + 2), // e.g. 2027-2028
            $defaultCurrent,                     // e.g. 2026-2027
            ($curStart - 1).'-'.($curStart),     // e.g. 2025-2026
            ($curStart - 2).'-'.($curStart - 1), // e.g. 2024-2025
            ($curStart - 3).'-'.($curStart - 2), // e.g. 2023-2024
        ]);

        $years = collect();

        // 1. From enrollment applicants
        if (Schema::hasTable('enrollment_applicants')) {
            try {
                $years = $years->merge(
                    EnrollmentApplicant::query()->distinct()->whereNotNull('school_year')->pluck('school_year')
                );
            } catch (\Throwable) {
            }
        }

        // 2. From students
        if (Schema::hasTable('students')) {
            try {
                $years = $years->merge(
                    Student::query()->distinct()->whereNotNull('school_year')->pluck('school_year')
                );
            } catch (\Throwable) {
            }
        }

        // 3. From school_years table if exists
        if (Schema::hasTable('school_years')) {
            try {
                $years = $years->merge(
                    DB::table('school_years')->whereNotNull('code')->pluck('code')
                );
            } catch (\Throwable) {
            }
        }

        // 4. From class schedules
        if (Schema::hasTable('class_schedules')) {
            try {
                $years = $years->merge(
                    DB::table('class_schedules')->distinct()->whereNotNull('school_year')->pluck('school_year')
                );
            } catch (\Throwable) {
            }
        }

        // 5. From school fees
        if (Schema::hasTable('school_fees')) {
            try {
                $years = $years->merge(
                    DB::table('school_fees')->distinct()->whereNotNull('school_year')->pluck('school_year')
                );
            } catch (\Throwable) {
            }
        }

        return $defaults->merge($years)
            ->filter(fn ($y) => is_string($y) && $this->isValidFormat($y))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function isValidFormat(string $year): bool
    {
        return preg_match('/^\d{4}-\d{4}$/', trim($year)) === 1;
    }
}
