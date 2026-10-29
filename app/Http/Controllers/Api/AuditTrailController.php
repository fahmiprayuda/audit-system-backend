<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditTrail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditTrail::with(['user.department']);

        if ($request->filled('module')) {
            $module = strtolower($request->module);
            $query->whereRaw('LOWER(module) = ?', [$module]);
        }

        if ($request->filled('action')) {
            $action = strtolower($request->action);
            $query->whereRaw('LOWER(action) = ?', [$action]);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            // Pre-fetch matching projects and findings for contextual search
            $matchingProjIds = \App\Models\AuditProject::where('project_code', 'like', "%{$search}%")
                ->orWhere('project_name', 'like', "%{$search}%")
                ->pluck('id');

            $matchingFindIds = \App\Models\Finding::where('finding_code', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->pluck('id');

            $query->where(function ($q) use ($search, $matchingProjIds, $matchingFindIds) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('new_values', 'like', "%{$search}%")
                  ->orWhere('old_values', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });

                if ($matchingProjIds->isNotEmpty()) {
                    $q->orWhere(function ($pq) use ($matchingProjIds) {
                        $pq->whereRaw('LOWER(module) = ?', ['project'])
                           ->whereIn('record_id', $matchingProjIds);
                    });
                }

                if ($matchingFindIds->isNotEmpty()) {
                    $q->orWhere(function ($fq) use ($matchingFindIds) {
                        $fq->whereRaw('LOWER(module) = ?', ['finding'])
                           ->whereIn('record_id', $matchingFindIds);
                    });
                }
            });
        }

        $allowAllPeriods = $request->boolean('all_periods') && $request->filled('search');

        $from = null;
        $to = null;

        if (!$allowAllPeriods) {
            if ($request->filled('date_from')) {
                try {
                    $from = \Carbon\Carbon::parse($request->date_from)->startOfDay();
                } catch (\Exception $e) {
                    $from = now()->startOfMonth();
                }
            } else {
                $from = now()->startOfMonth();
            }

            if ($request->filled('date_to')) {
                try {
                    $to = \Carbon\Carbon::parse($request->date_to)->endOfDay();
                } catch (\Exception $e) {
                    $to = (clone $from)->endOfMonth();
                }
            } else {
                $to = (clone $from)->endOfMonth();
            }

            // Enforce maximum 31 days limit
            if ($from->diffInDays($to) > 31) {
                $to = (clone $from)->addDays(31)->endOfDay();
            }

            if ($to->lt($from)) {
                $to = (clone $from)->endOfDay();
            }

            $query->whereBetween('created_at', [$from, $to]);
        }

        $perPage = min((int) ($request->per_page ?? 20), 1000);

        $paginated = $query
            ->latest()
            ->paginate($perPage);

        // Batch resolve project_code, finding_code, and action plan task
        $items = $paginated->getCollection();
        $projectIds = [];
        $findingIds = [];
        $actionPlanIds = [];
        $commentIds = [];

        foreach ($items as $item) {
            $mod = strtolower($item->module);
            $recId = $item->record_id;
            if (!$recId) continue;

            if ($mod === 'project') {
                $projectIds[] = $recId;
            } elseif ($mod === 'finding') {
                $findingIds[] = $recId;
            } elseif ($mod === 'action_plan' || $mod === 'actionplan') {
                $actionPlanIds[] = $recId;
            } elseif ($mod === 'comment') {
                $commentIds[] = $recId;
            }
        }

        $projects = !empty($projectIds)
            ? \App\Models\AuditProject::whereIn('id', array_unique($projectIds))->get()->keyBy('id')
            : collect();

        $findings = !empty($findingIds)
            ? \App\Models\Finding::with('project')->whereIn('id', array_unique($findingIds))->get()->keyBy('id')
            : collect();

        $actionPlans = !empty($actionPlanIds)
            ? \App\Models\ActionPlan::with('findingDepartment.finding.project')->whereIn('id', array_unique($actionPlanIds))->get()->keyBy('id')
            : collect();

        $comments = !empty($commentIds)
            ? \App\Models\ActionPlanComment::with('actionPlan.findingDepartment.finding.project')->whereIn('id', array_unique($commentIds))->get()->keyBy('id')
            : collect();

        foreach ($items as $item) {
            $mod = strtolower($item->module);
            $recId = $item->record_id;

            $pCode = null;
            $pId = null;
            $fCode = null;
            $fId = null;
            $fTitle = null;
            $apTask = null;

            $commentMsg = null;

            if ($mod === 'project' && isset($projects[$recId])) {
                $p = $projects[$recId];
                $pCode = $p->project_code;
                $pId = $p->id;
            } elseif ($mod === 'finding' && isset($findings[$recId])) {
                $f = $findings[$recId];
                $fCode = $f->finding_code;
                $fId = $f->id;
                $fTitle = $f->title;
                $pCode = $f->project?->project_code;
                $pId = $f->project?->id;
            } elseif (($mod === 'action_plan' || $mod === 'actionplan') && isset($actionPlans[$recId])) {
                $ap = $actionPlans[$recId];
                $f = $ap->findingDepartment?->finding;
                $fCode = $f?->finding_code;
                $fId = $f?->id;
                $fTitle = $f?->title;
                $pCode = $f?->project?->project_code;
                $pId = $f?->project?->id;
                $apTask = $ap->task;
            } elseif ($mod === 'comment' && isset($comments[$recId])) {
                $c = $comments[$recId];
                $commentMsg = $c->message;
                $ap = $c->actionPlan;
                $f = $ap?->findingDepartment?->finding;
                $fCode = $f?->finding_code;
                $fId = $f?->id;
                $fTitle = $f?->title;
                $pCode = $f?->project?->project_code;
                $pId = $f?->project?->id;
                $apTask = $ap?->task;
            }

            // Fallback checks from new_values if record was deleted or logged directly
            if (!$pCode && is_array($item->new_values) && isset($item->new_values['project_code'])) {
                $pCode = $item->new_values['project_code'];
            }
            if (!$fCode && is_array($item->new_values) && isset($item->new_values['finding_code'])) {
                $fCode = $item->new_values['finding_code'];
            }
            if (!$apTask && is_array($item->new_values) && isset($item->new_values['action_plan'])) {
                $apTask = $item->new_values['action_plan'];
            }
            if (!$commentMsg && is_array($item->new_values)) {
                $commentMsg = $item->new_values['comment'] ?? $item->new_values['message'] ?? null;
            }

            // Auto-enrich generic "Added comment" description if it was logged generically
            if (strtolower(trim($item->description)) === 'added comment' && ($fCode || $commentMsg)) {
                $desc = 'Added comment';
                if ($fCode) {
                    $desc .= " on Finding {$fCode}";
                    if ($pCode) $desc .= " ({$pCode})";
                }
                if ($apTask) {
                    $desc .= " - Action Plan: {$apTask}";
                }
                $item->description = $desc;
            }

            $item->project_code = $pCode;
            $item->project_id = $pId;
            $item->finding_code = $fCode;
            $item->finding_id = $fId;
            $item->finding_title = $fTitle;
            $item->action_plan_task = $apTask;
            $item->comment_message = $commentMsg;
        }

        return response()->json(array_merge($paginated->toArray(), [
            'filter_period' => [
                'from' => $allowAllPeriods ? null : $from->toDateString(),
                'to'   => $allowAllPeriods ? null : $to->toDateString(),
                'is_all_periods' => $allowAllPeriods,
            ],
        ]));
    }
}