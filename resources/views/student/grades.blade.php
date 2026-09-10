<x-student-layout title="Grades">

@php
    $fullName = trim(implode(' ', array_filter([
        $student?->applicant?->first_name,
        $student?->applicant?->middle_name,
        $student?->applicant?->last_name,
    ]))) ?: ($user->name ?? 'Student');

    $initials = collect(explode(' ', $fullName))->map(fn($n) => mb_substr($n, 0, 1))->take(2)->join('');
    $gradeLevel = $section?->grade_level ?? ($student?->grade_level ?: 'Grade 1');
    
    // Clean section name
    $rawSection = $section?->name ?? ($student?->section ?? 'Section');
    $cleanSection = preg_replace('/^(?:G\d+|Grade\s*\d+|K(?:inder)?\s*\d*)\s*[-_:]\s*/i', '', $rawSection);
    $cleanSection = trim(str_replace(['_', '-'], ' ', $cleanSection));
    if (strcasecmp($cleanSection, 'AL MUNAWWARA') === 0 || strcasecmp($cleanSection, 'AL-MUNAWWARA') === 0 || str_contains(strtolower($cleanSection), 'munawwara')) {
        $sectionName = 'Al Munawwara';
    } else {
        $sectionName = ucwords(strtolower($cleanSection)) ?: 'General';
    }

    $currentSchoolYear = $schoolYear ?? ($student?->school_year ?? '2026-2027');
    $lrn = $student?->applicant?->lrn ?: ($student?->student_number ?? '260000');
    $learningMode = $section?->learning_mode ?? ($student?->applicant?->learning_mode ?? 'Flexible Online Learning');

    // Build real grade records from DB
    $gradeRecords = $subjects->map(function ($subj) use ($grades) {
        $subjGrades = $grades->where('section_subject_id', $subj->id);

        $g1 = $subjGrades->first(fn($g) => in_array($g->grading_period, ['1st Quarter', '1st Term', 'Q1']));
        $g2 = $subjGrades->first(fn($g) => in_array($g->grading_period, ['2nd Quarter', '2nd Term', 'Q2']));
        $g3 = $subjGrades->first(fn($g) => in_array($g->grading_period, ['3rd Quarter', '3rd Term', 'Q3']));
        $g4 = $subjGrades->first(fn($g) => in_array($g->grading_period, ['4th Quarter', '4th Term', 'Q4']));

        $q1 = $g1?->quarter_grade;
        $q2 = $g2?->quarter_grade;
        $q3 = $g3?->quarter_grade;
        $q4 = $g4?->quarter_grade;

        $availableScores = array_filter([$q1, $q2, $q3, $q4], fn($v) => !is_null($v));
        $isComplete = count($availableScores) === 4;
        $final = !empty($availableScores) ? round(array_sum($availableScores) / count($availableScores), 1) : null;
        $remarks = !empty($availableScores) ? ($final >= 75 ? 'Passed' : 'Failed') : 'Ongoing';

        return [
            'id' => $subj->id,
            'subject_name' => $subj->subject_name,
            'teacher_name' => $subj->teacher_name ?: 'Assigned Faculty',
            'q1' => $q1,
            'q2' => $q2,
            'q3' => $q3,
            'q4' => $q4,
            'final' => $final,
            'remarks' => $remarks,
            'status' => $isComplete ? 'Completed' : (!empty($availableScores) ? 'In Progress' : 'Ongoing'),
        ];
    });

    // Compute Term Averages across all subjects
    $term1Avg = $gradeRecords->whereNotNull('q1')->isNotEmpty() ? round($gradeRecords->whereNotNull('q1')->avg('q1'), 1) : null;
    $term2Avg = $gradeRecords->whereNotNull('q2')->isNotEmpty() ? round($gradeRecords->whereNotNull('q2')->avg('q2'), 1) : null;
    $term3Avg = $gradeRecords->whereNotNull('q3')->isNotEmpty() ? round($gradeRecords->whereNotNull('q3')->avg('q3'), 1) : null;
    $term4Avg = $gradeRecords->whereNotNull('q4')->isNotEmpty() ? round($gradeRecords->whereNotNull('q4')->avg('q4'), 1) : null;

    $gradedFinals = $gradeRecords->whereNotNull('final');
    $generalAverage = $gradedFinals->isNotEmpty() ? round($gradedFinals->avg('final'), 1) : null;

    $descriptor = function (?float $avg): string {
        if (is_null($avg)) return 'Evaluation In Progress';
        if ($avg >= 90) return 'Outstanding';
        if ($avg >= 85) return 'Very Satisfactory';
        if ($avg >= 80) return 'Satisfactory';
        if ($avg >= 75) return 'Fairly Satisfactory';
        return 'Did Not Meet Expectations';
    };
@endphp

<style>
    /* Filter Segmented Control Buttons */
    .grade-filter-group {
        display: inline-flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        gap: 4px;
        flex-wrap: wrap;
    }
    .grade-tab-btn {
        border: none !important;
        background: transparent !important;
        padding: 0.5rem 1rem !important;
        border-radius: 8px !important;
        font-size: 13.5px !important;
        font-weight: 700 !important;
        color: #64748b !important;
        cursor: pointer !important;
        transition: all 0.15s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.35rem !important;
        white-space: nowrap !important;
    }
    .grade-tab-btn:hover {
        color: #0f172a !important;
    }
    .grade-tab-btn.active {
        background: #ffffff !important;
        color: #047857 !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        font-weight: 800 !important;
    }

    /* Print Stylesheet */
    @media print {
        body {
            background: #ffffff !important;
            color: #000000 !important;
        }
        .no-print, .student-sidebar, header, nav, footer, .student-topbar {
            display: none !important;
        }
        .print-only {
            display: block !important;
        }
        .report-card-doc {
            border: 2px solid #000000 !important;
            box-shadow: none !important;
            padding: 1.5rem !important;
            border-radius: 0 !important;
            width: 100% !important;
        }
        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }
        th, td {
            border: 1px solid #000000 !important;
            padding: 6px 10px !important;
            color: #000000 !important;
        }
    }
</style>

<div class="space-y-5" x-data="{ activeQuarter: 'all' }">

    <!-- 1. Hero Banner (Signature Dark Emerald) -->
    <div class="no-print" style="background: linear-gradient(135deg, #064e3b 0%, #047857 55%, #0d9488 100%); color: #ffffff; padding: 1.25rem 1.75rem; border-radius: 18px; box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.06); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); border: 1.5px solid rgba(255,255,255,0.3); flex-shrink: 0;">
                <i data-lucide="chart-no-axes-combined" style="width: 22px; height: 22px; color: #ffffff;"></i>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 900; letter-spacing: 0.01em; text-transform: uppercase; color: #ffffff; line-height: 1.2;">
                    Scholastic Performance & Report Card
                </h2>
                <p style="margin: 3px 0 0 0; font-size: 14px; color: #e6fffa; font-weight: 600;">
                    Official DepEd quarterly grades and academic evaluation for School Year {{ $currentSchoolYear }}.
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <span style="font-size: 13.5px; font-weight: 800; background: rgba(255,255,255,0.2); border: 1.5px solid rgba(255,255,255,0.3); color: #ffffff; padding: 0.35rem 0.85rem; border-radius: 999px;">
                {{ $gradeLevel }} — {{ $sectionName }}
            </span>
            <span style="font-size: 13.5px; font-weight: 800; background: rgba(255,255,255,0.15); border: 1.5px solid rgba(255,255,255,0.25); color: #ffffff; padding: 0.35rem 0.85rem; border-radius: 999px;">
                LRN: {{ $lrn }}
            </span>
        </div>
    </div>

    <!-- 2. Controls Bar (Segmented Filter Buttons + SY Selector + Print Button) -->
    <div class="no-print" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 0.85rem 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        
        <!-- Filter Buttons Group -->
        <div class="grade-filter-group">
            <button type="button"
                    @click="activeQuarter = 'all'"
                    class="grade-tab-btn"
                    :class="activeQuarter === 'all' ? 'active' : ''">
                <span>All Quarters (SF9)</span>
            </button>

            <button type="button"
                    @click="activeQuarter = 'q1'"
                    class="grade-tab-btn"
                    :class="activeQuarter === 'q1' ? 'active' : ''">
                <span>1st Quarter</span>
            </button>

            <button type="button"
                    @click="activeQuarter = 'q2'"
                    class="grade-tab-btn"
                    :class="activeQuarter === 'q2' ? 'active' : ''">
                <span>2nd Quarter</span>
            </button>

            <button type="button"
                    @click="activeQuarter = 'q3'"
                    class="grade-tab-btn"
                    :class="activeQuarter === 'q3' ? 'active' : ''">
                <span>3rd Quarter</span>
            </button>

            <button type="button"
                    @click="activeQuarter = 'q4'"
                    class="grade-tab-btn"
                    :class="activeQuarter === 'q4' ? 'active' : ''">
                <span>4th Quarter</span>
            </button>
        </div>

        <!-- Action Controls: SY Selector & Print Button -->
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <form method="GET" action="{{ route('student.grades') }}" style="margin: 0;">
                <select name="school_year" 
                        onchange="this.form.submit()" 
                        style="height: 38px; padding: 0 0.85rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 13px; font-weight: 700; color: #0f172a; background: #f8fafc; outline: none; cursor: pointer;">
                    <option value="2026-2027" {{ $currentSchoolYear === '2026-2027' ? 'selected' : '' }}>SY 2026–2027</option>
                    <option value="2025-2026" {{ $currentSchoolYear === '2025-2026' ? 'selected' : '' }}>SY 2025–2026</option>
                </select>
            </form>

            <button type="button" 
                    onclick="window.print()" 
                    style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #ffffff; padding: 0.5rem 1.15rem; border-radius: 10px; font-weight: 800; font-size: 13.5px; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; border: none; box-shadow: 0 2px 6px rgba(5,150,105,0.25);">
                <i data-lucide="printer" style="width: 15px; height: 15px;"></i>
                <span>Print Report Card</span>
            </button>
        </div>
    </div>

    <!-- 3. Performance Summary Metric Cards -->
    <div class="no-print grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: General Average --}}
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem 1.4rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
            <div>
                <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #047857;">General Average</span>
                <div style="font-size: 1.75rem; font-weight: 900; color: #0f172a; margin-top: 0.35rem; line-height: 1.2;">
                    @if($generalAverage)
                        {{ number_format($generalAverage, 1) }}%
                    @else
                        <span style="font-size: 1.35rem; font-weight: 800; color: #94a3b8;">Ongoing</span>
                    @endif
                </div>
            </div>
            <div style="padding-top: 0.65rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
                <span style="color: #64748b; font-weight: 600;">Descriptor:</span>
                <span style="font-weight: 800; color: #047857;">{{ $descriptor($generalAverage) }}</span>
            </div>
        </div>

        {{-- Card 2: 1st Quarter Average --}}
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem 1.4rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
            <div>
                <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #0369a1;">1st Quarter Rating</span>
                <div style="font-size: 1.75rem; font-weight: 900; color: #0f172a; margin-top: 0.35rem; line-height: 1.2;">
                    @if($term1Avg)
                        {{ number_format($term1Avg, 1) }}
                    @else
                        <span style="font-size: 1.35rem; font-weight: 800; color: #94a3b8;">—</span>
                    @endif
                </div>
            </div>
            <div style="padding-top: 0.65rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
                <span style="color: #64748b; font-weight: 600;">Evaluated:</span>
                <span style="font-weight: 800; color: #0369a1;">{{ $gradeRecords->whereNotNull('q1')->count() }} / {{ $gradeRecords->count() }} Subjects</span>
            </div>
        </div>

        {{-- Card 3: Academic Standing --}}
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem 1.4rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
            <div>
                <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #4338ca;">Academic Standing</span>
                <div style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin-top: 0.35rem; line-height: 1.2;">
                    @if($generalAverage)
                        <span style="color: {{ $generalAverage >= 75 ? '#047857' : '#dc2626' }};">
                            {{ $generalAverage >= 75 ? 'Passed' : 'Needs Review' }}
                        </span>
                    @else
                        <span style="color: #475569;">In Good Standing</span>
                    @endif
                </div>
            </div>
            <div style="padding-top: 0.65rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
                <span style="color: #64748b; font-weight: 600;">Passing Mark:</span>
                <span style="font-weight: 800; color: #4338ca;">75.0% & Above</span>
            </div>
        </div>

        {{-- Card 4: Grading Scale Reference --}}
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem 1.4rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
            <div>
                <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #b45309;">DepEd Grading Scale</span>
                <div style="font-size: 12.5px; font-weight: 600; color: #475569; margin-top: 0.35rem; line-height: 1.4;">
                    <div><strong style="color: #0f172a;">90–100:</strong> Outstanding</div>
                    <div><strong style="color: #0f172a;">85–89:</strong> Very Satisfactory</div>
                    <div><strong style="color: #0f172a;">75–84:</strong> Satisfactory</div>
                </div>
            </div>
            <div style="padding-top: 0.65rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: #94a3b8; font-weight: 600;">
                <span>DepEd Order No. 8, s. 2015</span>
            </div>
        </div>
    </div>

    <!-- 4. Official Report Card Document Card -->
    <div class="report-card-doc" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 2rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; gap: 1.75rem;">

        <!-- Official Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; padding-bottom: 1.5rem; border-bottom: 2px solid #0f172a; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 1.15rem;">
                <img src="{{ asset('images/AMIS_Logo.png') }}" alt="AMIS Logo" style="width: 58px; height: 58px; object-fit: contain; flex-shrink: 0;">
                <div>
                    <div class="arabic-school-title" style="font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important; font-size: 1.5rem; font-weight: 700; color: #047857; line-height: 1.1; margin-bottom: 0.2rem; direction: rtl; text-align: left;" dir="rtl" lang="ar">
                        المدرسة الإسلامية المنورة
                    </div>
                    <h2 style="font-family: 'Plus Jakarta Sans', sans-serif !important; font-size: 1.2rem; font-weight: 900; color: #0f172a; margin: 0; line-height: 1.2;">
                        AL MUNAWWARA ISLAMIC SCHOOL
                    </h2>
                    <p style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin: 0.2rem 0 0 0;">
                        Official Student Progress Report Card (DepEd Form 138 / SF9)
                    </p>
                </div>
            </div>

            <div>
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 0.5rem 1rem; text-align: right;">
                    <p style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #047857; margin: 0;">DepEd Recognized</p>
                    <p style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin: 2px 0 0 0;">K to 12 Basic Education</p>
                </div>
            </div>
        </div>

        <!-- Student Information Banner -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem 1.25rem;">
            <div>
                <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; display: block;">Learner Name</span>
                <span style="font-size: 0.95rem; font-weight: 900; color: #0f172a;">{{ $fullName }}</span>
            </div>
            <div>
                <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; display: block;">Learner Reference No. (LRN)</span>
                <span style="font-size: 0.95rem; font-weight: 800; color: #0f172a; font-family: monospace;">{{ $lrn }}</span>
            </div>
            <div>
                <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; display: block;">Grade & Section</span>
                <span style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">{{ $gradeLevel }} — {{ $sectionName }}</span>
            </div>
            <div>
                <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; display: block;">Curriculum Year</span>
                <span style="font-size: 0.95rem; font-weight: 800; color: #059669;">SY {{ $currentSchoolYear }}</span>
            </div>
        </div>

        <!-- Scholastic Grades Table -->
        <div>
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.85rem; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #ecfdf5; display: flex; align-items: center; justify-content: center; color: #059669;">
                        <i data-lucide="award" style="width: 18px; height: 18px;"></i>
                    </div>
                    <h3 style="font-size: 1.1rem; font-weight: 850; color: #0f172a; margin: 0;">Periodic Academic Achievement</h3>
                </div>
                <span style="font-size: 12px; font-weight: 600; color: #64748b;">
                    * Confirmed and published grading records
                </span>
            </div>

            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 14px;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; font-size: 0.75rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">
                            <th style="padding: 0.85rem 1.25rem; min-width: 220px;">Learning Area / Subject</th>
                            <th style="padding: 0.85rem 1rem; min-width: 160px;">Assigned Teacher</th>
                            <th style="padding: 0.85rem 0.65rem; text-align: center; width: 75px;" :style="activeQuarter === 'q1' ? 'background: #ecfdf5; color: #047857;' : ''">Q1</th>
                            <th style="padding: 0.85rem 0.65rem; text-align: center; width: 75px;" :style="activeQuarter === 'q2' ? 'background: #ecfdf5; color: #047857;' : ''">Q2</th>
                            <th style="padding: 0.85rem 0.65rem; text-align: center; width: 75px;" :style="activeQuarter === 'q3' ? 'background: #ecfdf5; color: #047857;' : ''">Q3</th>
                            <th style="padding: 0.85rem 0.65rem; text-align: center; width: 75px;" :style="activeQuarter === 'q4' ? 'background: #ecfdf5; color: #047857;' : ''">Q4</th>
                            <th style="padding: 0.85rem 0.85rem; text-align: center; width: 95px;">Final</th>
                            <th style="padding: 0.85rem 1.15rem; text-align: center; width: 105px;">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($gradeRecords as $row)
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 0.95rem 1.25rem; font-weight: 800; color: #0f172a;">
                                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; flex-shrink: 0;"></span>
                                        <span>{{ $row['subject_name'] }}</span>
                                    </div>
                                </td>
                                <td style="padding: 0.95rem 1rem; font-weight: 600; color: #475569; font-size: 0.825rem;">
                                    {{ $row['teacher_name'] }}
                                </td>
                                <td style="padding: 0.95rem 0.65rem; text-align: center; font-weight: 900; color: {{ $row['q1'] ? '#047857' : '#94a3b8' }}; font-size: {{ $row['q1'] ? '0.95rem' : '0.85rem' }};"
                                    :style="activeQuarter === 'q1' ? 'background: #f0fdf4;' : ''">
                                    {{ $row['q1'] ?? '—' }}
                                </td>
                                <td style="padding: 0.95rem 0.65rem; text-align: center; font-weight: 900; color: {{ $row['q2'] ? '#047857' : '#94a3b8' }}; font-size: {{ $row['q2'] ? '0.95rem' : '0.85rem' }};"
                                    :style="activeQuarter === 'q2' ? 'background: #f0fdf4;' : ''">
                                    {{ $row['q2'] ?? '—' }}
                                </td>
                                <td style="padding: 0.95rem 0.65rem; text-align: center; font-weight: 900; color: {{ $row['q3'] ? '#047857' : '#94a3b8' }}; font-size: {{ $row['q3'] ? '0.95rem' : '0.85rem' }};"
                                    :style="activeQuarter === 'q3' ? 'background: #f0fdf4;' : ''">
                                    {{ $row['q3'] ?? '—' }}
                                </td>
                                <td style="padding: 0.95rem 0.65rem; text-align: center; font-weight: 900; color: {{ $row['q4'] ? '#047857' : '#94a3b8' }}; font-size: {{ $row['q4'] ? '0.95rem' : '0.85rem' }};"
                                    :style="activeQuarter === 'q4' ? 'background: #f0fdf4;' : ''">
                                    {{ $row['q4'] ?? '—' }}
                                </td>
                                <td style="padding: 0.95rem 0.85rem; text-align: center; font-weight: 900; color: #0f172a;">
                                    @if($row['final'])
                                        <span style="color: #047857; font-weight: 950; font-size: 0.95rem;">
                                            {{ number_format($row['final'], 1) }}
                                        </span>
                                    @else
                                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 0.2rem 0.55rem; border-radius: 6px;">Ongoing</span>
                                    @endif
                                </td>
                                <td style="padding: 0.95rem 1.15rem; text-align: center;">
                                    @if($row['final'])
                                        <span style="font-size: 0.72rem; font-weight: 800; color: {{ $row['final'] >= 75 ? '#047857' : '#dc2626' }}; background: {{ $row['final'] >= 75 ? '#ecfdf5' : '#fef2f2' }}; border: 1px solid {{ $row['final'] >= 75 ? '#a7f3d0' : '#fecaca' }}; padding: 0.25rem 0.65rem; border-radius: 999px; text-transform: uppercase;">
                                            {{ $row['remarks'] }}
                                        </span>
                                    @else
                                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 0.2rem 0.55rem; border-radius: 6px;">
                                            Ongoing
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 3rem 1.5rem; color: #64748b; font-weight: 600;">
                                    No subjects currently registered for grading evaluation.
                                </td>
                            </tr>
                        @endforelse

                        {{-- General Average Summary Row --}}
                        <tr style="background: #f8fafc; border-top: 2px solid #e2e8f0; font-weight: 900;">
                            <td colspan="2" style="padding: 1.1rem 1.25rem; color: #0f172a; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.04em;">
                                General Average
                            </td>
                            <td style="padding: 1.1rem 0.65rem; text-align: center; color: #047857; font-size: 1.05rem; font-weight: 950;"
                                :style="activeQuarter === 'q1' ? 'background: #f0fdf4;' : ''">
                                {{ $term1Avg ? number_format($term1Avg, 1) : '—' }}
                            </td>
                            <td style="padding: 1.1rem 0.65rem; text-align: center; color: {{ $term2Avg ? '#047857' : '#94a3b8' }}; font-size: {{ $term2Avg ? '1.05rem' : '0.85rem' }}; font-weight: {{ $term2Avg ? '950' : '600' }};"
                                :style="activeQuarter === 'q2' ? 'background: #f0fdf4;' : ''">
                                {{ $term2Avg ? number_format($term2Avg, 1) : '—' }}
                            </td>
                            <td style="padding: 1.1rem 0.65rem; text-align: center; color: {{ $term3Avg ? '#047857' : '#94a3b8' }}; font-size: {{ $term3Avg ? '1.05rem' : '0.85rem' }}; font-weight: {{ $term3Avg ? '950' : '600' }};"
                                :style="activeQuarter === 'q3' ? 'background: #f0fdf4;' : ''">
                                {{ $term3Avg ? number_format($term3Avg, 1) : '—' }}
                            </td>
                            <td style="padding: 1.1rem 0.65rem; text-align: center; color: {{ $term4Avg ? '#047857' : '#94a3b8' }}; font-size: {{ $term4Avg ? '1.05rem' : '0.85rem' }}; font-weight: {{ $term4Avg ? '950' : '600' }};"
                                :style="activeQuarter === 'q4' ? 'background: #f0fdf4;' : ''">
                                {{ $term4Avg ? number_format($term4Avg, 1) : '—' }}
                            </td>
                            <td style="padding: 1.1rem 0.85rem; text-align: center; color: #047857; font-size: 1.1rem; font-weight: 950;">
                                {{ $generalAverage ? number_format($generalAverage, 1) : '—' }}
                            </td>
                            <td style="padding: 1.1rem 1.15rem; text-align: center;">
                                @if($generalAverage)
                                    <span style="font-size: 0.75rem; font-weight: 900; color: {{ $generalAverage >= 75 ? '#047857' : '#dc2626' }}; background: {{ $generalAverage >= 75 ? '#ecfdf5' : '#fef2f2' }}; border: 1px solid {{ $generalAverage >= 75 ? '#a7f3d0' : '#fecaca' }}; padding: 0.25rem 0.75rem; border-radius: 999px; text-transform: uppercase;">
                                        {{ $generalAverage >= 75 ? 'PASSED' : 'FAILED' }}
                                    </span>
                                @else
                                    <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 0.2rem 0.55rem; border-radius: 6px;">
                                        ONGOING
                                    </span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Official Signatures (Form 138 footer) -->
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; text-align: center;">
            <div>
                <div style="height: 40px;"></div>
                <div style="border-bottom: 1.5px solid #0f172a; width: 80%; margin: 0 auto 0.4rem;"></div>
                <span style="font-size: 0.85rem; font-weight: 850; color: #0f172a; display: block; text-transform: uppercase;">Class Adviser</span>
                <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">Al Munawwara Islamic School</span>
            </div>
            <div>
                <div style="height: 40px;"></div>
                <div style="border-bottom: 1.5px solid #0f172a; width: 80%; margin: 0 auto 0.4rem;"></div>
                <span style="font-size: 0.85rem; font-weight: 850; color: #0f172a; display: block; text-transform: uppercase;">School Principal</span>
                <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">Al Munawwara Islamic School</span>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) window.lucide.createIcons();
    });
</script>
</x-student-layout>
