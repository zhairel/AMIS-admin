@php
    $workspaceSections = [
        [
            'active' => request()->routeIs('admin.dashboard'),
            'icon' => 'layout-dashboard', 'iconClass' => 'text-slate-600', 'headerClass' => 'text-slate-700', 'activeClass' => 'sidebar-link-active-slate', 'title' => 'Dashboard',
            'links' => [
                ['Overview', 'layout-dashboard', route('admin.dashboard'), true],
                ['App Modules', 'layout-grid', route('admin.dashboard').'#modules', false],
                ['Quick Actions', 'zap', route('admin.dashboard').'#quick-actions', false],
            ],
        ],
        [
            'active' => (request()->routeIs('admin.applications.*') || request()->routeIs('admin.applicants.*') || request()->routeIs('admin.enrollment.index') || request()->routeIs('admin.enrollment.masters-list')) && request('workspace') !== 'reports',
            'icon' => 'clipboard-check', 'iconClass' => 'text-violet-600', 'headerClass' => 'text-violet-700', 'activeClass' => 'sidebar-link-active-violet', 'title' => 'Enrollment Applications',
            'links' => [
                ['Dashboard', 'layout-dashboard', route('admin.applications.dashboard'), request()->routeIs('admin.applications.dashboard')],
                ['Enrollment Applications', 'file-text', route('admin.applications.enrollment'), request()->routeIs('admin.applications.enrollment') || request()->routeIs('admin.applicants.index')],
                ['Applicant Review', 'file-search', route('admin.applications.review'), request()->routeIs('admin.applications.review') || request()->routeIs('admin.applicants.show')],
                ['Requirements', 'list-checks', route('admin.applications.requirements'), request()->routeIs('admin.applications.requirements')],
                ['Approval Workflow', 'shield-check', route('admin.applications.approval'), request()->routeIs('admin.applications.approval')],
                ['Enrollee Masters List', 'list', route('admin.enrollment.masters-list'), request()->routeIs('admin.enrollment.masters-list') && request('workspace') !== 'reports'],
                ['Archive / Trash (7d)', 'archive', route('admin.applications.archive'), request()->routeIs('admin.applications.archive')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.students.*') && !request()->routeIs('admin.students.families'),
            'icon' => 'users', 'iconClass' => 'text-emerald-600', 'headerClass' => 'text-emerald-700', 'activeClass' => 'sidebar-link-active-emerald', 'title' => 'Student Records',
            'links' => [
                ['Dashboard', 'layout-dashboard', route('admin.students.dashboard'), request()->routeIs('admin.students.dashboard')],
                ['Student Records', 'user-check', route('admin.students.index'), (request()->routeIs('admin.students.index') || request()->routeIs('admin.students.show')) && !request()->routeIs('admin.students.families') && !request()->routeIs('admin.students.unassigned')],
                ['Unassigned Students', 'user-x', route('admin.students.unassigned'), request()->routeIs('admin.students.unassigned')],
                ['Archived Students', 'archive', route('admin.students.archive'), request()->routeIs('admin.students.archive')],
                ['Account Onboarding', 'user-cog', route('admin.students.accounts'), request()->routeIs('admin.students.accounts')],
                ['Class Occupancy', 'grid', route('admin.students.occupancy'), request()->routeIs('admin.students.occupancy')],
                ['CSV Comparison', 'scale', route('admin.students.comparison'), request()->routeIs('admin.students.comparison')],
                ['Enrollment History', 'history', route('admin.students.history'), request()->routeIs('admin.students.history')],
                ['Audit Logs', 'clipboard-list', route('admin.students.audit-logs'), request()->routeIs('admin.students.audit-logs')],
                ['Reports & Analytics', 'file-text', route('admin.students.reports'), request()->routeIs('admin.students.reports')],
                ['Call Attendance', 'calendar-days', route('admin.students.attendance'), request()->routeIs('admin.students.attendance')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.ms-teams.*') || request()->routeIs('admin.academic.*'),
            'icon' => 'book-open-check', 'iconClass' => 'text-sky-600', 'headerClass' => 'text-sky-700', 'activeClass' => 'sidebar-link-active-sky', 'title' => 'Academic Affairs',
            'links' => [
                ['Dashboard', 'layout-dashboard', route('admin.academic.dashboard'), request()->routeIs('admin.academic.dashboard') || request()->routeIs('admin.academic.dashboard.index')],
                ['Existing SY Schedule', 'calendar-clock', route('admin.academic.schedule-copy'), request()->routeIs('admin.academic.schedule-copy')],
                ['Schedule Builder', 'calendar-range', route('admin.academic.builder'), request()->routeIs('admin.academic.builder') || request()->routeIs('admin.academic.schedules')],
                ['Teachers', 'user-check', route('admin.academic.teachers'), request()->routeIs('admin.academic.teachers*')],
                ['Advisers', 'contact-round', route('admin.academic.class-advisory'), request()->routeIs('admin.academic.class-advisory')],
                ['Subjects', 'book-open', route('admin.academic.subjects'), request()->routeIs('admin.academic.subjects*')],
                ['Sections', 'layers', route('admin.academic.sections'), request()->routeIs('admin.academic.sections*')],
                ['Rooms', 'school', route('admin.academic.rooms'), request()->routeIs('admin.academic.rooms*')],
                ['Faculty Loads & Timetables', 'chart-pie', route('admin.academic.workload'), request()->routeIs('admin.academic.workload')],
                ['Teacher Tracking', 'user-check', route('admin.academic.teacher-tracking'), request()->routeIs('admin.academic.teacher-tracking*')],
                ['Section Tracking', 'layout-grid', route('admin.academic.section-tracking'), request()->routeIs('admin.academic.section-tracking*')],
                ['Reports & Export', 'file-chart-column', route('admin.academic.reports'), request()->routeIs('admin.academic.reports*')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.attendance.*'),
            'icon' => 'calendar-check', 'iconClass' => 'text-cyan-600', 'headerClass' => 'text-cyan-700', 'activeClass' => 'sidebar-link-active-cyan', 'title' => 'Attendance',
            'links' => [
                ['Overview', 'layout-dashboard', route('admin.attendance.index'), request()->routeIs('admin.attendance.index')],
                ['Live QR Scanner', 'qr-code', route('admin.attendance.scanner'), request()->routeIs('admin.attendance.scanner')],
                ['Manual Entry', 'edit-3', route('admin.attendance.manual'), request()->routeIs('admin.attendance.manual')],
                ['Attendance Reports', 'file-text', route('admin.attendance.reports'), request()->routeIs('admin.attendance.reports')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.ebook.*'),
            'icon' => 'book-open', 'iconClass' => 'text-teal-600', 'headerClass' => 'text-teal-700', 'activeClass' => 'sidebar-link-active-teal', 'title' => 'eBook Library',
            'links' => [
                ['Library Dashboard', 'layout-dashboard', route('admin.ebook.index'), request()->routeIs('admin.ebook.index')],
                ['Upload eBook', 'upload-cloud', route('admin.ebook.create'), request()->routeIs('admin.ebook.create')],
                ['Upload Tracking', 'users', route('admin.ebook.tracking'), request()->routeIs('admin.ebook.tracking')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.finance.*'),
            'icon' => 'wallet', 'iconClass' => 'text-amber-600', 'headerClass' => 'text-amber-700', 'activeClass' => 'sidebar-link-active-amber', 'title' => 'Finance & Cashier',
            'links' => [
                ['Finance Dashboard', 'layout-dashboard', route('admin.finance.dashboard'), request()->routeIs('admin.finance.dashboard')],
                ['Student Accounts & SOA', 'users', route('admin.finance.families.index'), request()->routeIs('admin.finance.families.*', 'admin.finance.students.*', 'admin.finance.manual-soa.*')],
                ['Online Payment Review', 'badge-check', route('admin.finance.verification.index'), request()->routeIs('admin.finance.verification.*')],
                ['Payment Records', 'receipt-text', route('admin.finance.transactions.index'), request()->routeIs('admin.finance.transactions.*', 'admin.finance.onsite.*', 'admin.finance.receipts.*')],
                ['Reports', 'chart-no-axes-combined', route('admin.finance.reports.index'), request()->routeIs('admin.finance.reports.*', 'admin.finance.monthly-reminders.*')],
                ['Audit Log', 'history', route('admin.finance.audit.index'), request()->routeIs('admin.finance.audit.*')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.enrollment.analytics'),
            'icon' => 'chart-no-axes-combined', 'iconClass' => 'text-orange-600', 'headerClass' => 'text-orange-700', 'activeClass' => 'sidebar-link-active-orange', 'title' => 'Analytics',
            'links' => [
                ['Enrollment Analytics', 'chart-no-axes-combined', route('admin.enrollment.analytics'), true],
                ['Performance Reports', 'activity', route('admin.enrollment.analytics'), false],
                ['Charts', 'bar-chart-3', route('admin.enrollment.analytics'), false],
                ['Insights', 'sparkles', route('admin.enrollment.analytics'), false],
            ],
        ],
        [
            'active' => request()->routeIs('admin.enrollment.reports') || (request()->routeIs('admin.enrollment.masters-list') && request('workspace') === 'reports'),
            'icon' => 'file-down', 'iconClass' => 'text-pink-600', 'headerClass' => 'text-pink-700', 'activeClass' => 'sidebar-link-active-pink', 'title' => 'Reports',
            'links' => [
                ['Enrollee Masters List', 'list', route('admin.enrollment.masters-list', ['workspace' => 'reports']), request()->routeIs('admin.enrollment.masters-list') && request('workspace') === 'reports'],
                ['Export', 'download', route('admin.enrollment.reports'), request()->routeIs('admin.enrollment.reports')],
                ['PDF Reports', 'file-text', route('admin.enrollment.reports'), false],
                ['Excel Reports', 'sheet', route('admin.enrollment.reports'), false],
                ['Registrar / Finance', 'briefcase-business', route('admin.enrollment.reports'), false],
            ],
        ],
        [
            'active' => request()->routeIs('admin.administration.*'),
            'icon' => 'users', 'iconClass' => 'text-fuchsia-600', 'headerClass' => 'text-fuchsia-700', 'activeClass' => 'sidebar-link-active-fuchsia', 'title' => 'Administration',
            'links' => [
                ['User Accounts', 'user-cog', route('admin.administration.users.index'), request()->routeIs('admin.administration.users.*')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.website.*'),
            'icon' => 'globe', 'iconClass' => 'text-teal-600', 'headerClass' => 'text-teal-700', 'activeClass' => 'sidebar-link-active-teal', 'title' => 'Website CMS',
            'links' => [
                ['Announcements', 'megaphone', route('admin.website.announcements.index'), request()->routeIs('admin.website.announcements.*')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.support.*'),
            'icon' => 'message-square', 'iconClass' => 'text-rose-600', 'headerClass' => 'text-rose-700', 'activeClass' => 'sidebar-link-active-rose', 'title' => 'Support Center',
            'links' => [
                ['Inquiries List', 'list', route('admin.support.index'), request()->routeIs('admin.support.index') || request()->routeIs('admin.support.show')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.registrations.*'),
            'icon' => 'user-plus', 'iconClass' => 'text-emerald-600', 'headerClass' => 'text-emerald-700', 'activeClass' => 'sidebar-link-active-emerald', 'title' => 'Special Registrations',
            'links' => [
                ['Halaqah Online', 'check-square', route('admin.registrations.halaqah'), request()->routeIs('admin.registrations.halaqah')],
                ['Halaqah Parents', 'users', route('admin.registrations.halaqah-parents'), request()->routeIs('admin.registrations.halaqah-parents')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.access-control.*'),
            'icon' => 'key', 'iconClass' => 'text-indigo-600', 'headerClass' => 'text-indigo-700', 'activeClass' => 'sidebar-link-active-indigo', 'title' => 'Access Control',
            'links' => [
                ['Roles', 'shield', route('admin.access-control.roles.index'), request()->routeIs('admin.access-control.roles.*')],
                ['Permissions Matrix', 'table', route('admin.access-control.permissions.index'), request()->routeIs('admin.access-control.permissions.*')],
                ['Role Assignment', 'user-check', route('admin.access-control.assignment.index'), request()->routeIs('admin.access-control.assignment.*')],
                ['Access Policies', 'book-open', route('admin.access-control.policies.index'), request()->routeIs('admin.access-control.policies.*')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.security-workspace.*'),
            'icon' => 'shield-check', 'iconClass' => 'text-rose-600', 'headerClass' => 'text-rose-700', 'activeClass' => 'sidebar-link-active-rose', 'title' => 'Security Workspace',
            'links' => [
                ['Security Metrics', 'bar-chart-3', route('admin.security-workspace.metrics'), request()->routeIs('admin.security-workspace.metrics')],
                ['Login Activity', 'activity', route('admin.security-workspace.login-activity'), request()->routeIs('admin.security-workspace.login-activity')],
                ['Active Sessions', 'laptop', route('admin.security-workspace.sessions.index'), request()->routeIs('admin.security-workspace.sessions.*')],
                ['Security Events', 'fingerprint', route('admin.security-workspace.events.index'), request()->routeIs('admin.security-workspace.events.*')],
                ['Audit Logs', 'logs', route('admin.security-workspace.audit-logs'), request()->routeIs('admin.security-workspace.audit-logs')],
                ['Security Alerts', 'bell-ring', route('admin.security-workspace.alerts.index'), request()->routeIs('admin.security-workspace.alerts.*')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.system-management.*'),
            'icon' => 'settings-2', 'iconClass' => 'text-slate-600', 'headerClass' => 'text-slate-700', 'activeClass' => 'sidebar-link-active-slate', 'title' => 'System Management',
            'links' => [
                ['Backup Center', 'database', route('admin.system-management.backups.index'), request()->routeIs('admin.system-management.backups.*')],
                ['System Health', 'heart-pulse', route('admin.system-management.health.index'), request()->routeIs('admin.system-management.health.*')],
                ['DevOps Control', 'cpu', route('admin.system-management.devops.index'), request()->routeIs('admin.system-management.devops.*')],
                ['Live System Logs', 'terminal', route('admin.system-management.logs.index'), request()->routeIs('admin.system-management.logs.*')],
                ['Integrations', 'plug', route('admin.system-management.integrations.index'), request()->routeIs('admin.system-management.integrations.*')],
            ],
        ],
        [
            'active' => request()->routeIs('admin.settings.*') || request()->routeIs('admin.ms-sync.*'),
            'icon' => 'settings', 'iconClass' => 'text-lime-600', 'headerClass' => 'text-lime-700', 'activeClass' => 'sidebar-link-active-lime', 'title' => 'Settings',
            'links' => [
                ['General Settings', 'sliders', route('admin.settings.discounts'), request()->routeIs('admin.settings.*')],
                ['School Profile', 'school', route('admin.settings.discounts'), false],
                ['Email / Notifications', 'mail', route('admin.settings.discounts'), false],
                ['MS365 Sync', 'refresh-cw', route('admin.ms-sync.index'), request()->routeIs('admin.ms-sync.*')],
                ['Integrations', 'plug', route('admin.ms-sync.index'), false],
                ['Branding', 'palette', route('admin.settings.discounts'), false],
                ['System Preferences', 'settings-2', route('admin.settings.discounts'), false],
            ],
        ],
    ];

    $isTeacherAdminViewer = Auth::user()?->isTeacherAdminViewer() ?? false;

    if ($isTeacherAdminViewer) {
        $workspaceSections = array_values(array_filter($workspaceSections, function ($section) {
            return in_array($section['title'], ['Applications', 'Students', 'Support Center'], true);
        }));

        $workspaceSections = array_map(function ($section) {
            $section['links'] = array_values(array_filter($section['links'], function ($link) use ($section) {
                return ($section['title'] === 'Applications' && $link[0] === 'Enrollment Applications')
                    || ($section['title'] === 'Students' && ($link[0] === 'Student Records' || $link[0] === 'Reports & Analytics' || $link[0] === 'Call Attendance'))
                    || ($section['title'] === 'Support Center');
            }));

            return $section;
        }, $workspaceSections);
    }

    $isAdminUser = in_array(Auth::user()?->role, ['super_admin', 'admin']);
    if (!$isAdminUser) {
        $workspaceSections = array_map(function ($section) {
            if ($section['title'] === 'System Management') {
                $section['links'] = array_values(array_filter($section['links'], function ($link) {
                    return $link[0] !== 'DevOps Control';
                }));
            }
            return $section;
        }, $workspaceSections);
    }

    $workspaceSections = array_filter($workspaceSections, function($section) {
        return !($section['hidden'] ?? false);
    });

    $activeSection = collect($workspaceSections)->firstWhere('active', true) ?? $workspaceSections[0];
@endphp

<aside id="default-sidebar"
       class="admin-sidebar fixed left-0 z-40 w-64 translate-x-0 border-r border-gray-200 bg-white transition-transform dark:border-gray-700 dark:bg-gray-800"
       aria-label="Sidebar">
    <div class="flex h-full flex-col px-3 py-4">
        @unless ($isTeacherAdminViewer || request()->routeIs('admin.dashboard'))
            <a href="{{ route('admin.dashboard') }}" class="module-dashboard-link mb-3">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                <span>Main Dashboard</span>
            </a>
        @endunless

        <div class="sidebar-section-container">
            <div class="sidebar-section-header">
                <i data-lucide="{{ $activeSection['icon'] }}" class="sidebar-section-header-icon {{ $activeSection['iconClass'] }}"></i>
                <span class="{{ $activeSection['headerClass'] }} font-extrabold text-xs tracking-wider uppercase">{{ $activeSection['title'] }} Workspace</span>
            </div>
            <div class="space-y-1">
                @foreach ($activeSection['links'] as [$label, $icon, $href, $active])
                    <a href="{{ $href }}" class="sidebar-link{{ $active ? ' '.$activeSection['activeClass'] : '' }}">
                        <i data-lucide="{{ $icon }}"></i>
                        <span>{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="sidebar-profile-card mt-auto">
            <div class="sidebar-profile-info">
                <span class="sidebar-profile-label">Signed in as</span>
                <span class="sidebar-profile-name" title="{{ Auth::user()->name ?? 'Administrator' }}">
                    {{ Auth::user()->name ?? 'Administrator' }}
                </span>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}" class="view-only-allowed">
                @csrf
                <button type="submit" class="sidebar-logout-btn">
                    <i data-lucide="log-out" class="h-4 w-4"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </div>
</aside>
