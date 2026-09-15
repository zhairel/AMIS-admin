<?php

use App\Http\Controllers\Academic\GradeLevelController;
use App\Http\Controllers\Academic\SchoolYearController;
use App\Http\Controllers\Admin\Academic\SectionTrackingController;
use App\Http\Controllers\Admin\Academic\TeacherTrackingController;
use App\Http\Controllers\Admin\AcademicRoomController;
use App\Http\Controllers\Admin\AcademicSectionController;
use App\Http\Controllers\Admin\AcademicWorkspaceController;
use App\Http\Controllers\Admin\AccessControlController;
use App\Http\Controllers\Admin\AdminAnnouncementController;
use App\Http\Controllers\Admin\AdminEnrollmentSettingsController;
use App\Http\Controllers\Admin\AdministrationController;
use App\Http\Controllers\Admin\AdminSupportTicketController;
use App\Http\Controllers\Admin\ApplicantController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnrollmentAnalyticsController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\EnrollmentReportController;
use App\Http\Controllers\Admin\FacultyAttendanceController;
use App\Http\Controllers\Admin\Finance\FinanceController;
use App\Http\Controllers\Admin\Finance\MonthlyPaymentReminderController;
use App\Http\Controllers\Admin\GoogleDriveAuthController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\RequirementController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SecurityWorkspaceController;
use App\Http\Controllers\Admin\StudentBatchImportController;
use App\Http\Controllers\Admin\StudentComparisonController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentDashboardController;
use App\Http\Controllers\Admin\StudentExportController;
use App\Http\Controllers\Admin\StudentIdController;
use App\Http\Controllers\Admin\StudentPhotoController;
use App\Http\Controllers\Admin\StudentPrintController;
use App\Http\Controllers\Admin\StudentArchiveController;
use App\Http\Controllers\Admin\StudentRosterController;
use App\Http\Controllers\Admin\StudentUnassignedController;
use App\Http\Controllers\Admin\System\SystemBackupController;
use App\Http\Controllers\Admin\System\SystemDevOpsController;
use App\Http\Controllers\Admin\System\SystemHealthController;
use App\Http\Controllers\Admin\System\SystemLogController;
use App\Http\Controllers\Admin\SystemNotificationController;
use App\Http\Controllers\AdminAcademicController;
use App\Http\Controllers\AdminAcademicSubjectController;
use App\Http\Controllers\AdminAcademicTeacherController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminBackupController;
use App\Http\Controllers\AdminClassScheduleController;
use App\Http\Controllers\AdminDiscountSettingsController;
use App\Http\Controllers\AdminEbookController;
use App\Http\Controllers\AdminMicrosoftTeamsRosterController;
use App\Http\Controllers\AdminMsSyncController;
use App\Http\Controllers\AdminMsTeamsController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AdminStudentAccountController;
use App\Http\Controllers\AdminStudentFamilyController;
use App\Http\Controllers\AdminStudentProcessController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\IdentityRoutingController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.login'));

Route::name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.store');
        Route::post('/login/send-otp', [AdminAuthController::class, 'sendOtp'])
            ->middleware('throttle:10,1')
            ->name('login.otp.send');
        Route::post('/login/verify-otp', [AdminAuthController::class, 'verifyOtp'])
            ->middleware('throttle:20,1')
            ->name('login.otp.verify');
    });

    Route::post('/logout', [AdminAuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');

    Route::get('/auth/microsoft', [AdminAuthController::class, 'microsoftRedirect'])
        ->middleware('throttle:5,1')
        ->name('microsoft.redirect');
    Route::get('/auth/microsoft/callback', [AdminAuthController::class, 'microsoftCallback'])
        ->middleware('throttle:10,1')
        ->name('microsoft.callback');

    Route::post('/api/identity/login', [IdentityRoutingController::class, 'login'])
        ->name('identity.login');
    Route::post('/api/identity/link', [IdentityRoutingController::class, 'link'])
        ->middleware('auth')
        ->name('identity.link');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // System Notifications API
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [SystemNotificationController::class, 'index'])->name('index');
            Route::post('/{id}/read', [SystemNotificationController::class, 'markAsRead'])->name('read');
            Route::post('/mark-all-read', [SystemNotificationController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::delete('/clear', [SystemNotificationController::class, 'clearAll'])->name('clear');
        });

        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            Route::get('/scanner', [AttendanceController::class, 'scanner'])->name('scanner');
            Route::post('/scan', [AttendanceController::class, 'scan'])->name('scan');
            Route::get('/manual', [AttendanceController::class, 'manual'])->name('manual');
            Route::post('/manual', [AttendanceController::class, 'storeManual'])->name('manual.store');
            Route::get('/reports', [AttendanceController::class, 'reports'])->name('reports');
        });

        Route::prefix('faculty-attendance')->name('faculty-attendance.')->group(function () {
            Route::get('/', [FacultyAttendanceController::class, 'index'])->name('index');
            Route::post('/import', [FacultyAttendanceController::class, 'import'])->name('import');
            Route::post('/users', [FacultyAttendanceController::class, 'storeUser'])->name('users.store');
            Route::post('/users/{id}/delete', [FacultyAttendanceController::class, 'deleteUser'])->name('users.delete');
            Route::get('/users/download', [FacultyAttendanceController::class, 'downloadUsers'])->name('users.download');
            Route::post('/link', [FacultyAttendanceController::class, 'linkBiometricProfile'])->name('link');
            Route::post('/remarks', [FacultyAttendanceController::class, 'storeRemark'])->name('remarks.store');
        });

        Route::prefix('ebook')->name('ebook.')->group(function () {
            Route::get('/', [AdminEbookController::class, 'index'])->name('index');
            Route::get('/create', [AdminEbookController::class, 'create'])->name('create');
            Route::post('/', [AdminEbookController::class, 'store'])->name('store');
            Route::get('/tracking', [AdminEbookController::class, 'tracking'])->name('tracking');
            Route::get('/{ebook}/download', [AdminEbookController::class, 'download'])->name('download');
            Route::get('/{ebook}/edit', [AdminEbookController::class, 'edit'])->name('edit');
            Route::put('/{ebook}', [AdminEbookController::class, 'update'])->name('update');
            Route::delete('/{ebook}', [AdminEbookController::class, 'destroy'])->name('destroy');
            Route::get('/{ebook}/readers', [AdminEbookController::class, 'getReaders'])->name('readers');
        });

        Route::prefix('enrollment')->name('enrollment.')->group(function () {
            Route::get('/', [EnrollmentController::class, 'index'])->name('index');
            Route::get('/analytics', [EnrollmentAnalyticsController::class, 'analytics'])->name('analytics');
            Route::get('/reports', [EnrollmentAnalyticsController::class, 'reports'])->name('reports');
            Route::get('/reports/export', [EnrollmentAnalyticsController::class, 'export'])->name('reports.export');
            Route::get('/masters-list', [EnrollmentAnalyticsController::class, 'mastersList'])->name('masters-list');
            Route::get('/masters-list/export', [EnrollmentAnalyticsController::class, 'mastersListExport'])->name('masters-list.export');
        });

        Route::prefix('applications')->name('applications.')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.applications.dashboard'))->name('index');
            Route::get('/dashboard', [ApplicantController::class, 'dashboard'])->name('dashboard');
            Route::get('/enrollment', [ApplicantController::class, 'enrollment'])->name('enrollment');
            Route::get('/archive', [ApplicantController::class, 'archive'])->name('archive');
            Route::get('/review', [ApplicantController::class, 'review'])->name('review');
            Route::get('/requirements', [ApplicantController::class, 'requirements'])->name('requirements');
            Route::get('/approval-workflow', [ApplicantController::class, 'approval'])->name('approval');
            Route::get('/print-no-payment', [ApplicantController::class, 'printNoPayment'])->name('print-no-payment');
        });

        Route::prefix('applicants')->name('applicants.')->group(function () {
            Route::get('/', [ApplicantController::class, 'index'])->name('index');
            Route::post('/email-registry', [ApplicantController::class, 'emailRegistry'])->name('email-registry');
            Route::get('/{applicant}', [ApplicantController::class, 'show'])->name('show');
            Route::get('/{applicant}/review', [ApplicantController::class, 'reviewApplicant'])->name('review');
            Route::patch('/{applicant}/status', [ApprovalController::class, 'updateStatus'])->name('status');
            Route::patch('/{applicant}/document', [RequirementController::class, 'update'])->name('document');
            Route::patch('/{applicant}/discount', [ApplicantController::class, 'updateDiscount'])->name('discount');
            Route::post('/{applicant}/approve', [ApprovalController::class, 'approve'])->name('approve');
            Route::post('/{applicant}/approve-family', [ApprovalController::class, 'approveFamily'])->name('approve-family');
            Route::post('/{applicant}/send-welcome', [ApprovalController::class, 'resendOnboardingInbox'])->name('send-welcome');
            Route::post('/{applicant}/resend-onboarding-inbox', [ApprovalController::class, 'resendOnboardingInbox'])->name('resend-onboarding-inbox');
            Route::post('/{applicant}/verify-section', [ApprovalController::class, 'verifySection'])->name('verify-section');
            Route::post('/{id}/restore', [ApplicantController::class, 'restore'])->name('restore');
            Route::delete('/{id}/force-delete', [ApplicantController::class, 'forceDelete'])->name('force-delete');
            Route::delete('/{applicant}', [ApplicantController::class, 'destroy'])->name('destroy');
        });

        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/unassigned', [StudentUnassignedController::class, 'index'])->name('students.unassigned');
        Route::post('/students/unassigned/assign', [StudentUnassignedController::class, 'assign'])->name('students.unassigned.assign');
        Route::post('/students/unassigned/bulk-assign', [StudentUnassignedController::class, 'bulkAssign'])->name('students.unassigned.bulk-assign');
        Route::get('/students/unassigned/export-csv', [StudentUnassignedController::class, 'exportCsv'])->name('students.unassigned.export-csv');
        Route::get('/students/archive', [StudentArchiveController::class, 'index'])->name('students.archive');
        Route::post('/students/{student}/archive', [StudentArchiveController::class, 'archive'])->name('students.archive.store');
        Route::post('/students/{id}/restore', [StudentArchiveController::class, 'restore'])->name('students.archive.restore');
        Route::delete('/students/{id}/force-delete', [StudentArchiveController::class, 'forceDelete'])->name('students.archive.force-delete');
        Route::post('/students/archive/bulk-restore', [StudentArchiveController::class, 'bulkRestore'])->name('students.archive.bulk-restore');
        Route::post('/students/archive/bulk-archive', [StudentArchiveController::class, 'bulkArchive'])->name('students.archive.bulk-archive');
        Route::get('/students/dashboard', [StudentDashboardController::class, 'dashboard'])->name('students.dashboard');
        Route::get('/students/dashboard/sections/{section}/roster-print', [SectionController::class, 'rosterPrint'])->name('students.roster-print');
        Route::get('/students/dashboard/sections/{section}/id-roster-print', [SectionController::class, 'idRosterPrint'])->name('students.id-roster-print');
        Route::get('/students/history', [AdminStudentProcessController::class, 'history'])->name('students.history');
        Route::get('/students/accounts', [AdminStudentProcessController::class, 'accounts'])->name('students.accounts');
        Route::get('/students/audit-logs', [StudentComparisonController::class, 'auditLogs'])->name('students.audit-logs');
        Route::redirect('/students/documents', '/students')->name('students.documents');
        Route::get('/students/verification', [AdminStudentProcessController::class, 'verification'])->name('students.verification');
        Route::redirect('/students/promotions', '/students')->name('students.promotions');
        Route::get('/students/occupancy', [SectionController::class, 'occupancy'])->name('students.occupancy');
        Route::post('/students/occupancy/create-section', [SectionController::class, 'storeSection'])->name('students.occupancy.store-section');
        Route::post('/students/occupancy/bulk-json-import', [StudentBatchImportController::class, 'bulkJsonImport'])->name('students.occupancy.bulk-json-import');
        Route::post('/students/occupancy/preview-json-import', [StudentBatchImportController::class, 'previewJsonImport'])->name('students.occupancy.preview-json-import');
        Route::put('/students/occupancy/sections/{section}', [SectionController::class, 'updateSection'])->name('students.occupancy.update-section');
        Route::get('/students/occupancy/sections/{section}/manage', [SectionController::class, 'manageSection'])->name('students.occupancy.manage-section');
        Route::delete('/students/occupancy/sections/{section}', [SectionController::class, 'destroySection'])->name('students.occupancy.delete-section');
        Route::delete('/students/occupancy/grade/{grade}/delete-sections', [SectionController::class, 'destroyGradeSections'])->name('students.occupancy.delete-grade-sections');
        Route::post('/students/occupancy/sections/{section}/assign-students', [SectionController::class, 'assignStudentsToSection'])->name('students.occupancy.assign-students');
        Route::delete('/students/occupancy/sections/remove-student/{studentSection}', [SectionController::class, 'removeStudentFromSection'])->name('students.occupancy.remove-student');
        Route::get('/students/occupancy/grade/{grade}/roster-print', [SectionController::class, 'gradeRosterPrint'])->name('students.grade-roster-print');
        Route::get('/students/occupancy/grade/{grade}/id-print', [SectionController::class, 'gradeIdPrint'])->name('students.grade-id-print');
        Route::get('/students/reports', [EnrollmentReportController::class, 'reports'])->name('students.reports');
        Route::get('/students/attendance', [EnrollmentReportController::class, 'attendance'])->name('students.attendance');
        Route::get('/students/reports/data', [EnrollmentReportController::class, 'reportsData'])->name('students.reports.data');
        Route::get('/students/reports/enrollment-payments', [EnrollmentReportController::class, 'enrollmentPaymentsReportData'])->name('students.reports.enrollment-payments');
        Route::get('/students/reports/class-roster', [SectionController::class, 'classRosterData'])->name('students.reports.class-roster');
        Route::post('/students/reports/sync', [EnrollmentReportController::class, 'syncNow'])->name('students.reports.sync');
        Route::get('/students/reports/print-all', [SectionController::class, 'printAllRosters'])->name('students.print-all-rosters');
        Route::get('/google-drive/auth', [GoogleDriveAuthController::class, 'redirect'])->name('google-drive.auth');
        Route::get('/google-drive/callback', [GoogleDriveAuthController::class, 'callback'])->name('google-drive.callback');
        Route::get('/auth/google-drive/callback', [GoogleDriveAuthController::class, 'callback'])->name('google-drive.callback.auth');
        Route::post('/students/reports/sync-google-drive', [EnrollmentReportController::class, 'syncGoogleDrive'])->name('students.reports.sync-google-drive');
        Route::get('/students/families', [AdminStudentFamilyController::class, 'families'])->name('students.families');
        Route::get('/students/export-canva', [StudentExportController::class, 'exportCanva'])->name('students.export-canva');
        Route::get('/students/export-verification-db', [StudentExportController::class, 'exportVerificationDatabase'])->name('students.export-verification-db');
        Route::get('/students/download-docs-zip', [StudentExportController::class, 'downloadDocumentsZip'])->name('students.download-docs-zip');
        Route::get('/students/download-enrolment-forms-zip', [StudentExportController::class, 'downloadEnrolmentFormsZip'])->name('students.download-enrolment-forms-zip');
        Route::post('/students/start-batch-export', [StudentExportController::class, 'startBatchExport'])->name('students.start-batch-export');
        Route::get('/students/export-status/{id}', [StudentExportController::class, 'getBatchExportStatus'])->name('students.export-status');
        Route::get('/students/download-batch-export/{id}', [StudentExportController::class, 'downloadBatchExportFile'])->name('students.download-batch-export');
        Route::get('/students/comparison', [StudentComparisonController::class, 'comparison'])->name('students.comparison');
        Route::post('/students/comparison/sync', [StudentComparisonController::class, 'syncComparisonCsv'])->name('students.comparison.sync');
        Route::post('/students/comparison/update-field', [StudentController::class, 'updateField'])->name('students.comparison.update-field');
        Route::post('/students/bulk-print-list', [StudentPrintController::class, 'bulkPrintList'])->name('students.bulk-print-list');
        Route::get('/students/print-enrolment-forms-batch', [StudentPrintController::class, 'printEnrolmentFormsBatch'])->name('students.print-enrolment-forms-batch');
        Route::get('/students/print-export', [StudentPrintController::class, 'printExport'])->name('students.print-export');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::get('/students/{student}/id-editor', [StudentIdController::class, 'idEditor'])->name('students.id-editor');
        Route::get('/students/{student}/print-enrolment-form', [StudentPrintController::class, 'printEnrolmentForm'])->name('students.print-enrolment-form');
        Route::get('/students/{student}/official-enrollment-form/download', [StudentPrintController::class, 'downloadOfficialForm'])->name('students.official-enrollment-form.download');
        Route::get('/students/{student}/official-enrollment-form/view', [StudentPrintController::class, 'viewOfficialForm'])->name('students.official-enrollment-form.view');
        Route::get('/students/documents/{document}/download', [StudentPrintController::class, 'downloadDocument'])->name('students.documents.download');
        Route::get('/students/documents/{document}/view', [StudentPrintController::class, 'viewDocument'])->name('students.documents.view');
        Route::get('/students/{student}/preview-docx-enrolment-form', [StudentPrintController::class, 'previewDocxEnrolmentForm'])->name('students.preview-docx-enrolment-form');
        Route::post('/students/{student}/update-profile', [StudentController::class, 'updateProfile'])->name('students.update-profile');
        Route::post('/students/{student}/update-photo', [StudentPhotoController::class, 'updatePhoto'])->name('students.update-photo');
        Route::post('/students/{student}/update-section', [StudentRosterController::class, 'updateSection'])->name('students.update-section');
        Route::post('/students/{student}/update-status', [AdminStudentAccountController::class, 'updateStatus'])->name('students.update-status');
        Route::post('/students/{student}/update-email', [AdminStudentAccountController::class, 'updateEmail'])->name('students.update-email');
        Route::post('/students/{student}/update-id-font-sizes', [StudentIdController::class, 'updateIdFontSizes'])->name('students.update-id-font-sizes');
        Route::post('/students/{student}/delete-photo', [StudentPhotoController::class, 'deletePhoto'])->name('students.delete-photo');
        Route::post('/students/{student}/sync-microsoft-photo', [StudentPhotoController::class, 'syncMicrosoftPhoto'])->name('students.sync-microsoft-photo');
        Route::post('/students/{student}/toggle-requirements-lock', [StudentController::class, 'toggleRequirementsLock'])->name('students.toggle-requirements-lock');
        Route::post('/students/{student}/resend', [AdminStudentAccountController::class, 'resendCredentials'])->name('students.resend');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [FinanceController::class, 'dashboard'])->name('dashboard');

            Route::get('/payment-verification', [FinanceController::class, 'verificationIndex'])->name('verification.index');
            Route::get('/payment-verification/{receipt}', [FinanceController::class, 'verificationShow'])->name('verification.show');
            Route::get('/payment-verification/{receipt}/original', [FinanceController::class, 'originalReceipt'])->name('verification.original');
            Route::patch('/payment-verification/{receipt}', [FinanceController::class, 'verificationUpdate'])->name('verification.update');
            Route::post('/payment-verification/{receipt}/action', [FinanceController::class, 'verificationAction'])->name('verification.action');

            Route::get('/record-onsite-payment', [FinanceController::class, 'onsiteCreate'])->name('onsite.create');
            Route::post('/record-onsite-payment/check-duplicate', [FinanceController::class, 'onsiteDuplicateCheck'])->name('onsite.duplicate-check');
            Route::post('/record-onsite-payment', [FinanceController::class, 'onsiteStore'])->name('onsite.store');

            Route::get('/transactions', [FinanceController::class, 'transactionsIndex'])->name('transactions.index');
            Route::get('/transactions/{transaction}', [FinanceController::class, 'transactionsShow'])->name('transactions.show');

            Route::get('/family-accounts', [FinanceController::class, 'familiesIndex'])->name('families.index');
            Route::get('/family-accounts/{family}', [FinanceController::class, 'familiesShow'])->name('families.show');
            Route::post('/family-accounts/{family}/reset-demo', [FinanceController::class, 'resetDemoData'])->name('families.reset-demo');
            Route::post('/family-accounts/{family}/historical-payment', [FinanceController::class, 'storeHistoricalPayment'])->name('families.historical-payment');

            // Transaction SOA Edit, Void & Audit Details
            Route::get('/transactions/{transaction}/details', [FinanceController::class, 'getPaymentRecordDetails'])->name('transactions.details');
            Route::put('/transactions/{transaction}/update-record', [FinanceController::class, 'updatePaymentRecord'])->name('transactions.update-record');
            Route::post('/transactions/{transaction}/void-record', [FinanceController::class, 'voidPaymentRecord'])->name('transactions.void-record');

            // Manual Statement of Account (Finance Uploaded)
            Route::post('/students/{studentIdentifier}/manual-soa', [FinanceController::class, 'uploadManualSoa'])->name('manual-soa.upload');
            Route::get('/manual-soa/{soa}/view', [FinanceController::class, 'viewManualSoa'])->name('manual-soa.view');
            Route::get('/manual-soa/{soa}/download', [FinanceController::class, 'downloadManualSoa'])->name('manual-soa.download');
            Route::delete('/manual-soa/{soa}', [FinanceController::class, 'deleteManualSoa'])->name('manual-soa.delete');

            // Official Generated Statement of Account
            Route::get('/students/{studentIdentifier}/official-soa', [FinanceController::class, 'officialStudentSoa'])->name('students.official-soa');
            Route::post('/students/{studentIdentifier}/update-soa', [FinanceController::class, 'updateStudentSoa'])->name('students.update-soa');
            Route::post('/students/{studentIdentifier}/update-month-billing', [FinanceController::class, 'updateMonthBilling'])->name('students.update-month-billing');
            Route::post('/students/{studentIdentifier}/adjust-schedule', [FinanceController::class, 'adjustSchedule'])->name('students.adjust-schedule');

            Route::get('/official-receipts', [FinanceController::class, 'receiptsIndex'])->name('receipts.index');
            Route::get('/official-receipts/{receipt}', [FinanceController::class, 'receiptsShow'])->name('receipts.show');
            Route::get('/official-receipts/{receipt}/pdf', [FinanceController::class, 'receiptsPdf'])->name('receipts.pdf');

            Route::get('/reports', [FinanceController::class, 'reports'])->name('reports.index');
            Route::get('/reports/export', [FinanceController::class, 'reportsExport'])->name('reports.export');
            Route::get('/audit-log', [FinanceController::class, 'auditIndex'])->name('audit.index');

            // Monthly Payment Reminders (Finance -> Monthly Payment Reminder)
            Route::prefix('monthly-reminders')->name('monthly-reminders.')->group(function () {
                Route::get('/', [MonthlyPaymentReminderController::class, 'index'])->name('index');
                Route::post('/send', [MonthlyPaymentReminderController::class, 'sendBatch'])->name('send')->middleware('throttle:10,1');
                Route::post('/send-test', [MonthlyPaymentReminderController::class, 'sendTest'])->name('send-test')->middleware('throttle:15,1');
                Route::post('/reset', [MonthlyPaymentReminderController::class, 'resetBatch'])->name('reset');
                Route::get('/progress', [MonthlyPaymentReminderController::class, 'progress'])->name('progress');
                Route::get('/preview-email', [MonthlyPaymentReminderController::class, 'previewEmail'])->name('preview-email');
                Route::get('/history', [MonthlyPaymentReminderController::class, 'history'])->name('history');
                Route::post('/send-single/{familyId}', [MonthlyPaymentReminderController::class, 'sendSingle'])->name('send-single')->middleware('throttle:15,1');
            });
        });

        // Compatibility redirects for bookmarked legacy Finance URLs.
        Route::redirect('/payments', '/finance/payment-verification')->name('payments.index');
        Route::redirect('/soa', '/finance/family-accounts')->name('soa.index');

        // Enrollment-fee proof actions remain under Enrollment; they are not part of the retired monthly Finance UI.
        Route::get('/enrollment/payment-proofs/file', [AdminPaymentController::class, 'getReceiptFile'])->name('payments.receipt-file');
        Route::get('/enrollment/payment-proofs/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::patch('/enrollment/payment-proofs/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('payments.verify');
        Route::patch('/enrollment/payment-proofs/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');

        Route::get('/ms-sync', [AdminMsSyncController::class, 'index'])->name('ms-sync.index');
        Route::get('/ms-sync/data', [AdminMsSyncController::class, 'data'])->name('ms-sync.data');
        Route::post('/ms-sync/cleanup-test', [AdminMsSyncController::class, 'cleanupTestAccounts'])->name('ms-sync.cleanup-test');
        Route::post('/ms-sync/cleanup-portal', [AdminMsSyncController::class, 'cleanupPortalTestData'])->name('ms-sync.cleanup-portal');
        Route::post('/ms-sync/fix-guests', [AdminMsSyncController::class, 'fixGuests'])->name('ms-sync.fix-guests');
        Route::post('/ms-sync/retry-failed', [AdminMsSyncController::class, 'retryFailed'])->name('ms-sync.retry-failed');
        Route::post('/ms-sync/import-all', [AdminMsSyncController::class, 'importAll'])->name('ms-sync.import-all');
        Route::post('/ms-sync/import', [AdminMsSyncController::class, 'importFromAzure'])->name('ms-sync.import');
        Route::post('/ms-sync/delete-azure', [AdminMsSyncController::class, 'deleteFromAzure'])->name('ms-sync.delete-azure');
        Route::get('/ms-sync/students/{student}', [AdminMsSyncController::class, 'showStudentSyncRedirect'])->name('ms-sync.student.redirect');
        Route::post('/ms-sync/students/{student}', [AdminMsSyncController::class, 'syncStudent'])->name('ms-sync.student');
        Route::post('/ms-sync/sync-all-licenses', [AdminMsSyncController::class, 'syncAllLicenses'])->name('ms-sync.sync-all-licenses');

        Route::get('/admins', [AdminUserController::class, 'index'])->name('admins.index');
        Route::get('/admins/audit-logs', [AdminUserController::class, 'auditLogs'])->name('admins.audit-logs');
        Route::get('/admins/login-activity', [AdminUserController::class, 'loginActivity'])->name('admins.login-activity');
        Route::get('/admins/backups', [AdminBackupController::class, 'index'])->name('admins.backups');
        Route::post('/admins/backups', [AdminBackupController::class, 'create'])->name('admins.backups.create');
        Route::post('/admins/backups/full', [AdminBackupController::class, 'runFullBackup'])->name('admins.backups.full');
        Route::get('/admins/backups/{filename}/download', [AdminBackupController::class, 'download'])->name('admins.backups.download');
        Route::delete('/admins/backups/{filename}', [AdminBackupController::class, 'destroy'])->name('admins.backups.destroy');
        Route::post('/admins/backups/{filename}/google-drive', [AdminBackupController::class, 'uploadToDrive'])->name('admins.backups.google-drive');
        Route::post('/admins', [AdminUserController::class, 'store'])->name('admins.store');
        Route::get('/admins/{user}/edit', [AdminUserController::class, 'edit'])->name('admins.edit');
        Route::patch('/admins/{user}', [AdminUserController::class, 'update'])->name('admins.update');
        Route::patch('/admins/{user}/role', [AdminUserController::class, 'updateRole'])->name('admins.role');
        Route::patch('/admins/{user}/access', [AdminUserController::class, 'updateAccess'])->name('admins.access');
        Route::patch('/admins/{user}/accept', [AdminUserController::class, 'accept'])->name('admins.accept');
        Route::delete('/admins/{user}', [AdminUserController::class, 'destroy'])->name('admins.destroy');

        Route::get('/administration/users', [AdministrationController::class, 'usersIndex'])->name('administration.users.index');
        Route::get('/administration/users/create', [AdministrationController::class, 'usersCreate'])->name('administration.users.create');
        Route::post('/administration/users', [AdministrationController::class, 'usersStore'])->name('administration.users.store');
        Route::patch('/administration/users/{user}/status', [AdministrationController::class, 'usersStatus'])->name('administration.users.status');
        Route::get('/administration/users/{user}/security', [AdministrationController::class, 'usersSecurity'])->name('administration.users.security');
        Route::patch('/administration/users/{user}/security', [AdministrationController::class, 'usersSecurityUpdate'])->name('administration.users.security.update');
        Route::delete('/administration/users/{user}', [AdministrationController::class, 'usersDestroy'])->name('administration.users.destroy');

        Route::get('/website/announcements', [AdminAnnouncementController::class, 'index'])->name('website.announcements.index');
        Route::get('/website/announcements/create', [AdminAnnouncementController::class, 'create'])->name('website.announcements.create');
        Route::post('/website/announcements', [AdminAnnouncementController::class, 'store'])->name('website.announcements.store');
        Route::get('/website/announcements/{id}/edit', [AdminAnnouncementController::class, 'edit'])->name('website.announcements.edit');
        Route::put('/website/announcements/{id}', [AdminAnnouncementController::class, 'update'])->name('website.announcements.update');
        Route::delete('/website/announcements/{id}', [AdminAnnouncementController::class, 'destroy'])->name('website.announcements.destroy');

        Route::get('/access-control/roles', [AccessControlController::class, 'rolesIndex'])->name('access-control.roles.index');
        Route::post('/access-control/roles', [AccessControlController::class, 'rolesStore'])->name('access-control.roles.store');
        Route::patch('/access-control/roles/{role}', [AccessControlController::class, 'rolesUpdate'])->name('access-control.roles.update');
        Route::delete('/access-control/roles/{role}', [AccessControlController::class, 'rolesDestroy'])->name('access-control.roles.destroy');
        Route::get('/access-control/permissions', [AccessControlController::class, 'permissionsIndex'])->name('access-control.permissions.index');
        Route::post('/access-control/permissions', [AccessControlController::class, 'permissionsUpdate'])->name('access-control.permissions.update');
        Route::get('/access-control/assignment', [AccessControlController::class, 'assignmentIndex'])->name('access-control.assignment.index');
        Route::patch('/access-control/assignment/{user}', [AccessControlController::class, 'assignmentUpdate'])->name('access-control.assignment.update');
        Route::get('/access-control/policies', [AccessControlController::class, 'policiesIndex'])->name('access-control.policies.index');

        Route::get('/security-workspace/metrics', [SecurityWorkspaceController::class, 'securityMetrics'])->name('security-workspace.metrics');
        Route::get('/security-workspace/login-activity', [SecurityWorkspaceController::class, 'loginActivity'])->name('security-workspace.login-activity');
        Route::get('/security-workspace/sessions', [SecurityWorkspaceController::class, 'activeSessions'])->name('security-workspace.sessions.index');
        Route::post('/security-workspace/sessions/revoke', [SecurityWorkspaceController::class, 'revokeSession'])->name('security-workspace.sessions.revoke');
        Route::get('/security-workspace/events', [SecurityWorkspaceController::class, 'securityEvents'])->name('security-workspace.events.index');
        Route::get('/security-workspace/audit-logs', [SecurityWorkspaceController::class, 'auditLogs'])->name('security-workspace.audit-logs');
        Route::get('/security-workspace/alerts', [SecurityWorkspaceController::class, 'securityAlerts'])->name('security-workspace.alerts.index');

        // System Management Workspace
        Route::prefix('system-management')->name('system-management.')->group(function () {
            Route::get('/backups', [SystemBackupController::class, 'index'])->name('backups.index');
            Route::post('/backups/create', [SystemBackupController::class, 'create'])->name('backups.create');
            Route::post('/backups/trigger-full', [SystemBackupController::class, 'triggerFull'])->name('backups.trigger-full');
            Route::get('/backups/{filename}/download', [SystemBackupController::class, 'download'])->name('backups.download');
            Route::post('/backups/{filename}/google-drive', [SystemBackupController::class, 'uploadToDrive'])->name('backups.google-drive');
            Route::delete('/backups/{filename}', [SystemBackupController::class, 'destroy'])->name('backups.destroy');
            Route::post('/backups/restore', [SystemBackupController::class, 'restore'])->name('backups.restore');
            Route::post('/backups/schedule', [SystemBackupController::class, 'saveSchedule'])->name('backups.schedule');
            Route::post('/backups/prune', [SystemBackupController::class, 'pruneOldBackups'])->name('backups.prune');

            Route::get('/health', [SystemHealthController::class, 'index'])->name('health.index');
            Route::post('/health/test-email', [SystemHealthController::class, 'sendTestEmail'])->name('health.test-email');
            Route::post('/health/ping', [SystemHealthController::class, 'pingDiagnostics'])->name('health.ping');

            Route::post('/cache/clear', [SystemHealthController::class, 'clearCache'])->name('cache.clear');
            Route::post('/cache/warmup', [SystemHealthController::class, 'warmupCache'])->name('cache.warmup');

            Route::get('/logs', [SystemLogController::class, 'index'])->name('logs.index');
            Route::post('/logs/clear', [SystemLogController::class, 'clearLogs'])->name('logs.clear');

            Route::get('/devops', [SystemDevOpsController::class, 'index'])->name('devops.index');
            Route::post('/devops/db-optimize', [SystemDevOpsController::class, 'dbOptimize'])->name('devops.db-optimize');
            Route::post('/devops/maintenance', [SystemDevOpsController::class, 'toggleMaintenanceMode'])->name('devops.maintenance');
            Route::post('/devops/queue/retry', [SystemDevOpsController::class, 'retryFailedJobs'])->name('devops.queue.retry');
            Route::post('/devops/queue/flush', [SystemDevOpsController::class, 'flushFailedJobs'])->name('devops.queue.flush');

            Route::get('/integrations', [SystemLogController::class, 'integrationsIndex'])->name('integrations.index');
        });

        Route::get('/settings/discounts', [AdminDiscountSettingsController::class, 'edit'])->name('settings.discounts');
        Route::patch('/settings/discounts', [AdminDiscountSettingsController::class, 'update'])->name('settings.discounts.update');

        Route::get('/settings/enrollment', [AdminEnrollmentSettingsController::class, 'edit'])->name('settings.enrollment');
        Route::patch('/settings/enrollment', [AdminEnrollmentSettingsController::class, 'update'])->name('settings.enrollment.update');

        Route::prefix('academic')->name('academic.')->group(function () {
            Route::get('/', [AcademicWorkspaceController::class, 'dashboard'])->name('dashboard');
            Route::get('/dashboard', [AcademicWorkspaceController::class, 'dashboard'])->name('dashboard.index');
            Route::get('/schedule-copy', [AcademicWorkspaceController::class, 'scheduleCopy'])->name('schedule-copy');
            Route::get('/builder', [AcademicWorkspaceController::class, 'builder'])->name('builder');
            Route::get('/subjects', [AcademicWorkspaceController::class, 'subjects'])->name('subjects');
            Route::post('/subjects', [AdminAcademicSubjectController::class, 'store'])->name('subjects.store');
            Route::patch('/subjects/{subject}', [AdminAcademicSubjectController::class, 'update'])->name('subjects.update');
            Route::patch('/subjects/{subject}/archive', [AdminAcademicSubjectController::class, 'archive'])->name('subjects.archive');
            Route::patch('/subjects/{subject}/restore', [AdminAcademicSubjectController::class, 'restore'])->name('subjects.restore');
            Route::get('/curriculum', [AdminAcademicController::class, 'curriculum'])->name('curriculum');
            Route::get('/grade-levels', [GradeLevelController::class, 'index'])->name('grade-levels');
            Route::get('/class-advisory', [AdminAcademicController::class, 'classAdvisory'])->name('class-advisory');
            Route::post('/class-advisory', [AdminAcademicController::class, 'assignClassAdvisory'])->name('class-advisory.store');
            Route::get('/sections', [AcademicWorkspaceController::class, 'sections'])->name('sections');
            Route::post('/sections', [AcademicSectionController::class, 'store'])->name('sections.store');
            Route::patch('/sections/{section}', [AcademicSectionController::class, 'update'])->name('sections.update');
            Route::delete('/sections/{section}', [AcademicSectionController::class, 'destroy'])->name('sections.destroy');
            Route::get('/rooms', [AcademicWorkspaceController::class, 'rooms'])->name('rooms');
            Route::post('/rooms', [AcademicRoomController::class, 'store'])->name('rooms.store');
            Route::patch('/rooms/{room}', [AcademicRoomController::class, 'update'])->name('rooms.update');
            Route::delete('/rooms/{room}', [AcademicRoomController::class, 'destroy'])->name('rooms.destroy');
            Route::get('/teachers', [AcademicWorkspaceController::class, 'teachers'])->name('teachers');
            Route::post('/teachers', [AdminAcademicTeacherController::class, 'store'])->name('teachers.store');
            Route::patch('/teachers', [AdminAcademicTeacherController::class, 'update'])->name('teachers.update');
            Route::post('/teachers/resend', [AdminAcademicTeacherController::class, 'resendCredentials'])->name('teachers.resend');
            Route::patch('/teachers/{id}/subjects', [AdminAcademicTeacherController::class, 'updateSubjects'])->name('teachers.subjects.update');
            Route::get('/teachers/{id}', [AdminAcademicTeacherController::class, 'show'])->name('teachers.view');
            Route::post('/teachers/{id}/toggle-password', [AdminAcademicTeacherController::class, 'togglePasswordChanged'])->name('teachers.toggle-password');
            Route::delete('/teachers/{id}', [AdminAcademicTeacherController::class, 'destroy'])->name('teachers.destroy');
            Route::get('/schedules', [AcademicWorkspaceController::class, 'builder'])->name('schedules');
            Route::post('/schedules', [AdminClassScheduleController::class, 'store'])->name('schedules.store');
            Route::patch('/schedules/{schedule}', [AdminClassScheduleController::class, 'update'])->name('schedules.update');
            Route::delete('/schedules/{schedule}', [AdminClassScheduleController::class, 'destroy'])->name('schedules.destroy');
            Route::patch('/schedules/sections/{section}/publish', [AdminClassScheduleController::class, 'togglePublish'])->name('schedules.sections.publish');
            Route::get('/schedules/sections/{section}/json', [AdminClassScheduleController::class, 'exportJson'])->name('schedules.sections.json.get');
            Route::post('/schedules/sections/{section}/json', [AdminClassScheduleController::class, 'importJson'])->name('schedules.sections.json');
            Route::get('/school-years', [SchoolYearController::class, 'index'])->name('school-years');
            Route::get('/calendar', [AdminAcademicController::class, 'calendar'])->name('calendar');
            Route::get('/operations', [AdminAcademicController::class, 'operations'])->name('operations');
            Route::get('/workload', [AcademicWorkspaceController::class, 'workload'])->name('workload');
            Route::get('/teacher-tracking', [TeacherTrackingController::class, 'index'])->name('teacher-tracking');
            Route::post('/teacher-tracking/{sectionSubject}/approve-grades', [TeacherTrackingController::class, 'approveGrades'])->name('teacher-tracking.approve');
            Route::get('/section-tracking', [SectionTrackingController::class, 'index'])->name('section-tracking');
            Route::get('/reports', [AcademicWorkspaceController::class, 'reports'])->name('reports');
            Route::get('/reports/export', [AcademicWorkspaceController::class, 'export'])->name('reports.export');

            Route::get('/school-years-list', [SchoolYearController::class, 'index'])->name('school-years.index');
            Route::get('/school-years-list/create', [SchoolYearController::class, 'create'])->name('school-years.create');
            Route::post('/school-years-list', [SchoolYearController::class, 'store'])->name('school-years.store');
            Route::get('/school-years-list/{school_year}/edit', [SchoolYearController::class, 'edit'])->name('school-years.edit');
            Route::post('/school-years-list/{school_year}', [SchoolYearController::class, 'update'])->name('school-years.update');
            Route::post('/school-years-list/{school_year}/toggle-active', [SchoolYearController::class, 'toggleActive'])->name('school-years.toggle-active');
            Route::post('/school-years-list/{school_year}/toggle-status', [SchoolYearController::class, 'toggleStatus'])->name('school-years.toggle-status');

            Route::get('/grade-levels-list', [GradeLevelController::class, 'index'])->name('grade-levels.index');
            Route::get('/grade-levels-list/create', [GradeLevelController::class, 'create'])->name('grade-levels.create');
            Route::post('/grade-levels-list', [GradeLevelController::class, 'store'])->name('grade-levels.store');
            Route::get('/grade-levels-list/{grade_level}/edit', [GradeLevelController::class, 'edit'])->name('grade-levels.edit');
            Route::post('/grade-levels-list/{grade_level}', [GradeLevelController::class, 'update'])->name('grade-levels.update');
            Route::post('/grade-levels-list/{grade_level}/toggle-active', [GradeLevelController::class, 'toggleActive'])->name('grade-levels.toggle-active');
        });

        Route::prefix('ms-teams')->name('ms-teams.')->group(function () {
            Route::get('/', [AdminMsTeamsController::class, 'index'])->name('index');
            Route::get('/roster', [AdminMsTeamsController::class, 'roster'])->name('roster');
            Route::get('/structure', [AdminMsTeamsController::class, 'structure'])->name('structure');
            Route::get('/structure/data', [AdminMsTeamsController::class, 'structureData'])->name('structure.data');
            Route::post('/', [AdminMsTeamsController::class, 'store'])->name('store');
            Route::post('/store-single', [AdminMsTeamsController::class, 'storeSingle'])->name('store-single');
            Route::post('/fix-admin-access', [AdminMsTeamsController::class, 'fixAdminAccess'])->name('fix-access');
            Route::post('/fix-team-ownership', [AdminMsTeamsController::class, 'fixTeamOwnership'])->name('fix-ownership');
            Route::post('/fix-guest-students', [AdminMsTeamsController::class, 'fixGuestStudents'])->name('fix-guests');
            Route::post('/students/{student}/enroll', [AdminMsTeamsController::class, 'enrollStudent'])->name('enroll');
            Route::get('/students/search', [AdminMsTeamsController::class, 'searchStudents'])->name('students.search');
            Route::post('/{section}/students/assign', [AdminMsTeamsController::class, 'assignStudent'])->name('students.assign');
            Route::delete('/{section}/students/{student}', [AdminMsTeamsController::class, 'removeStudent'])->name('students.remove');
            Route::patch('/subjects/{subject}', [AdminMsTeamsController::class, 'updateSubject'])->name('subjects.update');
            Route::post('/subjects/{subject}/update', [AdminMsTeamsController::class, 'updateSubject'])->name('subjects.update-post');
            Route::delete('/subjects/{subject}', [AdminMsTeamsController::class, 'destroySubject'])->name('subjects.destroy');
            Route::post('/subjects/{subject}/invite-teacher', [AdminMsTeamsController::class, 'inviteTeacher'])->name('subjects.invite-teacher');
            Route::get('/{section}', [AdminMsTeamsController::class, 'show'])->name('show');
            Route::patch('/{section}', [AdminMsTeamsController::class, 'update'])->name('update');
            Route::post('/{section}/update', [AdminMsTeamsController::class, 'update'])->name('update-post');
            Route::delete('/{section}', [AdminMsTeamsController::class, 'destroy'])->name('destroy');
            Route::post('/{section}/subjects', [AdminMsTeamsController::class, 'storeSubject'])->name('subjects.store');
            Route::post('/{section}/retry-team', [AdminMsTeamsController::class, 'retryTeam'])->name('retry-team');
            Route::post('/{section}/sync-advisor', [AdminMsTeamsController::class, 'syncAdvisor'])->name('sync-advisor');
        });

        Route::prefix('microsoft-integration')
            ->name('microsoft-roster.')
            ->middleware('can:manage-microsoft-rosters')
            ->group(function () {
                Route::get('/accounts', [AdminMicrosoftTeamsRosterController::class, 'accounts'])->name('accounts');
                Route::get('/teams', [AdminMicrosoftTeamsRosterController::class, 'index'])->name('index');
                Route::post('/teams/sync', [AdminMicrosoftTeamsRosterController::class, 'syncAll'])->name('sync');
                Route::get('/teams/status', [AdminMicrosoftTeamsRosterController::class, 'status'])->name('status');
                Route::get('/teams/export/{format}', [AdminMicrosoftTeamsRosterController::class, 'exportTeams'])->name('export');
                Route::get('/teams/{team}/roster/export/{format}', [AdminMicrosoftTeamsRosterController::class, 'exportRoster'])->name('roster.export');
                Route::get('/teams/{team}/raw/download', [AdminMicrosoftTeamsRosterController::class, 'rawDownload'])->name('raw.download');
                Route::get('/teams/{team}/raw', [AdminMicrosoftTeamsRosterController::class, 'raw'])->name('raw');
                Route::post('/teams/{team}/sync', [AdminMicrosoftTeamsRosterController::class, 'syncTeam'])->name('team.sync');
                Route::get('/teams/{team}', [AdminMicrosoftTeamsRosterController::class, 'show'])->name('show');

                Route::get('/mappings', [AdminMicrosoftTeamsRosterController::class, 'mappings'])->name('mappings');
                Route::get('/mappings/{team}/edit', [AdminMicrosoftTeamsRosterController::class, 'editMapping'])->name('mappings.edit');
                Route::put('/mappings/{team}', [AdminMicrosoftTeamsRosterController::class, 'updateMapping'])->name('mappings.update');
                Route::post('/mappings/{team}/ignore', [AdminMicrosoftTeamsRosterController::class, 'ignoreMapping'])->name('mappings.ignore');
                Route::delete('/mappings/{team}', [AdminMicrosoftTeamsRosterController::class, 'destroyMapping'])->name('mappings.destroy');

                Route::get('/unmatched', [AdminMicrosoftTeamsRosterController::class, 'unmatched'])->name('unmatched');
                Route::get('/unmatched/export/{format}', [AdminMicrosoftTeamsRosterController::class, 'exportUnmatched'])->name('unmatched.export');
                Route::get('/memberships/{membership}/review', [AdminMicrosoftTeamsRosterController::class, 'reviewMatch'])->name('matches.review');
                Route::post('/memberships/{membership}/match', [AdminMicrosoftTeamsRosterController::class, 'storeManualMatch'])->name('matches.store');
                Route::delete('/memberships/{membership}/match', [AdminMicrosoftTeamsRosterController::class, 'removeManualMatch'])->name('matches.destroy');
                Route::post('/memberships/{membership}/ignore', [AdminMicrosoftTeamsRosterController::class, 'ignoreAccount'])->name('matches.ignore');

                Route::get('/history', [AdminMicrosoftTeamsRosterController::class, 'history'])->name('history');
                Route::get('/history/{run}', [AdminMicrosoftTeamsRosterController::class, 'historyShow'])->name('history.show');
            });
        Route::prefix('support')->name('support.')->group(function () {
            Route::get('/', [AdminSupportTicketController::class, 'index'])->name('index');
            Route::get('/settings', [AdminSupportTicketController::class, 'settings'])->name('settings');
            Route::post('/settings', [AdminSupportTicketController::class, 'saveSettings'])->name('settings.save');
            Route::get('/screenshot', [AdminSupportTicketController::class, 'viewScreenshot'])->name('screenshot');
            Route::get('/{ticket}', [AdminSupportTicketController::class, 'show'])->name('show');
            Route::post('/{ticket}/reply', [AdminSupportTicketController::class, 'reply'])->name('reply');
            Route::patch('/{ticket}/status', [AdminSupportTicketController::class, 'updateStatus'])->name('status');
        });
        Route::prefix('registrations')->name('registrations.')->group(function () {
            Route::redirect('/halaqah/approved', '/admin/registrations/halaqah?tab=students')->name('halaqah.approved');
            Route::get('/halaqah', [RegistrationController::class, 'halaqah'])->name('halaqah');
            Route::get('/halaqah-parents', [RegistrationController::class, 'halaqahParents'])->name('halaqah-parents');
            Route::patch('/halaqah/{id}/toggle', [RegistrationController::class, 'toggleStatus'])->name('halaqah.toggle');
            Route::delete('/halaqah/{id}', [RegistrationController::class, 'destroy'])->name('halaqah.destroy');
        });
    });
});
