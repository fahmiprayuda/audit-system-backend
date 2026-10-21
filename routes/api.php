<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuditProjectController;
use App\Http\Controllers\Api\AuditTrailController;
use App\Http\Controllers\Api\FindingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ActionPlanController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\FindingDepartmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MyTaskController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ActionPlanMonitoringController;
use App\Http\Controllers\Master\CompanyController;
use App\Http\Controllers\Master\DepartmentController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/test', function () {
    return response()->json([
        'message' => 'API working'
    ]);
});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn() => auth()->user());
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Common Read Access
    Route::get('/projects', [AuditProjectController::class, 'index']);
    Route::get('/projects/{id}', [AuditProjectController::class, 'show']);
    Route::get('/projects/{id}/findings', [AuditProjectController::class, 'findings']);

    Route::get('/master/companies', [CompanyController::class, 'index']);
    Route::get('/master/companies/check-code', [CompanyController::class, 'checkCode']);
    Route::get('/departments', [DepartmentController::class, 'index']);

    Route::get('/findings', [FindingController::class, 'index']);
    Route::get('/findings/{id}', [FindingController::class, 'show']);

    // Action Plan Comments & Extension Requests
    Route::post('/action-plans/{id}/comment', [ActionPlanController::class, 'comment']);
    Route::post('/action-plans/{id}/extend', [ActionPlanController::class, 'extend']);

    // Shared Dashboard Summaries
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/findings-by-risk', [DashboardController::class, 'findingsByRisk']);
        Route::get('/findings-by-category', [DashboardController::class, 'findingsByCategory']);
        Route::get('/action-plans-by-status', [DashboardController::class, 'actionPlansByStatus']);
        Route::get('/overdue-action-plans', [DashboardController::class, 'overdueActionPlans']);
        Route::get('/overdue-by-department', [DashboardController::class, 'overdueByDepartment']);
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    /*
    |----------------------------------------------------------------------
    | Role: Manager & Auditor
    |----------------------------------------------------------------------
    */
    Route::middleware('role:manager,auditor')->group(function () {

        // Master Data Management
        Route::prefix('master')->group(function () {
            Route::apiResource('companies', CompanyController::class);
            Route::get('companies/{company}/departments', [DepartmentController::class, 'index']);
            Route::post('companies/{company}/departments', [DepartmentController::class, 'store']);
            Route::put('departments/{department}', [DepartmentController::class, 'update']);
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);
        });

        // User Management & Audit Trails
        Route::apiResource('/users', UserController::class);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::get('/audit-trails', [AuditTrailController::class, 'index']);

        // Audit Projects CRUD
        Route::post('/projects', [AuditProjectController::class, 'store']);
        Route::put('/projects/{id}', [AuditProjectController::class, 'update']);
        Route::delete('/projects/{id}', [AuditProjectController::class, 'destroy']);

        // Findings & Department Assignments
        Route::post('/findings', [FindingController::class, 'store']);
        Route::put('/findings/{id}', [FindingController::class, 'update']);
        Route::delete('/findings/{id}', [FindingController::class, 'destroy']);
        Route::post('/findings/add-department', [FindingController::class, 'addDepartment']);

        Route::post('/finding-departments', [FindingDepartmentController::class, 'store']);
        Route::put('/finding-departments/{id}', [FindingDepartmentController::class, 'updateStatus']);
        Route::delete('/finding-departments/{id}', [FindingDepartmentController::class, 'destroy']);

        // Action Plan Review & Verification
        Route::post('/action-plans', [ActionPlanController::class, 'store']);
        Route::post('/action-plans/bulk', [ActionPlanController::class, 'bulkStore']);
        Route::post('/action-plans/{id}/close', [ActionPlanController::class, 'close']);
        Route::post('/verifications', [VerificationController::class, 'store']);

        // Executive & Analytical Dashboards
        Route::get('/dashboard/executive-summary', [DashboardController::class, 'executiveSummary']);
        Route::get('/dashboard/top-overdue-findings', [DashboardController::class, 'topOverdueFindings']);
        Route::get('/dashboard/overdue-findings-by-department', [DashboardController::class, 'overdueFindingsByDepartment']);
        Route::get('/dashboard/findings/{id}/details', [DashboardController::class, 'findingDetails']);
        Route::get('/dashboard/project-portfolio', [DashboardController::class, 'projectPortfolio']);
        Route::get('/dashboard/projects/{id}/details', [DashboardController::class, 'projectDetails']);
        Route::get('/dashboard-overview', [DashboardController::class, 'overview']);

        // Action Plan Monitoring
        Route::get('/dashboard/action-plan-monitoring', [ActionPlanMonitoringController::class, 'index']);
        Route::get('/dashboard/action-plan-monitoring/flag-details', [ActionPlanMonitoringController::class, 'flagDetails']);
    });

    /*
    |----------------------------------------------------------------------
    | Role: Auditee
    |----------------------------------------------------------------------
    */
    Route::middleware('role:auditee')->group(function () {
        Route::post('/action-plans/{id}/submit', [ActionPlanController::class, 'submit']);
        Route::get('/my-tasks', [MyTaskController::class, 'index']);
    });
});
