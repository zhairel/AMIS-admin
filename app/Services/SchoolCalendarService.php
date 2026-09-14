<?php

namespace App\Services;

use Carbon\Carbon;

class SchoolCalendarService
{
    /**
     * Get all official calendar activities organized by Year-Month ('YYYY-MM').
     */
    public static function getAllEventsByMonth(): array
    {
        return [
            // June 2026
            '2026-06' => [
                ['month' => 'JUN', 'day' => '8-11', 'category' => 'Training', 'title' => 'School-based INSET for Teachers (Training-Workshop)', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'JUN', 'day' => '13', 'category' => 'Orientation', 'title' => 'General Orientation for Teachers and Staff', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'JUN', 'day' => '14', 'category' => 'Orientation', 'title' => 'Parents’ Orientation (Kindergarten ODL)', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'JUN', 'day' => '15-18', 'category' => 'Orientation', 'title' => 'Parents’ Orientation (Grades 1 to 12 ODL)', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'JUN', 'day' => '21-22', 'category' => 'Orientation', 'title' => 'Students’ Orientation (Academic & Islamic)', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'JUN', 'day' => '23', 'category' => 'Class Resumption', 'title' => 'Regular Class Begins (Homeroom Activities/Diagnostic)', 'badge_class' => 'background:#dcfce7;color:#14532d;'],
                ['month' => 'JUN', 'day' => '30', 'category' => 'Election', 'title' => 'Homeroom Election', 'badge_class' => 'background:#f3e8ff;color:#6b21a8;'],
            ],

            // July 2026
            '2026-07' => [
                ['month' => 'JUL', 'day' => '11', 'category' => 'Assembly', 'title' => 'PTA General Assembly and Election of Officers', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'JUL', 'day' => '18', 'category' => 'Student Activity', 'title' => 'JHS and SHS Get Together', 'badge_class' => 'background:#fce7f3;color:#be185d;'],
                ['month' => 'JUL', 'day' => '19-23', 'category' => 'Summative Test', 'title' => '1st Summative Test (Term 1)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'JUL', 'day' => '20', 'category' => 'International Event', 'title' => '22nd Asia Youth International Model United Nations (AYIMUN) 2026', 'badge_class' => 'background:#dcfce7;color:#14532d;'],
                ['month' => 'JUL', 'day' => '21', 'category' => 'SSC Election', 'title' => 'SSC Filing of Candidacy', 'badge_class' => 'background:#f3e8ff;color:#6b21a8;'],
                ['month' => 'JUL', 'day' => '22-23', 'category' => 'SSC Election', 'title' => 'SSC Campaign Week', 'badge_class' => 'background:#f3e8ff;color:#6b21a8;'],
                ['month' => 'JUL', 'day' => '28', 'category' => 'Safety Drill', 'title' => 'Earthquake Drill (Davao)', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
                ['month' => 'JUL', 'day' => '29', 'category' => 'SSC Election', 'title' => 'SSC Election of Officers', 'badge_class' => 'background:#f3e8ff;color:#6b21a8;'],
                ['month' => 'JUL', 'day' => '30', 'category' => 'SSC Election', 'title' => 'SSC Induction of Officers', 'badge_class' => 'background:#f3e8ff;color:#6b21a8;'],
            ],

            // August 2026
            '2026-08' => [
                ['month' => 'AUG', 'day' => '1', 'category' => 'Special Class', 'title' => 'Special Class', 'badge_class' => 'background:#e0f2fe;color:#0369a1;'],
                ['month' => 'AUG', 'day' => '2-6', 'category' => 'Medical Assessment', 'title' => 'Medical Assessment for Students (F2F)', 'badge_class' => 'background:#ecfdf5;color:#047857;'],
                ['month' => 'AUG', 'day' => '16-20', 'category' => 'Summative Test', 'title' => '2nd Summative Test (Term 1)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'AUG', 'day' => '20-22', 'category' => 'Teacher INSET', 'title' => 'PEAC INSET for JHS Teachers / Asynchronous class for JHS', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'AUG', 'day' => '21', 'category' => 'Holiday', 'title' => 'Special Non-Working Holiday', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
                ['month' => 'AUG', 'day' => '24-26', 'category' => 'Teacher INSET', 'title' => 'PEAC INSET for SHS Teachers / Asynchronous class for SHS', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'AUG', 'day' => '25', 'category' => 'Holiday', 'title' => 'Regular Holiday', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
            ],

            // September 2026 — CURRENT MONTH
            '2026-09' => [
                ['month' => 'SEP', 'day' => '2-3', 'category' => 'Term Exam', 'title' => '1st Term Examination (1st and 2nd Day)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'SEP', 'day' => '6-7', 'category' => 'Term Exam', 'title' => '1st Term Examination (3rd and 4th Day)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'SEP', 'day' => '8-10', 'category' => 'Academic Break', 'title' => 'Wellness Break for Students / Grade Computations for Teachers', 'badge_class' => 'background:#f1f5f9;color:#475569;'],
                ['month' => 'SEP', 'day' => '12', 'category' => 'Wellness Break', 'title' => 'Wellness Break for Teachers', 'badge_class' => 'background:#f1f5f9;color:#475569;'],
                ['month' => 'SEP', 'day' => '13-15', 'category' => 'Islamic Event', 'title' => 'AMIS School-based Musabaqah', 'badge_class' => 'background:#fce7f3;color:#be185d;'],
                ['month' => 'SEP', 'day' => '16-17', 'category' => 'Sports & Culture', 'title' => 'Sports Fest 2026', 'badge_class' => 'background:#ede9fe;color:#5b21b6;'],
                ['month' => 'SEP', 'day' => '19', 'category' => 'Recognition', 'title' => '1st Term Recognition Ceremony', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'SEP', 'day' => '20', 'category' => 'PTC Meeting', 'title' => '1st Term PTC & Viewing of Grades', 'badge_class' => 'background:#fef3c7;color:#92400e;'],
                ['month' => 'SEP', 'day' => '21', 'category' => 'Instructional Block', 'title' => 'Start of Instructional Block (Term 2)', 'badge_class' => 'background:#dcfce7;color:#14532d;'],
            ],

            // October 2026
            '2026-10' => [
                ['month' => 'OCT', 'day' => '3', 'category' => 'Special Class', 'title' => 'Special Class', 'badge_class' => 'background:#e0f2fe;color:#0369a1;'],
                ['month' => 'OCT', 'day' => '11-15', 'category' => 'Summative Test', 'title' => '1st Summative test (Term 2)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'OCT', 'day' => '25-29', 'category' => 'Subject Activities', 'title' => 'English Activities (Subject Area Time Only)', 'badge_class' => 'background:#fce7f3;color:#be185d;'],
            ],

            // November 2026
            '2026-11' => [
                ['month' => 'NOV', 'day' => '1-2', 'category' => 'Academic Break', 'title' => 'Academic Break (Holiday)', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
                ['month' => 'NOV', 'day' => '8-12', 'category' => 'Subject Activities', 'title' => 'Math-Sci Week (Subject Area Time Only)', 'badge_class' => 'background:#ede9fe;color:#5b21b6;'],
                ['month' => 'NOV', 'day' => '14', 'category' => 'Special Class', 'title' => 'Special Class', 'badge_class' => 'background:#e0f2fe;color:#0369a1;'],
                ['month' => 'NOV', 'day' => '15', 'category' => 'DepEd Testing', 'title' => 'PEPT Luzon', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'NOV', 'day' => '16-19', 'category' => 'Summative Test', 'title' => '2nd Summative test (Term 2)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'NOV', 'day' => '22-26', 'category' => 'Accreditation', 'title' => 'PAASCU Visit', 'badge_class' => 'background:#dcfce7;color:#14532d;'],
                ['month' => 'NOV', 'day' => '30', 'category' => 'Holiday', 'title' => 'Bonifacio Day (Regular Holiday)', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
            ],

            // December 2026
            '2026-12' => [
                ['month' => 'DEC', 'day' => '1-3', 'category' => 'Term Exam', 'title' => '2nd Term Examination (1st, 2nd, and 3rd Day)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'DEC', 'day' => '6', 'category' => 'Term Exam', 'title' => '2nd Term Examination (4th Day)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'DEC', 'day' => '7-9', 'category' => 'Academic Break', 'title' => 'Wellness Break for Students / Grade Computations', 'badge_class' => 'background:#f1f5f9;color:#475569;'],
                ['month' => 'DEC', 'day' => '10', 'category' => 'Academic', 'title' => 'Exam Rationalization', 'badge_class' => 'background:#e0f2fe;color:#0369a1;'],
                ['month' => 'DEC', 'day' => '12', 'category' => 'Faculty INSET', 'title' => 'Teachers INSET', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'DEC', 'day' => '13-14', 'category' => 'Competition', 'title' => 'English International Competition', 'badge_class' => 'background:#ede9fe;color:#5b21b6;'],
                ['month' => 'DEC', 'day' => '15-16', 'category' => 'Competition', 'title' => 'Math International Competition', 'badge_class' => 'background:#ede9fe;color:#5b21b6;'],
                ['month' => 'DEC', 'day' => '17', 'category' => 'Recognition', 'title' => '2nd Term Recognition', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'DEC', 'day' => '19', 'category' => 'PTC Meeting', 'title' => '2nd Term PTC & Viewing of Grades', 'badge_class' => 'background:#fef3c7;color:#92400e;'],
                ['month' => 'DEC', 'day' => '20-31', 'category' => 'Holiday Break', 'title' => 'Semestral & Year End Break', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
            ],

            // January 2027
            '2027-01' => [
                ['month' => 'JAN', 'day' => '1', 'category' => 'Holiday', 'title' => 'New Year’s Day (Regular Holiday)', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
                ['month' => 'JAN', 'day' => '4', 'category' => 'Class Resumption', 'title' => 'Resumption of Classes (Instructional Block)', 'badge_class' => 'background:#dcfce7;color:#14532d;'],
                ['month' => 'JAN', 'day' => '16', 'category' => 'Orientation', 'title' => 'Orientation for All Graduating Students', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'JAN', 'day' => '23', 'category' => 'School Event', 'title' => 'Faculty and Staff Pictorial', 'badge_class' => 'background:#fce7f3;color:#be185d;'],
                ['month' => 'JAN', 'day' => '24-28', 'category' => 'Summative Test', 'title' => '1st Summative test (Term 3)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'JAN', 'day' => '29-31', 'category' => 'International Event', 'title' => '3rd AMIS International Qur’an Musabaqah', 'badge_class' => 'background:#ede9fe;color:#5b21b6;'],
                ['month' => 'JAN', 'day' => '31', 'category' => 'Academic', 'title' => 'Asynchronous Class', 'badge_class' => 'background:#e0f2fe;color:#0369a1;'],
            ],

            // February 2027
            '2027-02' => [
                ['month' => 'FEB', 'day' => '2', 'category' => 'Islamic Event', 'title' => 'Pre-Ramadhan Islamic Symposium & Opening of Sadaqah Boxes', 'badge_class' => 'background:#fce7f3;color:#be185d;'],
                ['month' => 'FEB', 'day' => '3-6', 'category' => 'Graduation Event', 'title' => 'Pictorial Week for Graduating Students', 'badge_class' => 'background:#ede9fe;color:#5b21b6;'],
                ['month' => 'FEB', 'day' => '8-9', 'category' => 'Islamic Holiday', 'title' => 'Start of Ramadhan 1448H', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
                ['month' => 'FEB', 'day' => '21-25', 'category' => 'Summative Test', 'title' => '2nd Summative test (Term 3)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'FEB', 'day' => '28', 'category' => 'Ramadhan Break', 'title' => 'Start of Ramadhan Break', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
            ],

            // March 2027
            '2027-03' => [
                ['month' => 'MAR', 'day' => '1-11', 'category' => 'Ramadhan Break', 'title' => 'Ramadhan Break', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
                ['month' => 'MAR', 'day' => '13', 'category' => 'Staff Resumption', 'title' => 'Resumption of Work (Staff Only)', 'badge_class' => 'background:#dbeafe;color:#1e40af;'],
                ['month' => 'MAR', 'day' => '14', 'category' => 'Class Resumption', 'title' => 'Resumption of Classes (PAT ME Celebration)', 'badge_class' => 'background:#dcfce7;color:#14532d;'],
                ['month' => 'MAR', 'day' => '25-26', 'category' => 'Holiday', 'title' => 'Regular Holidays', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
                ['month' => 'MAR', 'day' => '28-31', 'category' => 'Term Exam', 'title' => '3rd Term Examination (Graduating Students)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
            ],

            // April 2027
            '2027-04' => [
                ['month' => 'APR', 'day' => '1', 'category' => 'Examination', 'title' => 'Remedial Examination (Graduating Students)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'APR', 'day' => '4-7', 'category' => 'Term Exam', 'title' => '3rd Term Examination (Non-Graduating Students)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'APR', 'day' => '8', 'category' => 'Examination', 'title' => 'Remedial Examination (Non-Graduating Students)', 'badge_class' => 'background:#fef9c3;color:#854d0e;'],
                ['month' => 'APR', 'day' => '9', 'category' => 'Holiday', 'title' => 'Araw ng Kagitingan (Regular Holiday)', 'badge_class' => 'background:#fee2e2;color:#991b1b;'],
                ['month' => 'APR', 'day' => '11-13', 'category' => 'Clearance', 'title' => 'Compliance with INC Requirements and Clearance', 'badge_class' => 'background:#e0f2fe;color:#0369a1;'],
                ['month' => 'APR', 'day' => '14-15', 'category' => 'Rehearsal', 'title' => 'Rehearsal for EOSY Rites (Davao)', 'badge_class' => 'background:#ede9fe;color:#5b21b6;'],
                ['month' => 'APR', 'day' => '17', 'category' => 'EOSY Recognition', 'title' => 'Year-End Recognition (Davao)', 'badge_class' => 'background:#f3e8ff;color:#6b21a8;'],
                ['month' => 'APR', 'day' => '18', 'category' => 'Graduation', 'title' => 'Graduation and Moving Up Ceremony (Davao)', 'badge_class' => 'background:#dcfce7;color:#14532d;'],
            ],
        ];
    }

    /**
     * Get the schedule for the current or a specific month.
     */
    public static function getCurrentMonthSchedule(?int $year = null, ?int $month = null): array
    {
        $all = self::getAllEventsByMonth();

        $now = now();
        $targetYear = $year ?? (int)$now->format('Y');
        $targetMonth = $month ?? (int)$now->format('n');

        $key = sprintf('%04d-%02d', $targetYear, $targetMonth);

        // If specific key not found, default to current month or first available
        if (!isset($all[$key])) {
            // Check if current real month exists
            $currentRealKey = $now->format('Y-m');
            if (isset($all[$currentRealKey])) {
                $key = $currentRealKey;
                $targetYear = (int)$now->format('Y');
                $targetMonth = (int)$now->format('n');
            } else {
                // Fallback to closest month (e.g. September 2026 if in S.Y. 2026)
                $key = '2026-09';
                $targetYear = 2026;
                $targetMonth = 9;
            }
        }

        $carbonDate = Carbon::createFromDate($targetYear, $targetMonth, 1);
        $monthName = $carbonDate->format('F');
        $monthAbbr = strtoupper($carbonDate->format('M'));

        return [
            'key' => $key,
            'year' => $targetYear,
            'month' => $targetMonth,
            'month_name' => $monthName,
            'month_abbr' => $monthAbbr,
            'title' => "{$monthName} {$targetYear} Schedule & Activities",
            'is_current_month' => ($targetYear === (int)$now->format('Y') && $targetMonth === (int)$now->format('n')),
            'events' => $all[$key] ?? [],
            'all_months' => array_keys($all),
        ];
    }
}
