<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuditProjectController;
use App\Http\Controllers\Api\FindingController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ActionPlanController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\FindingDepartmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MyTaskController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Middleware\RoleMiddleware;


Route::get('/test', function () {
    return response()->json([
        'message' => 'API working'
    ]);
});

Route::prefix("dashboard")->group(function () {
    Route::get(
        "/summary",
        [DashboardController::class,"summary"]
    );

    Route::get("/findings-by-risk",[DashboardController::class,"findingsByRisk"]);
    Route::get("/findings-by-category",[DashboardController::class,"findingsByCategory"]);
    Route::get("/action-plans-by-status",[DashboardController::class,"actionPlansByStatus"]);
    Route::get("/overdue-action-plans",[DashboardController::class,"overdueActionPlans"]);
    Route::get("/overdue-by-department",[DashboardController::class,"overdueByDepartment"]);
});

Route::get('/projects', [AuditProjectController::class, 'index']);
Route::get('/projects/{id}', [AuditProjectController::class, 'show']);
Route::post('/projects', [AuditProjectController::class, 'store']);
Route::get('/projects/{id}/findings', [AuditProjectController::class, 'findings']);
Route::put('/projects/{id}', [AuditProjectController::class,'update']);
Route::delete('/projects/{id}', [AuditProjectController::class,'destroy']);

Route::get('/companies', [CompanyController::class, 'index']);

Route::get('/departments', [DepartmentController::class, 'index']);

Route::get('/findings', [FindingController::class, 'index']);
Route::post('/findings', [FindingController::class, 'store']);
Route::get('/findings/{id}', [FindingController::class, 'show']);
Route::put('/findings/{id}', [FindingController::class,'update']);
Route::delete('/findings/{id}', [FindingController::class,'destroy']);
Route::post('/findings/add-department', [FindingController::class, 'addDepartment']);

Route::post('/action-plans', [ActionPlanController::class, 'store']);
Route::post('/action-plans/bulk', [ActionPlanController::class, 'bulkStore']);
Route::post('/action-plans/{id}/submit', [ActionPlanController::class, 'submit']);
Route::post('/action-plans/{id}/approve', [ActionPlanController::class, 'approve']);

Route::post('/verifications', [VerificationController::class, 'store']);

Route::post('/finding-departments', [FindingDepartmentController::class, 'store']);
Route::put('/finding-departments/{id}', [FindingDepartmentController::class, 'updateStatus']);
Route::delete('/finding-departments/{id}', [FindingDepartmentController::class, 'destroy']);

Route::post('/login', [AuthController::class, 'login']);



Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn() => auth()->user());

    Route::get('/projects', [AuditProjectController::class, 'index']);
    Route::get('/projects/{id}', [AuditProjectController::class, 'show']);
    Route::get('/projects/{id}/findings', [AuditProjectController::class, 'findings']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/departments', [DepartmentController::class, 'index']);

    Route::get('/findings', [FindingController::class, 'index']);
    Route::get('/findings/{id}', [FindingController::class, 'show']);

    Route::post('/action-plans/{id}/comment',[ActionPlanController::class, 'comment']);

    Route::prefix("dashboard")->group(function () {
        Route::get("/summary",[DashboardController::class,"summary"]);
        Route::get("/findings-by-risk",[DashboardController::class,"findingsByRisk"]);
        Route::get("/findings-by-category",[DashboardController::class,"findingsByCategory"]);
        Route::get("/action-plans-by-status",[DashboardController::class,"actionPlansByStatus"]);
        Route::get("/overdue-action-plans",[DashboardController::class,"overdueActionPlans"]);
        Route::get("/overdue-by-department",[DashboardController::class,"overdueByDepartment"]);
    });

    Route::get('/notifications',[NotificationController::class,'index']);
    Route::get('/notifications/unread-count',[NotificationController::class,'unreadCount']);
    Route::post('/notifications/{id}/read',[NotificationController::class,'markRead']);

});

Route::middleware([
    'auth:sanctum',
    'role:admin,auditor'
    ])->group(function () {

        // Project
        Route::post('/projects', [AuditProjectController::class, 'store']);
        Route::put('/projects/{id}', [AuditProjectController::class, 'update']);

        // Finding
        Route::post('/finding-departments', [FindingDepartmentController::class, 'store']);
        Route::post('/findings', [FindingController::class, 'store']);
        Route::put('/findings/{id}', [FindingController::class, 'update']);
        Route::post('/findings/add-department', [FindingController::class, 'addDepartment']);

        // Action Plan Management
        Route::post('/action-plans', [ActionPlanController::class, 'store']);
        Route::post('/action-plans/bulk', [ActionPlanController::class, 'bulkStore']);

        Route::post('/action-plans/{id}/approve', [ActionPlanController::class, 'approve']);
    });

Route::middleware([
    'auth:sanctum',
    'role:admin,auditor'
    ])->group(function () {

        Route::delete('/projects/{id}', [AuditProjectController::class, 'destroy']);

        Route::delete('/findings/{id}', [FindingController::class, 'destroy']);

        Route::delete('/finding-departments/{id}',
            [FindingDepartmentController::class, 'destroy']
        );
    });

Route::middleware([
    'auth:sanctum',
    'role:auditee'
    ])->group(function () {

        Route::post('/action-plans/{id}/submit',
            [ActionPlanController::class, 'submit']
        );
    });

Route::middleware([
        'auth:sanctum',
        'role:auditee'
    ])->group(function () {

        Route::get('/my-tasks', [MyTaskController::class, 'index']);

    });