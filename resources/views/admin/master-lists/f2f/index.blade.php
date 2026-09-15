<x-admin-layout title="Face-to-Face Master Lists">
    <div class="space-y-6">

        <!-- Top Navigation Pills / Breadcrumb Submenu -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.master-lists.f2f.index') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider bg-emerald-700 text-white shadow-xs">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>Face-to-Face Lists</span>
                </a>
                <a href="{{ route('admin.master-lists.sections.index') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider bg-white text-slate-700 border border-slate-250 hover:bg-slate-50 transition shadow-2xs">
                    <i data-lucide="layers" class="w-4 h-4 text-slate-500"></i>
                    <span>Section Lists</span>
                </a>
            </div>

            <!-- School Year Selector -->
            <form method="GET" action="{{ route('admin.master-lists.f2f.index') }}" class="flex items-center gap-2.5">
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
                        Official Master Lists
                    </div>
                    <h1 class="text-2xl md:text-3xl font-black tracking-tight text-white">Face-to-Face Master Lists</h1>
                    <p class="mt-2 text-sm text-emerald-100/90 max-w-2xl font-normal leading-relaxed">
                        Grade-level student lists for in-person learning across all configured grade levels. Open any grade roster directly without requiring section assignments.
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <div class="bg-white/10 backdrop-blur-xs px-4 py-3 rounded-2xl border border-white/15 text-center">
                        <div class="text-2xl font-black text-white">{{ count($grades) }}</div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-200">Grade Levels</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-xs px-4 py-3 rounded-2xl border border-white/15 text-center">
                        <div class="text-2xl font-black text-emerald-300">
                            {{ collect($grades)->sum('student_count') }}
                        </div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-200">F2F Students</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade Levels Grid -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-2xs">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div>
                    <h2 class="text-base font-black text-slate-900">Configured Grade Levels</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Select a grade level to review its complete roster, assigned faculty adviser, linked curriculum subjects, and lock status.</p>
                </div>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">SY {{ $schoolYear }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($grades as $g)
                    <div class="rounded-2xl border {{ $g['is_empty'] ? 'border-slate-200 bg-slate-50/40' : 'border-slate-250 bg-white hover:border-emerald-400' }} p-5 shadow-2xs transition group flex flex-col justify-between">
                        <div>
                            <!-- Header / Category & Lock State -->
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg {{ $g['code'] === 'G3' ? 'bg-emerald-100 text-emerald-850 font-black' : 'bg-slate-150 text-slate-700' }}">
                                    {{ $g['category'] }}
                                </span>
                                @if($g['is_locked'])
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-700 border border-slate-250">
                                        <svg class="w-3 h-3 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-width="2.5"/>
                                        </svg>
                                        Locked
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-250">
                                        <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                                            <path d="M7 11V7a5 5 0 0 1 9.9-1" stroke-width="2.5"/>
                                        </svg>
                                        Draft
                                    </span>
                                @endif
                            </div>

                            <!-- Grade Title -->
                            <div class="flex items-baseline justify-between gap-2 mb-2">
                                <h3 class="text-lg font-black text-slate-900 group-hover:text-emerald-750 transition">
                                    {{ $g['name'] }}
                                </h3>
                                <span class="text-xs font-black {{ $g['is_empty'] ? 'text-slate-400' : 'text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md' }}">
                                    {{ $g['student_count'] }} {{ $g['student_count'] === 1 ? 'Student' : 'Students' }}
                                </span>
                            </div>

                            <!-- Adviser Info -->
                            <div class="flex items-center gap-2 text-xs text-slate-600 mb-4 bg-slate-50 p-2.5 rounded-xl border border-slate-150">
                                <i data-lucide="user-check" class="w-4 h-4 text-slate-400 shrink-0"></i>
                                <div class="truncate">
                                    <span class="text-[10px] font-bold uppercase text-slate-400 block">Adviser</span>
                                    <span class="font-extrabold text-slate-850 {{ $g['code'] === 'G3' ? 'text-emerald-900 font-black' : '' }}">
                                        {{ $g['adviser'] }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                            @if($g['is_empty'])
                                <span class="text-xs font-bold text-slate-400 italic">No students assigned</span>
                                <a href="{{ route('admin.master-lists.f2f.show', ['grade' => $g['name'], 'school_year' => $schoolYear]) }}" 
                                   class="text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                                    Inspect &rarr;
                                </a>
                            @else
                                <span class="text-xs font-bold text-emerald-700">Roster Ready</span>
                                <a href="{{ route('admin.master-lists.f2f.show', ['grade' => $g['name'], 'school_year' => $schoolYear]) }}" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider bg-emerald-700 hover:bg-emerald-800 text-white shadow-2xs transition">
                                    <span>View Roster</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</x-admin-layout>
