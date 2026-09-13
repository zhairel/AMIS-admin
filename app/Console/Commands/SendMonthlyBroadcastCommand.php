<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminderMail;
use App\Mail\Term1PtcAnnouncementMail;
use App\Models\MonthlyPaymentReminder;
use App\Services\System\SmartSmtpRotatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMonthlyBroadcastCommand extends Command
{
    protected $signature = 'email:send-monthly-broadcast
                            {--preview= : Send test preview of BOTH separate emails to this email address}
                            {--type=all : Broadcast type: all (both), ptc (PTC only), payment (Payment reminder only)}
                            {--send : Actually dispatch emails to parents and students}
                            {--batch=200 : Maximum number of emails to send in this run to respect rate limits}
                            {--delay=1 : Delay in seconds between each email dispatch}
                            {--force : Skip interactive confirmation}';

    protected $description = 'Dispatch or preview the Term 1 PTC Announcement and Monthly Payment Reminder as two separate inbox emails with strict deduplication and rate-limit batching.';

    public function handle(SmartSmtpRotatorService $rotator)
    {
        $billingMonth = '2026-09';

        $this->info('========================================================================');
        $this->info('  AL MUNAWWARA BROADCAST SYSTEM (OPTION A + B • DEDUPLICATION ENABLED)  ');
        $this->info('========================================================================');

        // 1. Resolve Active Students and their Parents
        $activeEnrollments = DB::table('students')
            ->join('enrollment_applicants', 'students.enrollment_applicant_id', '=', 'enrollment_applicants.id')
            ->where(function ($q) {
                $q->whereIn('enrollment_applicants.status', ['approved', 'enrolled', 'active'])
                    ->orWhereNotNull('students.student_number');
            })
            ->select(
                'students.id as student_id',
                'students.student_number',
                'students.school_email',
                'students.grade_level',
                'enrollment_applicants.id as applicant_id',
                'enrollment_applicants.first_name',
                'enrollment_applicants.last_name',
                'enrollment_applicants.parent_email',
                'enrollment_applicants.mother_first_name',
                'enrollment_applicants.mother_last_name',
                'enrollment_applicants.father_first_name',
                'enrollment_applicants.father_last_name',
                'enrollment_applicants.emergency_name'
            )
            ->get();

        $destinationInboxes = [];

        foreach ($activeEnrollments as $row) {
            $studentName = trim("{$row->first_name} {$row->last_name}");
            $parentName = trim("{$row->mother_first_name} {$row->mother_last_name}");
            if (empty($parentName)) {
                $parentName = trim("{$row->father_first_name} {$row->father_last_name}");
            }
            if (empty($parentName) && !empty($row->emergency_name)) {
                $parentName = trim($row->emergency_name);
            }
            if (empty($parentName)) {
                $parentName = "Parent of {$studentName}";
            }

            // A. Parent Inbox
            $parentEmail = strtolower(trim((string) $row->parent_email));
            if (!empty($parentEmail) && filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
                if (!isset($destinationInboxes[$parentEmail])) {
                    $destinationInboxes[$parentEmail] = [
                        'type' => 'Parent',
                        'name' => $parentName,
                        'email' => $parentEmail,
                        'student_name' => $studentName,
                        'grade_level' => $row->grade_level,
                        'family_id' => 'APP-' . $row->applicant_id,
                    ];
                }
            }

            // B. Student School Inbox
            $studentEmail = strtolower(trim((string) $row->school_email));
            if (!empty($studentEmail) && filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
                if (!isset($destinationInboxes[$studentEmail])) {
                    $destinationInboxes[$studentEmail] = [
                        'type' => 'Student',
                        'name' => $studentName,
                        'email' => $studentEmail,
                        'student_name' => $studentName,
                        'grade_level' => $row->grade_level,
                        'family_id' => 'STU-' . $row->student_id,
                    ];
                }
            }
        }

        $totalInboxes = count($destinationInboxes);

        // 2. Query Already Sent Records for Billing Month 2026-09 to PREVENT DOUBLE SENDING
        $sentPtcEmails = MonthlyPaymentReminder::where('billing_month', $billingMonth)
            ->where('reminder_type', 'term1_ptc_announcement')
            ->where('status', MonthlyPaymentReminder::STATUS_SENT)
            ->pluck('parent_email')
            ->map(fn($e) => strtolower(trim($e)))
            ->flip()
            ->toArray();

        $sentPaymentEmails = MonthlyPaymentReminder::where('billing_month', $billingMonth)
            ->where('reminder_type', 'monthly_payment_reminder')
            ->where('status', MonthlyPaymentReminder::STATUS_SENT)
            ->pluck('parent_email')
            ->map(fn($e) => strtolower(trim($e)))
            ->flip()
            ->toArray();

        $this->info("Destination Inboxes Status (Cycle {$billingMonth}):");
        $this->info("  • Total Target Inboxes: {$totalInboxes}");
        $this->info("  • Email 1 (PTC Announcement) Already Sent: " . count($sentPtcEmails));
        $this->info("  • Email 2 (Payment Reminder) Already Sent: " . count($sentPaymentEmails));
        $this->info('------------------------------------------------------------------------');

        // 3. Handle Preview Mode
        $previewAddress = $this->option('preview');
        if ($previewAddress) {
            $this->warn("\n🚀 SENDING PREVIEW TO: {$previewAddress}");
            $this->info("Sending 2 SEPARATE emails to verify inbox formatting and anti-threading...");

            try {
                // Email 1: Term 1 PTC Announcement
                $this->comment("1. Dispatching Email 1: Term 1 Special Awards Recognition & PTC Announcement...");
                $ptcMail = new Term1PtcAnnouncementMail(
                    recipientName: 'Valued AMIS Family',
                    studentName: 'Sample Student',
                    gradeLevel: 'Grade 1',
                    dispatchRef: 'PREVIEW'
                );
                Mail::to($previewAddress)->send($ptcMail);
                $this->info("   ✔ Email 1 sent successfully!");

                sleep(2);

                // Email 2: Monthly Payment Reminder (with 3 pictures)
                $this->comment("2. Dispatching Email 2: Monthly Payment Reminder (with 3 picture posters)...");
                $paymentMail = new PaymentReminderMail(
                    recipientName: 'Valued AMIS Family',
                    billingMonth: $billingMonth,
                    dispatchRef: 'PREVIEW'
                );
                Mail::to($previewAddress)->send($paymentMail);
                $this->info("   ✔ Email 2 sent successfully!");

                $this->info("\n🎉 PREVIEW DISPATCH COMPLETE!");
                $this->info("Check your inbox at {$previewAddress}. You should receive 2 distinct, unthreaded emails.");

                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->error("ERROR during preview dispatch: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        // 4. Handle Actual Sending with Deduplication and Safe Batch Limits
        $actuallySend = $this->option('send');
        if ($actuallySend) {
            $broadcastType = $this->option('type'); // all, ptc, payment
            $batchLimit = (int) $this->option('batch');
            $delaySeconds = (int) $this->option('delay');

            // Filter out already sent inboxes
            $pendingInboxes = [];
            foreach ($destinationInboxes as $email => $data) {
                $needsPtc = in_array($broadcastType, ['all', 'ptc']) && !isset($sentPtcEmails[$email]);
                $needsPayment = in_array($broadcastType, ['all', 'payment']) && !isset($sentPaymentEmails[$email]);

                if ($needsPtc || $needsPayment) {
                    $pendingInboxes[$email] = array_merge($data, [
                        'needs_ptc' => $needsPtc,
                        'needs_payment' => $needsPayment,
                    ]);
                }
            }

            $totalPending = count($pendingInboxes);

            if ($totalPending === 0) {
                $this->info("✔ ALL INBOXES HAVE ALREADY RECEIVED THEIR BROADCAST EMAILS! No double sends needed.");
                return Command::SUCCESS;
            }

            $this->warn("\nPending inboxes to send: {$totalPending}");
            $this->info("Batch limit for this run: {$batchLimit} inboxes (up to " . ($batchLimit * 2) . " emails) (auto-resumes every 2 hours via cron)");

            if (!$this->option('force')) {
                if (!$this->confirm("Proceed with sending the next batch of up to {$batchLimit} inboxes?")) {
                    $this->warn('Operation cancelled.');
                    return Command::SUCCESS;
                }
            }

            $batch = array_slice($pendingInboxes, 0, $batchLimit, true);
            $sentCount = 0;
            $failedCount = 0;
            $rateLimitHit = false;

            $bar = $this->output->createProgressBar(count($batch));
            $bar->start();

            foreach ($batch as $email => $rec) {
                // A. Send Email 1: PTC Announcement
                if ($rec['needs_ptc']) {
                    try {
                        $ptcMail = new Term1PtcAnnouncementMail(
                            recipientName: $rec['name'],
                            studentName: $rec['student_name'],
                            gradeLevel: $rec['grade_level']
                        );

                        $sendRes = $rotator->sendMail($email, $ptcMail);

                        // Record in DB as SENT to strictly guarantee NO DOUBLE SEND
                        MonthlyPaymentReminder::updateOrCreate(
                            [
                                'billing_month' => $billingMonth,
                                'reminder_type' => 'term1_ptc_announcement',
                                'parent_email'  => $email,
                            ],
                            [
                                'family_id'       => $rec['family_id'],
                                'parent_name'     => $rec['name'],
                                'student_names'   => $rec['student_name'],
                                'student_count'   => 1,
                                'status'          => MonthlyPaymentReminder::STATUS_SENT,
                                'sent_at'         => now(),
                                'mail_transport'  => $sendRes['mailer_used'] ?? 'smtp',
                                'smtp_message_id' => 'ptc-' . time() . '-' . substr(md5($email), 0, 8),
                            ]
                        );

                        $sentCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        $errorMsg = $e->getMessage();
                        Log::error("Broadcast Error (PTC) to {$email}: {$errorMsg}");

                        // Handle transient socket connection limit (421) by purging connection and retrying once
                        if (str_contains(strtolower($errorMsg), '421') || str_contains(strtolower($errorMsg), 'too many messages')) {
                            try {
                                foreach ($rotator->getMailerPool() as $m) {
                                    try { app('mail.manager')->purge($m); } catch (\Throwable) {}
                                }
                                sleep(3);
                                $sendRes = $rotator->sendMail($email, $ptcMail);
                                MonthlyPaymentReminder::updateOrCreate(
                                    [
                                        'billing_month' => $billingMonth,
                                        'reminder_type' => 'term1_ptc_announcement',
                                        'parent_email'  => $email,
                                    ],
                                    [
                                        'family_id'       => $rec['family_id'],
                                        'parent_name'     => $rec['name'],
                                        'student_names'   => $rec['student_name'],
                                        'student_count'   => 1,
                                        'status'          => MonthlyPaymentReminder::STATUS_SENT,
                                        'sent_at'         => now(),
                                        'mail_transport'  => $sendRes['mailer_used'] ?? 'smtp',
                                        'smtp_message_id' => 'ptc-' . time() . '-' . substr(md5($email), 0, 8),
                                    ]
                                );
                                $sentCount++;
                                $failedCount--;
                            } catch (\Throwable $retryEx) {
                                $rateLimitHit = true;
                                $this->warn("\n[RATE LIMIT DETECTED] Server limit reached: " . $retryEx->getMessage());
                                break;
                            }
                        } elseif (str_contains(strtolower($errorMsg), 'quota') || str_contains(strtolower($errorMsg), 'rate limit') || str_contains(strtolower($errorMsg), '451')) {
                            $rateLimitHit = true;
                            $this->warn("\n[RATE LIMIT DETECTED] Server limit reached: {$errorMsg}");
                            break;
                        }
                    }

                    if ($delaySeconds > 0) {
                        sleep($delaySeconds);
                    }
                }

                if ($rateLimitHit) {
                    break;
                }

                // B. Send Email 2: Monthly Payment Reminder (with 3 pictures)
                if ($rec['needs_payment']) {
                    try {
                        $paymentMail = new PaymentReminderMail(
                            recipientName: $rec['name'],
                            billingMonth: $billingMonth
                        );

                        $sendRes = $rotator->sendMail($email, $paymentMail);

                        // Record in DB as SENT to strictly guarantee NO DOUBLE SEND
                        MonthlyPaymentReminder::updateOrCreate(
                            [
                                'billing_month' => $billingMonth,
                                'reminder_type' => 'monthly_payment_reminder',
                                'parent_email'  => $email,
                            ],
                            [
                                'family_id'       => $rec['family_id'],
                                'parent_name'     => $rec['name'],
                                'student_names'   => $rec['student_name'],
                                'student_count'   => 1,
                                'status'          => MonthlyPaymentReminder::STATUS_SENT,
                                'sent_at'         => now(),
                                'mail_transport'  => $sendRes['mailer_used'] ?? 'smtp',
                                'smtp_message_id' => 'payment-' . time() . '-' . substr(md5($email), 0, 8),
                            ]
                        );

                        $sentCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        $errorMsg = $e->getMessage();
                        Log::error("Broadcast Error (Payment) to {$email}: {$errorMsg}");

                        if (str_contains(strtolower($errorMsg), '421') || str_contains(strtolower($errorMsg), 'too many messages')) {
                            try {
                                foreach ($rotator->getMailerPool() as $m) {
                                    try { app('mail.manager')->purge($m); } catch (\Throwable) {}
                                }
                                sleep(3);
                                $sendRes = $rotator->sendMail($email, $paymentMail);
                                MonthlyPaymentReminder::updateOrCreate(
                                    [
                                        'billing_month' => $billingMonth,
                                        'reminder_type' => 'monthly_payment_reminder',
                                        'parent_email'  => $email,
                                    ],
                                    [
                                        'family_id'       => $rec['family_id'],
                                        'parent_name'     => $rec['name'],
                                        'student_names'   => $rec['student_name'],
                                        'student_count'   => 1,
                                        'status'          => MonthlyPaymentReminder::STATUS_SENT,
                                        'sent_at'         => now(),
                                        'mail_transport'  => $sendRes['mailer_used'] ?? 'smtp',
                                        'smtp_message_id' => 'payment-' . time() . '-' . substr(md5($email), 0, 8),
                                    ]
                                );
                                $sentCount++;
                                $failedCount--;
                            } catch (\Throwable $retryEx) {
                                $rateLimitHit = true;
                                $this->warn("\n[RATE LIMIT DETECTED] Server limit reached: " . $retryEx->getMessage());
                                break;
                            }
                        } elseif (str_contains(strtolower($errorMsg), 'quota') || str_contains(strtolower($errorMsg), 'rate limit') || str_contains(strtolower($errorMsg), '451')) {
                            $rateLimitHit = true;
                            $this->warn("\n[RATE LIMIT DETECTED] Server limit reached: {$errorMsg}");
                            break;
                        }
                    }

                    if ($delaySeconds > 0) {
                        sleep($delaySeconds);
                    }
                }

                // Keep socket connection fresh by purging every 15 emails
                if ($sentCount > 0 && $sentCount % 15 === 0) {
                    try {
                        foreach ($rotator->getMailerPool() as $m) {
                            try { app('mail.manager')->purge($m); } catch (\Throwable) {}
                        }
                    } catch (\Throwable) {}
                }

                $bar->advance();

                if ($rateLimitHit) {
                    break;
                }
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("Batch dispatch finished for this run:");
            $this->info("  • Emails successfully sent: {$sentCount}");
            $this->info("  • Emails failed: {$failedCount}");

            $remainingAfterRun = $totalPending - count($batch);
            if ($remainingAfterRun > 0 || $rateLimitHit) {
                $this->warn("Remaining unsent inboxes: " . max(0, $remainingAfterRun));
                $this->info("Auto-scheduler (cron) will automatically pick up the remaining recipients in the next 2-hour cycle without any duplicates.");
            } else {
                $this->info("🎉 ALL INBOXES PROCESSED SUCCESSFULLY!");
            }

            return Command::SUCCESS;
        }

        // 5. Default: Safe Dry-Run Mode
        $this->warn("\n*** CURRENTLY IN DRY-RUN / SAFE MODE (WAG MUNA SEND) ***");
        $this->info("No emails were dispatched to parents or students.");
        $this->info("To test and preview BOTH emails in your own personal inbox first, run:");
        $this->comment("  php artisan email:send-monthly-broadcast --preview=your-email@example.com");
        $this->info("\nTo dispatch with automatic 2-hour batches and zero duplicates, run:");
        $this->comment("  php artisan email:send-monthly-broadcast --send --batch=200");
        $this->info('========================================================================');

        return Command::SUCCESS;
    }
}
