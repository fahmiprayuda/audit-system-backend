<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActionPlan;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class CheckOverdueActionPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active action plans, sync overdue flags, and send reminder notifications to auditees';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting automated audit action plan overdue & reminder check...');

        $activePlans = ActionPlan::with(['findingDepartment.finding.project', 'findingDepartment.department'])
            ->where('status', '!=', 'closed')
            ->whereNotNull('due_date')
            ->get();

        $total = $activePlans->count();
        $overdueCount = 0;
        $notifCount = 0;
        $today = Carbon::today();
        $inThreeDays = Carbon::today()->addDays(3);

        foreach ($activePlans as $ap) {
            $dueDate = Carbon::parse($ap->due_date)->startOfDay();
            $isOverdue = $dueDate->lt($today);
            $isDueSoon = $dueDate->equalTo($inThreeDays);

            // Sync overdue flag in database
            if ($isOverdue) {
                $ap->syncOverdue();
                $overdueCount++;
            }

            // Find relevant context
            $fd = $ap->findingDepartment;
            $finding = $fd?->finding;
            $findingCode = $finding?->finding_code ?: 'FND';
            $findingId = $finding?->id;
            $fdId = $fd?->id;
            $taskName = $ap->corrective_action ?: $ap->root_cause ?: 'Tindak lanjut';
            $formattedDue = $dueDate->format('d M Y');

            // Find auditees for this department
            $deptId = $fd?->department_id;
            if (!$deptId || !$findingId) {
                continue;
            }

            $auditees = User::where('department_id', $deptId)
                ->where('role', 'auditee')
                ->get();

            foreach ($auditees as $auditee) {
                // Skenario 1: Overdue Notification (dibatasi 1x per hari per action plan per user)
                if ($isOverdue) {
                    $url = "/findings/{$findingId}?ap={$ap->id}&fd={$fdId}";
                    $alreadySentToday = Notification::where('user_id', $auditee->id)
                        ->where('type', 'action_plan_overdue')
                        ->where('url', $url)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySentToday) {
                        Notification::create([
                            'user_id' => $auditee->id,
                            'type' => 'action_plan_overdue',
                            'title' => "Overdue: {$findingCode}",
                            'message' => "Action Plan '{$taskName}' pada temuan {$findingCode} telah melewati batas jatuh tempo ({$formattedDue}). Mohon segera ditindaklanjuti.",
                            'url' => $url,
                        ]);
                        $notifCount++;
                    }
                }

                // Skenario 2: H-3 Reminder Notification (dibatasi 1x per hari)
                if ($isDueSoon) {
                    $url = "/findings/{$findingId}?ap={$ap->id}&fd={$fdId}";
                    $alreadySentToday = Notification::where('user_id', $auditee->id)
                        ->where('type', 'action_plan_due_soon')
                        ->where('url', $url)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySentToday) {
                        Notification::create([
                            'user_id' => $auditee->id,
                            'type' => 'action_plan_due_soon',
                            'title' => "Reminder Jatuh Tempo (H-3): {$findingCode}",
                            'message' => "Action Plan '{$taskName}' pada temuan {$findingCode} akan jatuh tempo dalam 3 hari ({$formattedDue}).",
                            'url' => $url,
                        ]);
                        $notifCount++;
                    }
                }
            }
        }

        $this->info("Completed. Checked {$total} action plans. Overdue synced: {$overdueCount}. Reminder notifications dispatched: {$notifCount}.");

        return Command::SUCCESS;
    }
}
