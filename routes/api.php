<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuditProjectController;
use App\Http\Controllers\Api\FindingController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ActionPlanController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\FindingDepartmentController;


Route::get('/test', function () {
    return response()->json([
        'message' => 'API working'
    ]);
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


Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/dashboard-summary',[DashboardController::class,'summary']);
Route::get('/findings-by-risk',[DashboardController::class,'findingsByRisk']);
Route::get('/findings-overdue',[DashboardController::class,'overdue']);

Route::post('/action-plans', [ActionPlanController::class, 'store']);
Route::post('/action-plans/bulk', [ActionPlanController::class, 'bulkStore']);
Route::post('/action-plans/{id}/submit', [ActionPlanController::class, 'submit']);
Route::post('/action-plans/{id}/approve', [ActionPlanController::class, 'approve']);
Route::post('/action-plans/{id}/start', [ActionPlanController::class, 'start']);
Route::post('/action-plans/{id}/done', [ActionPlanController::class, 'done']);
Route::post('/action-plans/{id}/verify', [ActionPlanController::class, 'verify']);
Route::post('/action-plans/{id}/reject', [ActionPlanController::class, 'reject']);

Route::post('/evidences', [EvidenceController::class, 'store']);

Route::post('/verifications', [VerificationController::class, 'store']);

Route::post('/finding-departments', [FindingDepartmentController::class, 'store']);
Route::put('/finding-departments/{id}', [FindingDepartmentController::class, 'updateStatus']);
Route::delete('/finding-departments/{id}', [FindingDepartmentController::class, 'destroy']);