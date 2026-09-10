<x-student-layout title="Subjects">

@php 
    $nowRiyadh = \Carbon\Carbon::now('Asia/Riyadh');
    $todayName = $nowRiyadh->format('l'); // 'Sunday', 'Monday', etc.
    
    // AMIS School Days: Sunday through Thursday
    $schoolDays = [
        'Sunday' => 'Sun',
        'Monday' => 'Mon',
        'Tuesday' => 'Tue',
        'Wednesday' => 'Wed',
        'Thursday' => 'Thu',
    ];
    $isSchoolDay = in_array($todayName, array_keys($schoolDays));

    // Helper to check if a schedule string matches today
    $matchesDay = function (?string $sched, string $dayName) use ($schoolDays): bool {
        if (empty($sched)) return false;
        $dayAbbrev = $schoolDays[$dayName] ?? substr($dayName, 0, 3);
        if (str_contains($sched, 'Sun-Thu') || str_contains($sched, 'Sun–Thu')) {
            return in_array($dayName, ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday']);
        }
        return str_contains($sched, $dayAbbrev);
    };

    // Helper to categorize subject & assign tone
    $getSubjectMeta = function (?string $name) {
        $lower = mb_strtolower((string) $name);
        
        if (str_contains($lower, 'arabic') || str_contains($lower, 'qur') || str_contains($lower, 'islamic') || str_contains($lower, 'shaf') || str_contains($lower, 'hadith') || str_contains($lower, 'fiqh') || str_contains($lower, 'sirah') || str_contains($lower, 'tajweed')) {
            return [
                'category' => 'islamic',
                'category_label' => 'Islamic & Arabic',
                'icon' => 'book-marked',
                'tone_text' => '#047857',
                'tone_bg' => '#ecfdf5',
                'tone_accent' => '#059669',
            ];
        }

        if (str_contains($lower, 'math') || str_contains($lower, 'science') || str_contains($lower, 'english') || str_contains($lower, 'filipino') || str_contains($lower, 'reading') || str_contains($lower, 'language') || str_contains($lower, 'writing') || str_contains($lower, 'araling') || str_contains($lower, 'makabansa') || str_contains($lower, 'ap')) {
            return [
                'category' => 'core',
                'category_label' => 'Core Academic',
                'icon' => str_contains($lower, 'math') ? 'binary' : (str_contains($lower, 'science') ? 'beaker' : 'book-open'),
                'tone_text' => '#0369a1',
                'tone_bg' => '#f0f9ff',
                'tone_accent' => '#0284c7',
            ];
        }

        if (str_contains($lower, 'values') || str_contains($lower, 'gmrc') || str_contains($lower, 'esp') || str_contains($lower, 'character')) {
            return [
                'category' => 'core',
                'category_label' => 'Values Education',
                'icon' => 'heart-handshake',
                'tone_text' => '#4338ca',
                'tone_bg' => '#eef2ff',
                'tone_accent' => '#4f46e5',
            ];
        }

        return [
            'category' => 'specialized',
            'category_label' => 'Specialized Area',
            'icon' => str_contains($lower, 'comp') || str_contains($lower, 'ict') ? 'monitor' : (str_contains($lower, 'pe') || str_contains($lower, 'map') ? 'activity' : 'palette'),
            'tone_text' => '#b45309',
            'tone_bg' => '#fffbeb',
            'tone_accent' => '#d97706',
        ];
    };

    $formatTeacher = function ($name) {
        $name = trim((string) $name) ?: 'Assigned Faculty';
        if ($name === 'Assigned Faculty' || $name === '—') return $name;
        $lower = strtolower($name);
        if (str_starts_with($lower, 'teacher ') || str_starts_with($lower, 'alim ') || str_starts_with($lower, 'ustadz ') || str_starts_with($lower, 'ustadha ')) {
            return ucwords($lower);
        }
        return 'Teacher ' . ucwords($lower);
    };

    $todayCount = $subjects->filter(fn($s) => $matchesDay($s->schedule, $todayName))->count();
@endphp

<style>
    /* Filter Segmented Control Buttons */
    .subj-filter-group {
        display: inline-flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        gap: 4px;
        flex-wrap: wrap;
    }
    .subj-tab-btn {
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
    .subj-tab-btn:hover {
        color: #0f172a !important;
    }
    .subj-tab-btn.active {
        background: #ffffff !important;
        color: #047857 !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        font-weight: 800 !important;
    }

    /* Clean Card Hover */
    .subject-card {
        transition: all 0.2s ease !important;
    }
    .subject-card:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 20px -2px rgba(15, 23, 42, 0.06) !important;
        border-color: #cbd5e1 !important;
    }
</style>

<div class="space-y-5" x-data="{ 
    activeFilter: 'all', 
    searchQuery: '',
    todayDayName: '{{ $todayName }}'
}">

    <!-- 1. Hero Banner (Signature Dark Emerald) -->
    <div style="background: linear-gradient(135deg, #064e3b 0%, #047857 55%, #0d9488 100%); color: #ffffff; padding: 1.25rem 1.75rem; border-radius: 18px; box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.06); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); border: 1.5px solid rgba(255,255,255,0.3); flex-shrink: 0;">
                <i data-lucide="book-open" style="width: 22px; height: 22px; color: #ffffff;"></i>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 900; letter-spacing: 0.01em; text-transform: uppercase; color: #ffffff; line-height: 1.2;">
                    Official Academic Curriculum
                </h2>
                <p style="margin: 3px 0 0 0; font-size: 14px; color: #e6fffa; font-weight: 600;">
                    Registered subjects, teacher directory, and timetables for School Year {{ $student?->school_year ?? '2026-2027' }}.
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <span style="font-size: 13.5px; font-weight: 800; background: rgba(255,255,255,0.2); border: 1.5px solid rgba(255,255,255,0.3); color: #ffffff; padding: 0.35rem 0.85rem; border-radius: 999px;">
                {{ $subjects->count() }} Subjects
            </span>
            <span style="font-size: 13.5px; font-weight: 800; background: rgba(255,255,255,0.15); border: 1.5px solid rgba(255,255,255,0.25); color: #ffffff; padding: 0.35rem 0.85rem; border-radius: 999px;">
                {{ $section?->grade_level ?? ($student?->grade_level ?? 'Grade 1') }} — {{ $section?->name ?? 'Section' }}
            </span>
        </div>
    </div>

    <!-- 2. Controls Bar (Segmented Filter Buttons + Search) -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 0.85rem 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        
        <!-- Filter Buttons Group -->
        <div class="subj-filter-group">
            <button type="button"
                    @click="activeFilter = 'all'"
                    class="subj-tab-btn"
                    :class="activeFilter === 'all' ? 'active' : ''">
                <span>All Subjects</span>
                <span style="font-size: 11px; padding: 2px 6px; border-radius: 6px; background: rgba(0,0,0,0.06);">{{ $subjects->count() }}</span>
            </button>

            <button type="button"
                    @click="activeFilter = 'today'"
                    class="subj-tab-btn"
                    :class="activeFilter === 'today' ? 'active' : ''">
                <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                <span>Today's Classes ({{ substr($todayName, 0, 3) }})</span>
                @if($todayCount > 0)
                    <span style="font-size: 11px; padding: 2px 6px; border-radius: 6px; background: #ecfdf5; color: #047857; font-weight: 800;">{{ $todayCount }}</span>
                @endif
            </button>

            <button type="button"
                    @click="activeFilter = 'core'"
                    class="subj-tab-btn"
                    :class="activeFilter === 'core' ? 'active' : ''">
                <span>Core Academics</span>
            </button>

            <button type="button"
                    @click="activeFilter = 'islamic'"
                    class="subj-tab-btn"
                    :class="activeFilter === 'islamic' ? 'active' : ''">
                <span>Islamic & Arabic</span>
            </button>
        </div>

        <!-- Search Input -->
        <div style="position: relative; min-width: 220px; flex-grow: 1; max-width: 320px;">
            <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: #94a3b8; pointer-events: none;"></i>
            <input type="text" 
                   x-model="searchQuery" 
                   placeholder="Search subjects or teachers..." 
                   style="width: 100%; height: 38px; padding: 0.4rem 0.85rem 0.4rem 2.25rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 13.5px; font-weight: 600; color: #0f172a; outline: none; background: #f8fafc; transition: all 0.15s ease;"
                   @focus="$el.style.borderColor = '#059669'; $el.style.background = '#ffffff';"
                   @blur="$el.style.borderColor = '#cbd5e1'; $el.style.background = '#f8fafc';">
            <button type="button" 
                    x-show="searchQuery.length > 0" 
                    @click="searchQuery = ''" 
                    style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: transparent; cursor: pointer; color: #94a3b8;">
                <i data-lucide="x" style="width: 14px; height: 14px;"></i>
            </button>
        </div>
    </div>

    <!-- 3. Subject Cards Grid -->
    @if($subjects->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($subjects as $subject)
                @php
                    $meta = $getSubjectMeta($subject->subject_name);
                    $tName = $formatTeacher($subject->teacher_name);
                    $tEmail = $subject->teacher_email;
                    $tInitials = collect(explode(' ', $subject->teacher_name))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->join('');
                    $sched = trim((string) $subject->schedule);
                    $isToday = $matchesDay($sched, $todayName);
                    
                    // Grades for this subject
                    $subjectGrades = isset($grades) && isset($grades[$subject->id]) ? $grades[$subject->id] : collect();
                    $latestQuarterGrade = $subjectGrades->whereNotNull('quarter_grade')->last();

                    $searchCorpus = strtolower(addslashes($subject->subject_name . ' ' . $tName . ' ' . $sched . ' ' . $meta['category_label']));
                @endphp

                <div x-show="(activeFilter === 'all' || 
                             (activeFilter === 'today' && {{ $isToday ? 'true' : 'false' }}) || 
                             activeFilter === '{{ $meta['category'] }}') && 
                             (!searchQuery || '{{ $searchCorpus }}'.includes(searchQuery.toLowerCase().trim()))"
                     class="subject-card"
                     style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.35rem 1.5rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; justify-content: space-between; gap: 1rem;">
                    
                    <!-- Top Line: Category Label on left, Schedule on right -->
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                            <span style="font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: {{ $meta['tone_text'] }};">
                                {{ $meta['category_label'] }} · SUB-{{ str_pad($subject->id, 4, '0', STR_PAD_LEFT) }}
                            </span>

                            @if($sched)
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 12.5px; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.65rem; border-radius: 8px;">
                                    <i data-lucide="clock" style="width: 13px; height: 13px; color: #0d9488;"></i>
                                    <span>{{ $sched }}</span>
                                </span>
                            @else
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 12.5px; font-weight: 600; color: #94a3b8; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.65rem; border-radius: 8px;">
                                    <i data-lucide="clock" style="width: 13px; height: 13px;"></i>
                                    <span>To be announced</span>
                                </span>
                            @endif
                        </div>

                        <!-- Main Row: Icon + Title + Grade Level -->
                        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-top: 0.85rem;">
                            <div style="width: 44px; height: 44px; border-radius: 10px; background: {{ $meta['tone_bg'] }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: {{ $meta['tone_accent'] }};">
                                <i data-lucide="{{ $meta['icon'] }}" style="width: 22px; height: 22px;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h3 style="margin: 0; font-size: 17.5px; font-weight: 850; color: #0f172a; line-height: 1.35; letter-spacing: -0.01em;">
                                    {{ $subject->subject_name }}
                                </h3>
                                <p style="margin: 0.25rem 0 0 0; font-size: 13px; font-weight: 600; color: #64748b;">
                                    {{ $section?->grade_level ?? ($student?->grade_level ?? 'Basic Education') }} · {{ $section?->name ?? 'AMIS' }}
                                </p>
                            </div>
                        </div>

                        <!-- Teacher Bar -->
                        <div style="margin-top: 1rem; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; padding: 0.75rem 0.95rem; display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #047857; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; flex-shrink: 0;">
                                {{ $tInitials ?: 'TR' }}
                            </div>
                            <div style="min-width: 0; flex: 1;">
                                <div style="font-size: 0.875rem; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $tName }}
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $tEmail ?: 'Assigned AMIS Faculty' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer: Status & Teams Action -->
                    <div style="border-top: 1px solid #f1f5f9; padding-top: 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                        <div>
                            @if($latestQuarterGrade)
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 12px; font-weight: 800; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.3rem 0.65rem; border-radius: 8px;">
                                    <i data-lucide="award" style="width: 13px; height: 13px;"></i>
                                    <span>Grade: {{ $latestQuarterGrade->quarter_grade }} ({{ $latestQuarterGrade->remarks }})</span>
                                </span>
                            @else
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 12px; font-weight: 700; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.3rem 0.65rem; border-radius: 8px;">
                                    <i data-lucide="clock-3" style="width: 13px; height: 13px;"></i>
                                    <span>Quarter 1 Ongoing</span>
                                </span>
                            @endif
                        </div>

                        <div>
                            @php
                                $joinUrl = $subject->team_url ?? $section?->ms_team_url;
                            @endphp
                            @if($joinUrl)
                                <a href="{{ $joinUrl }}" 
                                   target="_blank" 
                                   style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #ffffff; padding: 0.45rem 1rem; border-radius: 10px; font-weight: 800; font-size: 13px; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; box-shadow: 0 2px 6px rgba(5,150,105,0.2);">
                                    <i data-lucide="video" style="width: 14px; height: 14px;"></i>
                                    <span>Join MS Teams</span>
                                </a>
                            @else
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 12px; font-weight: 700; color: #94a3b8; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.35rem 0.75rem; border-radius: 8px;">
                                    <i data-lucide="video-off" style="width: 13px; height: 13px;"></i>
                                    <span>Teams link pending</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Empty Search Fallback -->
        <div x-cloak
             x-show="searchQuery.length > 0 && Array.from(document.querySelectorAll('.subject-card')).every(el => el.style.display === 'none')"
             style="background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 3rem 2rem; text-align: center;">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: #ecfdf5; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                <i data-lucide="search-x" style="width: 24px; height: 24px; color: #059669;"></i>
            </div>
            <h3 style="font-size: 17px; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem;">No Matching Subjects</h3>
            <p style="font-size: 14px; color: #64748b; margin: 0 auto; max-width: 420px;">
                No subjects matched your search for "<span x-text="searchQuery" style="color: #0f172a; font-weight: 700;"></span>".
            </p>
            <button type="button" @click="searchQuery = ''; activeFilter = 'all'" 
                    style="margin-top: 0.85rem; background: #047857; color: #ffffff; border: none; padding: 0.45rem 1.15rem; border-radius: 8px; font-size: 13.5px; font-weight: 750; cursor: pointer;">
                Clear Search
            </button>
        </div>
    @else
        <!-- Full Empty State -->
        <div style="background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 3.5rem 2rem; text-align: center;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #ecfdf5; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                <i data-lucide="book-x" style="width: 26px; height: 26px; color: #059669;"></i>
            </div>
            <h3 style="font-size: 18px; font-weight: 850; color: #0f172a; margin: 0 0 0.35rem;">No Registered Subjects Found</h3>
            <p style="font-size: 14.5px; color: #64748b; margin: 0 auto; max-width: 420px;">
                Subject assignments for your section are currently being finalized by the academic team.
            </p>
        </div>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) window.lucide.createIcons();
    });
</script>
</x-student-layout>
