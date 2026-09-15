<x-admin-layout :title="'Section List: ' . $section->name">
    <div class="space-y-6" x-data="{ lockModal: false, unlockModal: false }">

        <!-- Top Navigation / Actions & School Year Selector -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.master-lists.sections.index', ['school_year' => $schoolYear]) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 bg-white border border-slate-250 shadow-2xs hover:bg-slate-50 transition">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>All Sections</span>
                </a>
                <a href="{{ route('admin.master-lists.f2f.index', ['school_year' => $schoolYear]) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 bg-white border border-slate-250 shadow-2xs hover:bg-slate-50 transition">
                    <i data-lucide="user-check" class="w-4 h-4 text-slate-500"></i>
                    <span>Face-to-Face Lists</span>
                </a>
            </div>

            <!-- School Year Selector -->
            <form method="GET" action="{{ route('admin.master-lists.sections.show', $section->id) }}" class="flex items-center gap-2.5">
                <label for="sy-select" class="text-xs font-bold text-slate-600 uppercase tracking-wide">School Year:</label>
                <select id="sy-select" name="school_year" onchange="this.form.submit()" 
                        class="text-xs font-bold bg-white border border-slate-250 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-slate-800 shadow-2xs cursor-pointer">
                    <option value="2026-2027" {{ $schoolYear === '2026-2027' ? 'selected' : '' }}>SY 2026–2027</option>
                    <option value="2025-2026" {{ $schoolYear === '2025-2026' ? 'selected' : '' }}>SY 2025–2026</option>
                    <option value="2027-2028" {{ $schoolYear === '2027-2028' ? 'selected' : '' }}>SY 2027–2028</option>
                </select>
            </form>
        </div>

        <!-- Header Card with Title, Draft / Locked Badge, and Finalize / Unlock Actions -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-2xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                            {{ $section->grade_level }}
                        </span>

                        <span class="text-xs font-bold uppercase tracking-wider {{ str_contains(strtolower($section->learning_mode ?? ''), 'flexible') ? 'text-indigo-800 bg-indigo-50 border-indigo-200' : 'text-slate-700 bg-slate-100 border-slate-200' }} px-2.5 py-1 rounded-lg border">
                            {{ $section->learning_mode ?: 'Face-to-Face' }}
                        </span>

                        <!-- Visible Draft / Locked Status Badge beside Title -->
                        @if($lock && $lock->status === 'locked')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-slate-900 text-white shadow-xs">
                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-width="2.5"/>
                                </svg>
                                <span>Locked</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-900 border border-amber-300 shadow-2xs">
                                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                                    <path d="M7 11V7a5 5 0 0 1 9.9-1" stroke-width="2.5"/>
                                </svg>
                                <span>Draft</span>
                            </span>
                        @endif
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black text-slate-950 mt-2.5">
                        {{ $section->name }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1 font-medium">
                        School Year {{ $schoolYear }} &bull; Official Section Roster &amp; Academic Enrollment
                        @if($section->room)
                            &bull; Room: {{ $section->room }}
                        @endif
                    </p>
                </div>

                <!-- Finalize & Lock / Unlock Controls -->
                <div class="flex items-center gap-3 shrink-0">
                    @if($lock && $lock->status === 'locked')
                        @if(in_array(auth()->user()?->role, ['super_admin', 'admin']))
                            <button type="button" @click="unlockModal = true"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-250 transition shadow-2xs cursor-pointer">
                                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                                    <path d="M7 11V7a5 5 0 0 1 9.9-1" stroke-width="2.5"/>
                                </svg>
                                <span>Unlock Roster</span>
                            </button>
                        @endif
                    @else
                        @if(in_array(auth()->user()?->role, ['super_admin', 'admin']))
                            <button type="button" @click="lockModal = true"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider bg-emerald-700 hover:bg-emerald-800 text-white transition shadow-sm cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-width="2.5"/>
                                </svg>
                                <span>Finalize & Lock</span>
                            </button>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Lock Info Details if locked -->
            @if($lock && $lock->status === 'locked')
                <div class="mt-4 pt-3 border-t border-slate-150 flex items-center justify-between flex-wrap gap-2 text-xs text-slate-500">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-700">Roster finalized by:</span>
                        <span class="font-semibold text-slate-900">{{ $lock->lockedBy?->name ?? 'Administrator' }}</span>
                        <span class="text-slate-400">({{ $lock->locked_at?->format('M d, Y h:i A') ?? 'N/A' }})</span>
                    </div>
                    <span class="text-[11px] font-bold text-amber-850 bg-amber-50 px-2.5 py-0.5 rounded-md border border-amber-200">
                        Section reassignment locked in backend &bull; Grade encoding active
                    </span>
                </div>
            @endif
        </div>

        <!-- Adviser & Meta Information Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            
            <!-- Assigned Adviser Card -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-2xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Assigned Class Adviser</div>
                <div class="flex items-center gap-3 mt-1">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 border border-emerald-300 flex items-center justify-center text-emerald-800 shrink-0 font-black text-sm">
                        {{ substr(is_string($adviser) ? $adviser : ($adviser->teacher_name ?? 'A'), 0, 1) }}
                    </div>
                    <div>
                        <div class="font-black text-slate-900 text-sm">
                            {{ is_string($adviser) ? $adviser : ($adviser->teacher_name ?? 'Assigned Faculty') }}
                        </div>
                        <div class="text-[11px] font-semibold text-emerald-700 mt-0.5">Official Section Adviser</div>
                    </div>
                </div>
            </div>

            <!-- Student Counts Card -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-2xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Section Enrollment Summary</div>
                <div class="flex items-center gap-4 mt-2">
                    <div>
                        <span class="text-2xl font-black text-slate-950">{{ $totalStudents }}</span>
                        <span class="text-[11px] font-bold text-slate-500 block uppercase">Total Enrolled</span>
                    </div>
                    <div class="h-8 w-px bg-slate-200"></div>
                    <div>
                        <span class="text-xl font-black text-blue-700">{{ $totalMale }}</span>
                        <span class="text-[10px] font-bold text-slate-400 block uppercase">Male</span>
                    </div>
                    <div class="h-8 w-px bg-slate-200"></div>
                    <div>
                        <span class="text-xl font-black text-pink-700">{{ $totalFemale }}</span>
                        <span class="text-[10px] font-bold text-slate-400 block uppercase">Female</span>
                    </div>
                </div>
            </div>

            <!-- Modality & Capacity Card -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-2xs">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Learning Modality &amp; Capacity</div>
                <div class="mt-1.5">
                    <span class="font-extrabold text-sm text-slate-850">{{ $section->learning_mode ?: 'Face-to-Face' }}</span>
                    <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-2">
                        <span>Max Capacity: <strong class="text-slate-800">{{ $section->max_students ?: 'Open' }}</strong></span>
                        @if($section->room)
                            <span>&bull; Room: <strong class="text-slate-800">{{ $section->room }}</strong></span>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        <!-- Linked Academic and Islamic Subjects -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-2xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-150 mb-4">
                <div>
                    <h2 class="text-base font-black text-slate-900">Linked Curriculum Subjects</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Configured academic and Islamic subjects taught in {{ $section->name }}.</p>
                </div>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                    {{ count($academicSubjects) + count($islamicSubjects) }} Subjects Linked
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Academic Subjects -->
                <div class="border border-slate-200 rounded-2xl p-4.5 bg-slate-50/40">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-850 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                            Academic Subjects
                        </span>
                        <span class="text-[11px] font-bold text-slate-500">{{ count($academicSubjects) }} Subjects</span>
                    </div>
                    <div class="space-y-2">
                        @forelse($academicSubjects as $as)
                            <div class="bg-white border border-slate-200 rounded-xl p-3 flex items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="font-extrabold text-slate-900">{{ $as->subject_name }}</div>
                                    <div class="text-[11px] text-slate-500 font-medium">{{ $as->teacher_name ?: 'Assigned Faculty' }}</div>
                                </div>
                                <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">Academic</span>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 italic py-2">No academic subjects linked to this section.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Islamic Subjects -->
                <div class="border border-slate-200 rounded-2xl p-4.5 bg-slate-50/40">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-850 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-teal-600"></span>
                            Islamic Subjects
                        </span>
                        <span class="text-[11px] font-bold text-slate-500">{{ count($islamicSubjects) }} Subjects</span>
                    </div>
                    <div class="space-y-2">
                        @forelse($islamicSubjects as $is)
                            <div class="bg-white border border-slate-200 rounded-xl p-3 flex items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="font-extrabold text-slate-900">{{ $is->subject_name }}</div>
                                    <div class="text-[11px] text-slate-500 font-medium">{{ $is->teacher_name ?: 'Assigned Asatidz' }}</div>
                                </div>
                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">Islamic</span>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 italic py-2">No Islamic subjects linked to this section.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Rosters -->
        @if($totalStudents === 0)
            <!-- Empty State for Section without students -->
            <div class="bg-white border border-slate-200 rounded-3xl p-12 text-center shadow-2xs">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 border border-slate-250 flex items-center justify-center mx-auto text-slate-400 mb-4">
                    <i data-lucide="users" class="w-7 h-7"></i>
                </div>
                <h3 class="text-lg font-black text-slate-850 mb-1">No students assigned.</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
                    There are currently no students assigned to <strong class="text-slate-700">{{ $section->name }}</strong> for School Year {{ $schoolYear }}.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <a href="{{ route('admin.master-lists.sections.index', ['school_year' => $schoolYear]) }}" 
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                        <span>Back to Section Lists</span>
                    </a>
                    <a href="{{ route('admin.roster.index') }}" 
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition">
                        <span>Go to Student Roster Manager</span>
                    </a>
                </div>
            </div>
        @else
            <!-- Male Learners Table -->
            <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-2xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-150 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-3 h-3 rounded-full bg-blue-600 inline-block"></span>
                        <h2 class="text-base font-black text-slate-900">Male Learners</h2>
                    </div>
                    <span class="text-xs font-black text-blue-800 bg-blue-50 border border-blue-200 px-3 py-1 rounded-full">
                        {{ $totalMale }} Male Students
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-black uppercase text-[11px]">
                                <th class="py-3 px-3 w-12 text-center">#</th>
                                <th class="py-3 px-3">Student Number</th>
                                <th class="py-3 px-4">Learner Name</th>
                                <th class="py-3 px-3">LRN</th>
                                <th class="py-3 px-3">Email Address</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($maleStudents as $idx => $st)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-3 text-center font-bold text-slate-400">{{ $idx + 1 }}</td>
                                    <td class="py-3 px-3 font-extrabold text-slate-900 font-mono">{{ $st['student_number'] }}</td>
                                    <td class="py-3 px-4 font-black text-slate-950 text-sm">
                                        <a href="{{ $st['profile_url'] }}" class="text-slate-900 hover:text-emerald-700 transition">
                                            {{ $st['name'] }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-slate-600 font-mono">{{ $st['lrn'] }}</td>
                                    <td class="py-3 px-3 text-slate-600 font-mono text-[11px]">{{ $st['email'] ?: 'N/A' }}</td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="text-[10px] font-black text-emerald-800 bg-emerald-50 border border-emerald-250 px-2.5 py-0.5 rounded-full uppercase">
                                            {{ $st['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="{{ $st['profile_url'] }}" 
                                           class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-900 hover:underline">
                                            <span>View Profile</span>
                                            <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-400 italic">No male learners assigned to this section.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Female Learners Table -->
            <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-2xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-150 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-3 h-3 rounded-full bg-pink-600 inline-block"></span>
                        <h2 class="text-base font-black text-slate-900">Female Learners</h2>
                    </div>
                    <span class="text-xs font-black text-pink-800 bg-pink-50 border border-pink-200 px-3 py-1 rounded-full">
                        {{ $totalFemale }} Female Students
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-black uppercase text-[11px]">
                                <th class="py-3 px-3 w-12 text-center">#</th>
                                <th class="py-3 px-3">Student Number</th>
                                <th class="py-3 px-4">Learner Name</th>
                                <th class="py-3 px-3">LRN</th>
                                <th class="py-3 px-3">Email Address</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($femaleStudents as $idx => $st)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-3 text-center font-bold text-slate-400">{{ $idx + 1 }}</td>
                                    <td class="py-3 px-3 font-extrabold text-slate-900 font-mono">{{ $st['student_number'] }}</td>
                                    <td class="py-3 px-4 font-black text-slate-950 text-sm">
                                        <a href="{{ $st['profile_url'] }}" class="text-slate-900 hover:text-emerald-700 transition">
                                            {{ $st['name'] }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-slate-600 font-mono">{{ $st['lrn'] }}</td>
                                    <td class="py-3 px-3 text-slate-600 font-mono text-[11px]">{{ $st['email'] ?: 'N/A' }}</td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="text-[10px] font-black text-emerald-800 bg-emerald-50 border border-emerald-250 px-2.5 py-0.5 rounded-full uppercase">
                                            {{ $st['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="{{ $st['profile_url'] }}" 
                                           class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-900 hover:underline">
                                            <span>View Profile</span>
                                            <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-400 italic">No female learners assigned to this section.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Finalize & Lock Modal (Alpine.js) -->
        <div x-show="lockModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" @click="lockModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-xl border border-slate-200" @click.stop>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 border border-emerald-250 flex items-center justify-center text-emerald-800 mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-width="2.5"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-black text-slate-900">Finalize &amp; Lock Section Roster</h3>
                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        Are you sure you want to finalize and lock the roster for <span class="font-bold text-slate-900">{{ $section->name }}</span> (SY {{ $schoolYear }})?
                    </p>
                    <div class="mt-3 p-3 bg-amber-50 rounded-xl border border-amber-200 text-[11px] text-amber-900 font-medium">
                        Locking prevents subsequent section reassignments in the backend. Grade encoding remains fully accessible.
                    </div>
                    <form method="POST" action="{{ route('admin.master-lists.lock') }}" class="mt-6">
                        @csrf
                        <input type="hidden" name="roster_type" value="section">
                        <input type="hidden" name="roster_identifier" value="{{ $section->id }}">
                        <input type="hidden" name="school_year" value="{{ $schoolYear }}">
                        
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" @click="lockModal = false"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider bg-emerald-700 hover:bg-emerald-800 text-white shadow-sm transition cursor-pointer">
                                Confirm &amp; Lock Roster
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Unlock Roster Modal with Required Reason (Alpine.js) -->
        <div x-show="unlockModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" @click="unlockModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-xl border border-slate-200" @click.stop>
                    <div class="w-12 h-12 rounded-2xl bg-rose-100 border border-rose-250 flex items-center justify-center text-rose-800 mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2.5"/>
                            <path d="M7 11V7a5 5 0 0 1 9.9-1" stroke-width="2.5"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-black text-slate-900">Unlock Section Roster</h3>
                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        Unlocking this roster will allow administrators to modify student section assignments for <span class="font-bold text-slate-900">{{ $section->name }}</span>.
                    </p>
                    <div class="mt-3 p-3 bg-rose-50 rounded-xl border border-rose-200 text-[11px] text-rose-900 font-medium">
                        Administrative unlock requires a recorded reason. This justification will be logged in the permanent audit trail.
                    </div>
                    <form method="POST" action="{{ route('admin.master-lists.unlock') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="roster_type" value="section">
                        <input type="hidden" name="roster_identifier" value="{{ $section->id }}">
                        <input type="hidden" name="school_year" value="{{ $schoolYear }}">
                        
                        <div>
                            <label for="unlock_reason" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">
                                Reason for Unlocking <span class="text-rose-600">*</span>
                            </label>
                            <textarea id="unlock_reason" name="reason" rows="3" required minlength="5" maxlength="1000"
                                      placeholder="State reason for unlocking this finalized roster (e.g. late student transfer, administrative correction)..."
                                      class="w-full text-xs rounded-xl border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 p-3 text-slate-800 placeholder-slate-400"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" @click="unlockModal = false"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider bg-rose-700 hover:bg-rose-800 text-white shadow-sm transition cursor-pointer">
                                Authorize &amp; Unlock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-admin-layout>
