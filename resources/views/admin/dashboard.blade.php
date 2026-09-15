@php
    $statusColor = [
        'approved' => 'green',
        'rejected' => 'red',
        'under_review' => 'blue',
        'pending' => 'yellow',
        'submitted' => 'yellow',
        'ready_for_submission' => 'yellow',
    ];
    $totalApplications = collect($dashboardKpis ?? [])->firstWhere('key', 'applications')['value'] ?? 0;
@endphp

<x-admin-layout title="Dashboard">
    <script type="application/json" id="dashboard-chart-data">
        @json(['charts' => $dashboardCharts ?? [], 'kpis' => $dashboardKpis ?? []])
    </script>

    <!-- Glassmorphic Command Hero Banner -->
    <div class="relative overflow-hidden p-6 md:p-8 bg-gradient-to-r from-emerald-800 to-teal-950 rounded-2xl border border-emerald-700/30 shadow-sm text-white">
        <div class="absolute right-0 top-0 -mt-4 -mr-4 w-56 h-56 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="absolute left-1/3 bottom-0 -mb-8 w-64 h-64 rounded-full bg-teal-500/10 blur-3xl"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold bg-emerald-500/20 text-emerald-300 rounded-full border border-emerald-500/30 backdrop-blur-xs mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Command Center
                </span>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white">AMIS Admin Workspace</h1>
                <p class="mt-2 text-sm md:text-base text-emerald-100 max-w-2xl font-light">
                    Monitor admissions, payments, enrollment capacity, and student operations from one focused workspace for SY <span class="font-bold text-white bg-emerald-500/30 px-2.5 py-0.5 rounded-md">{{ $schoolYear ?? 'Current' }}</span>.
                </p>
            </div>

        </div>
    </div>

    <!-- Admin App Modules Launcher -->
    <section id="modules" class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-200/70 dark:border-gray-700/50 p-6 shadow-sm mt-6">
        <div class="mb-5 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-950">Admin App Modules</h2>
                <p class="mt-1 text-sm text-slate-500">Open each AMIS admin workspace from the dashboard Launcher.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $totalApplications }} Applications Applied</span>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ auth()->user()?->can('manage-microsoft-rosters') ? 17 : 16 }} modules</span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <x-dashboard.module-card :href="route('admin.students.dashboard')" icon="users" name="Student Records" owner="Registrar & Records Office" summary="Official student master list, profiles, ID cards, sectioning" accent="emerald" shape="arch" />
            <x-dashboard.module-card :href="route('admin.applications.dashboard')" icon="clipboard-check" name="Enrollment Applications" owner="Admissions & Registrar" summary="Online admissions, document review, requirements, approvals ({{ $totalApplications }} applied)" accent="violet" shape="soft" />
            @php
                $academicMaintenance = config('services.school.academic_maintenance', false) 
                    && !auth()->user()->hasRole(['super_admin', 'admin']);
            @endphp
            <x-dashboard.module-card 
                :href="$academicMaintenance ? null : route('admin.academic.dashboard')" 
                icon="book-open-check" 
                name="Academic Affairs" 
                owner="Academic Affairs Office" 
                summary="Curriculum, subjects, sections, class schedules, faculty load" 
                accent="sky" 
                shape="soft" 
                :status="$academicMaintenance ? 'Maintenance' : null" 
                :disabled="$academicMaintenance" 
            />
            @can('manage-microsoft-rosters')
                <x-dashboard.module-card :href="route('admin.microsoft-roster.index')" icon="cloud-cog" name="Microsoft 365 & Teams" owner="IT & Systems Admin" summary="Microsoft accounts, Teams rosters sync, section mappings, and sync history" accent="indigo" shape="soft" />
            @endcan
            <x-dashboard.module-card :href="route('admin.ebook.index')" icon="book-open" name="Digital Library (eBooks)" owner="Learning Resource Center" summary="Digital textbooks, uploads, grade-level reading access, and catalog" accent="teal" shape="soft" />
            <x-dashboard.module-card :href="route('admin.attendance.index')" icon="calendar-check" name="Attendance Tracking" owner="Prefect & Academic Office" summary="QR code scanner, manual attendance, daily logs & reports" accent="cyan" shape="circle" />
            <x-dashboard.module-card icon="graduation-cap" name="Grading & Assessment" owner="Faculty & Academic Office" summary="Grade encoding, assessment, report cards" accent="blue" shape="arch" status="Coming Soon" disabled />
            <x-dashboard.module-card :href="route('admin.finance.dashboard')" icon="wallet" name="Finance & Tuition" :owner="config('services.school.finance_reviewer_name', 'Finance & Cashier Office')" summary="Tuition SOA, online payment review, fees, and receipts" accent="amber" shape="soft" />
            <x-dashboard.module-card icon="chart-no-axes-combined" name="Enrollment Analytics" owner="Institutional Analytics" summary="Admissions growth, capacity trends, cohort distribution insights" accent="orange" shape="circle" status="Coming Soon" disabled />
            <x-dashboard.module-card icon="file-down" name="Official Reports & Exports" owner="Registrar & Finance" summary="DepEd reports, master lists, PDF & Excel exports" accent="pink" shape="arch" status="Coming Soon" disabled />
            <x-dashboard.module-card :href="route('admin.administration.users.index')" icon="users" name="User Directory & Staff" owner="System Administrator" summary="User directories, verification status, and credentials security" accent="fuchsia" shape="soft" />
            <x-dashboard.module-card :href="route('admin.support.index')" icon="message-square" name="Help Desk & Inquiries" owner="IT & Support Desk" summary="Manage student & parent inquiries, concerns, support tickets" accent="rose" shape="soft" />
            <x-dashboard.module-card :href="route('admin.website.announcements.index')" icon="globe" name="School Portal & CMS" owner="Public Relations & Media" summary="Manage announcements, news, events, and public school bulletins" accent="teal" shape="soft" />
            <x-dashboard.module-card :href="route('admin.registrations.halaqah')" icon="user-plus" name="Special Registrations" owner="Registrar & Madrasah" summary="Halaqah Online, study circles, public inquiry registrations" accent="purple" shape="soft" />
            <x-dashboard.module-card :href="route('admin.access-control.roles.index')" icon="key" name="Roles & Permissions" owner="Security Administrator" summary="Roles, permissions matrix, and access policy definitions" accent="indigo" shape="soft" />
            <x-dashboard.module-card :href="route('admin.security-workspace.login-activity')" icon="shield-check" name="Security & Audit" owner="Security Auditing" summary="Session tracking, login attempts, force revokes, and alerts" accent="rose" shape="soft" />
            <x-dashboard.module-card :href="route('admin.system-management.backups.index')" icon="settings-2" name="System Operations" owner="DevOps & Systems Admin" summary="Backup Center, restore dumps, health status, and API sync" accent="slate" shape="soft" />
            <x-dashboard.module-card :href="route('admin.settings.discounts')" icon="settings" name="School Settings & Config" owner="System Administrator" summary="School profile, MS365 sync, discounts, integrations" accent="lime" shape="circle" />
        </div>
    </section>

    <!-- Metrics telemetry panel -->
    <section class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        @foreach (collect($dashboardKpis ?? [])->reject(fn($m) => ($m['key'] ?? '') === 'payments') as $metric)
            <x-dashboard.kpi-card :metric="$metric" />
        @endforeach
    </section>

    <!-- Analytics charts wrappers -->
    {{-- Commented out for live production cleanup --}}
    {{--
    <section class="mt-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
        <x-dashboard.chart-card class="lg:col-span-7" title="Enrollment Trend" subtitle="Monthly enrollment growth" chart="enrollmentTrendChart" />
        <x-dashboard.chart-card class="lg:col-span-5" title="Application Status" subtitle="Review and payment distribution" chart="statusDonutChart" />
        <x-dashboard.chart-card class="lg:col-span-6" title="Grade Distribution" subtitle="Applications by grade level" chart="gradeDistributionChart" />
        <x-dashboard.chart-card class="lg:col-span-6" title="Payment Analytics" subtitle="Weekly verified and pending payment volume" chart="paymentTrendChart" />
    </section>
    --}}

    <!-- Recent applications & quick actions -->
    <section class="mt-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-200/70 dark:border-gray-700/50 p-6 shadow-sm lg:col-span-8">
            <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Recent Applications</h2>
                    <p class="mt-1 text-sm text-slate-500 font-light">Latest submitted enrollment records</p>
                </div>
                <div class="flex items-center gap-3">
                    <label class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400"></i>
                        <input type="search" placeholder="Search applicants" class="table-control pl-9">
                    </label>
                    <select class="table-control">
                        <option>All status</option>
                        <option>Under Review</option>
                        <option>Approved</option>
                        <option>Rejected</option>
                    </select>
                </div>
            </div>

            <div class="premium-table-wrap border border-slate-100 rounded-xl overflow-hidden">
                <table class="premium-table w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Applicant</th>
                            <th class="px-4 py-3">Grade</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent ?? [] as $applicant)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.applicants.show', $applicant) }}" class="font-extrabold text-slate-950 hover:text-emerald-700 uppercase tracking-wide">
                                        {{ Str::upper(trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? '')) ?: 'Applicant') }}
                                    </a>
                                    <div class="mt-1 text-xs text-slate-500 font-light">{{ $applicant->user->email ?? $applicant->email ?? 'No email' }}</div>
                                </td>
                                <td class="px-4 py-4 text-xs font-semibold text-gray-800">{{ $applicant->grade_level ?? '-' }}</td>
                                <td class="px-4 py-4">
                                    @php
                                        $status = $applicant->status;
                                        $label = $statusLabels[$status] ?? $status;
                                    @endphp
                                    @if($status === 'approved')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            {{ $label }}
                                        </span>
                                    @elseif($status === 'rejected' || $status === 'cancelled')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-100">
                                            {{ $label }}
                                        </span>
                                    @elseif($status === 'under_review')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                            {{ $label }}
                                        </span>
                                    @elseif($status === 'submitted' || $status === 'ready_for_submission')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            {{ $label }}
                                        </span>
                                    @elseif($status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                                            {{ $label }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-150">
                                            {{ $label }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @php
                                        $payStatus = strtolower($applicant->payment->status ?? 'no payment');
                                    @endphp
                                    @if ($payStatus === 'verified' || $payStatus === 'paid')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            Paid
                                        </span>
                                    @elseif ($payStatus === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                                            Pending
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-150">
                                            No Payment
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-xs text-gray-400 font-medium">{{ optional($applicant->created_at)->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                                    <div class="empty-state">
                                        <i data-lucide="inbox" class="h-8 w-8 text-slate-300"></i>
                                        <p class="font-semibold text-sm">No recent applications yet.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside id="quick-actions" class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-200/70 dark:border-gray-700/50 p-6 shadow-sm lg:col-span-4">
            <div class="mb-5 border-b border-gray-100 dark:border-gray-700 pb-4">
                <h2 class="text-base font-semibold text-slate-950">Quick Actions</h2>
                <p class="mt-1 text-sm text-slate-500 font-light">Common admin workflows</p>
            </div>
            <div class="space-y-3">
                <x-dashboard.quick-action :href="route('admin.applications.review')" icon="clipboard-check" label="Review Applications" meta="Approve, reject, or request updates" />
                <x-dashboard.quick-action :href="route('admin.students.index')" icon="user-plus" label="Add Student" meta="Manage enrolled student records" />
                <x-dashboard.quick-action :href="route('admin.finance.verification.index')" icon="wallet" label="Payment Verification" meta="Review online family payment proofs" />
                <x-dashboard.quick-action :href="route('admin.finance.families.index')" icon="scroll-text" label="Family Accounts / SOA" meta="Open consolidated family balances" />
                <x-dashboard.quick-action :href="route('admin.ebook.create')" icon="upload-cloud" label="Upload eBook" meta="Add a PDF to the eBook library" />
                {{-- Commented out for live production cleanup --}}
                {{--
                <x-dashboard.quick-action :href="route('admin.enrollment.reports')" icon="bar-chart-3" label="Reports" meta="Open enrollment reporting" />
                --}}
            </div>

            <!-- Storage Usage -->
            @if (isset($storageStats))
            @php
                $formatBytes = function($bytes) {
                    if ($bytes >= 1099511627776) return number_format($bytes / 1099511627776, 1) . 'TB';
                    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1) . 'GB';
                    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . 'MB';
                    return number_format($bytes / 1024, 1) . 'KB';
                };
            @endphp
            <div class="mt-6 pt-5 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="hard-drive" class="w-4 h-4 text-slate-500"></i>
                        <h3 class="text-sm font-semibold text-slate-800">Server Storage</h3>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-{{ $storageStats['healthColor'] }}-50 text-{{ $storageStats['healthColor'] }}-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-{{ $storageStats['healthColor'] }}-500"></span>
                        {{ $storageStats['health'] }}
                    </span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                    <div class="h-3 rounded-full transition-all duration-500 bg-{{ $storageStats['healthColor'] }}-500" style="width: {{ min($storageStats['percent'], 100) }}%"></div>
                </div>
                <div class="mt-2 flex justify-between text-xs text-slate-500">
                    <span>{{ $formatBytes($storageStats['used']) }} used</span>
                    <span>{{ $formatBytes($storageStats['total']) }} total</span>
                </div>
                <div class="mt-1 text-xs text-slate-400">{{ $formatBytes($storageStats['free']) }} free ({{ 100 - $storageStats['percent'] }}%)</div>
                <div class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                    <i data-lucide="folder" class="w-3 h-3"></i>
                    <span>Documents: {{ $formatBytes($storageStats['documents']) }}</span>
                </div>
            </div>
            @endif
        </aside>
    </section>
</x-admin-layout>
