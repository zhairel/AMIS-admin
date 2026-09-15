<x-admin-layout title="Section Master Lists">
    <div class="space-y-6">

        <!-- Top Navigation Submenu & School Year Selector -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.master-lists.f2f.index', ['school_year' => $schoolYear]) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider bg-white text-slate-700 border border-slate-250 hover:bg-slate-50 transition shadow-2xs">
                    <i data-lucide="users" class="w-4 h-4 text-slate-500"></i>
                    <span>Face-to-Face Lists</span>
                </a>
                <a href="{{ route('admin.master-lists.sections.index', ['school_year' => $schoolYear]) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider bg-emerald-700 text-white shadow-xs">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    <span>Section Lists</span>
                </a>
            </div>

            <!-- School Year Selector -->
            <form method="GET" action="{{ route('admin.master-lists.sections.index') }}" class="flex items-center gap-2.5">
                <label for="sy-select" class="text-xs font-bold text-slate-600 uppercase tracking-wide">School Year:</label>
                <select id="sy-select" name="school_year" onchange="this.form.submit()" 
                        class="text-xs font-bold bg-white border border-slate-250 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-slate-800 shadow-2xs cursor-pointer">
                    <option value="2026-2027" {{ $schoolYear === '2026-2027' ? 'selected' : '' }}>SY 2026–2027</option>
                    <option value="2025-2026" {{ $schoolYear === '2025-2026' ? 'selected' : '' }}>SY 2025–2026</option>
                    <option value="2027-2028" {{ $schoolYear === '2027-2028' ? 'selected' : '' }}>SY 2027–2028</option>
                </select>
            </form>
        </div>

        <!-- Hero Command Banner -->
        <div class="relative overflow-hidden p-6 md:p-8 bg-gradient-to-r from-emerald-850 via-emerald-800 to-teal-900 rounded-3xl border border-emerald-700/30 text-white shadow-xs">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 text-xs font-bold bg-emerald-500/20 text-emerald-250 rounded-full border border-emerald-400/25 mb-3">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        Section-Based Classes
                    </div>
                    <h1 class="text-2xl md:text-3xl font-black tracking-tight text-white">Section Master Lists</h1>
                    <p class="mt-2 text-sm text-emerald-100/90 max-w-2xl font-normal leading-relaxed">
                        Classes organized by learning modality and grade level. Click any section to inspect its student roster, assigned class adviser, linked subjects, and roster lock status.
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <div class="bg-white/10 backdrop-blur-xs px-4 py-3 rounded-2xl border border-white/15 text-center">
                        <div class="text-2xl font-black text-white">
                            {{ $sectionsByModality->flatten()->count() }}
                        </div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-200">Total Sections</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-xs px-4 py-3 rounded-2xl border border-white/15 text-center">
                        <div class="text-2xl font-black text-emerald-300">
                            {{ $sectionsByModality->flatten()->sum('students_count') }}
                        </div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-200">Enrolled Students</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modality Groups -->
        @foreach($sectionsByModality as $modalityName => $modalitySections)
            <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-150">
                    <div class="flex items-center gap-2.5">
                        <span class="w-3 h-3 rounded-full {{ str_contains(strtolower($modalityName), 'face') ? 'bg-emerald-600' : 'bg-sky-600' }} inline-block"></span>
                        <h2 class="text-lg font-black text-slate-900">{{ $modalityName }}</h2>
                        <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">
                            {{ $modalitySections->count() }} {{ $modalitySections->count() === 1 ? 'Section' : 'Sections' }}
                        </span>
                    </div>
                    <span class="text-xs font-black text-emerald-800 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                        {{ $modalitySections->sum('students_count') }} Total Learners
                    </span>
                </div>

                <!-- Sections Grouped by Grade within Modality -->
                @php
                    $groupedByGrade = $modalitySections->groupBy('grade_level');
                @endphp

                <div class="space-y-6">
                    @foreach($groupedByGrade as $gradeLevel => $secList)
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-xs font-black uppercase tracking-wider text-slate-700 bg-slate-100 px-2.5 py-0.5 rounded-md">
                                    {{ $gradeLevel ?: 'Unspecified Grade' }}
                                </span>
                                <div class="h-px bg-slate-200 flex-1"></div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($secList as $sec)
                                    @php
                                        $adviserName = $sec->resolved_adviser?->teacher_name ?: ($sec->grade_advisor?->teacher_name ?? 'Assigned Faculty');
                                        $cleanAdv = ucwords(strtolower(str_ireplace('TEACHER ', '', $adviserName)));
                                        $isLocked = $sec->lock_state?->status === 'locked';
                                    @endphp
                                    <div class="rounded-2xl border border-slate-250 bg-white hover:border-emerald-400 p-5 shadow-2xs transition group flex flex-col justify-between">
                                        <div>
                                            <!-- Section Header & Lock Status -->
                                            <div class="flex items-center justify-between gap-2 mb-2">
                                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                                    {{ $sec->shift ?: 'Regular Shift' }} &bull; {{ ucfirst($sec->gender ?: 'Co-ed') }}
                                                </span>
                                                @if($isLocked)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-700 border border-slate-250">
                                                        <svg class="w-2.5 h-2.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                                                            <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-width="2.5"/>
                                                        </svg>
                                                        Locked
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-250">
                                                        <svg class="w-2.5 h-2.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                                                            <path d="M7 11V7a5 5 0 0 1 9.9-1" stroke-width="2.5"/>
                                                        </svg>
                                                        Draft
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Section Name -->
                                            <div class="flex items-baseline justify-between gap-2 mb-3">
                                                <h3 class="text-base font-black text-slate-900 group-hover:text-emerald-750 transition">
                                                    {{ $sec->name }}
                                                </h3>
                                                <span class="text-xs font-black text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md shrink-0">
                                                    {{ $sec->students_count }} {{ $sec->students_count === 1 ? 'Learner' : 'Learners' }}
                                                </span>
                                            </div>

                                            <!-- Adviser -->
                                            <div class="flex items-center gap-2 text-xs text-slate-600 mb-4 bg-slate-50 p-2.5 rounded-xl border border-slate-150">
                                                <i data-lucide="user-check" class="w-4 h-4 text-slate-400 shrink-0"></i>
                                                <div class="truncate">
                                                    <span class="text-[10px] font-bold uppercase text-slate-400 block">Class Adviser</span>
                                                    <span class="font-extrabold text-slate-850 truncate block">
                                                        {{ $cleanAdv }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Footer / Open Section -->
                                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                                            <span class="text-[11px] font-semibold text-slate-500">
                                                {{ $sec->subjects->count() }} Subjects
                                            </span>
                                            <a href="{{ route('admin.master-lists.sections.show', ['section' => $sec->id, 'school_year' => $schoolYear]) }}" 
                                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider bg-emerald-700 hover:bg-emerald-800 text-white shadow-2xs transition">
                                                <span>Open Section</span>
                                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

    </div>
</x-admin-layout>
