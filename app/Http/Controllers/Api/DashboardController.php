<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Finding;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class DashboardController extends Controller
{
    public function index()
{
    $totalFindings = Finding::count();

    $openFindings = Finding::where('status', 'open')->count();

    $needReviewFindings = Finding::where('status', 'need_review')->count();

    $closedFindings = Finding::where('status', 'closed')->count();

    $overdueFindings = Finding::where('due_date', '<', now())
        ->where('status', '!=', 'closed')
        ->count();

    $riskLevels = Finding::select('risk_level', DB::raw('count(*) as total'))
        ->groupBy('risk_level')
        ->get();

    return response()->json([
    'total_findings' => $totalFindings,
    'open_findings' => $openFindings,
    'need_review_findings' => $needReviewFindings,
    'closed_findings' => $closedFindings
]);
}

public function summary()
{
    $total = Finding::count();

            $significant = Finding::where('risk_category','Significant')->count();

            $moderate = Finding::where('risk_category','Moderate')->count();

            $open = Finding::where('status','open')->count();

            $review = Finding::where('status','need_review')->count();

            $closed = Finding::where('status','closed')->count();

            return response()->json([

            "total"=>$total,
            "significant"=>$significant,
            "moderate"=>$moderate,
            "open"=>$open,
            "need_review"=>$review,
            "closed"=>$closed
]);
}
public function findingsByRisk()
{
    return Finding::selectRaw('risk_rating, COUNT(*) as total')
    ->groupBy('risk_rating')
    ->get();
}

public function overdue()
{
 return Finding::with('project','departments')
->where('status','!=','closed')
->whereDate('due_date','<',Carbon::today())
->get();
}

}
