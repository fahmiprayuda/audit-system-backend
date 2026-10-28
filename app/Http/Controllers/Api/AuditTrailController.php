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
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->date_to
            );
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);

        return $query
            ->latest()
            ->paginate($perPage);
    }
}