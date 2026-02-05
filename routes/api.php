<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebAuthnController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ObjectiveController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicEventFeedController;
use App\Http\Controllers\CalendarExportController;
use App\Http\Controllers\EventNoteController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingPointController;
use App\Http\Controllers\MeetingPointNoteController;
use App\Http\Controllers\MeetingNoteController;
use App\Http\Controllers\MeetingSummaryExportController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\Integrations\ClubCalendarController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'registerWithInvite']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('recover', [AuthController::class, 'recoverPassword']);
    Route::post('webauthn/register/options', [WebAuthnController::class, 'registerOptions']);
    Route::post('webauthn/register/verify', [WebAuthnController::class, 'registerVerify']);
    Route::post('webauthn/authenticate/options', [WebAuthnController::class, 'authenticateOptions']);
    Route::post('webauthn/authenticate/verify', [WebAuthnController::class, 'authenticateVerify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::prefix('public')->group(function () {
    Route::get('churches', [PublicCatalogController::class, 'churches']);
    Route::get('churches/{church}/departments', [PublicCatalogController::class, 'departments']);
    Route::get('churches/{church:slug}/events', [PublicEventFeedController::class, 'index']);
    Route::get('churches/{church:slug}/events.ics', [PublicEventFeedController::class, 'ics']);
    Route::get('invitations/{code}', [PublicCatalogController::class, 'invitation']);
    Route::get('events', [PublicEventFeedController::class, 'index']);
    Route::get('events.ics', [PublicEventFeedController::class, 'ics']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events', [EventController::class, 'index']);
    Route::post('events/hold', [EventController::class, 'storeHold']);
    Route::post('events/{event}/lock', [EventController::class, 'lockEvent']);
    Route::post('events/{event}/review', [EventController::class, 'review']);
    Route::post('events/publish-accepted', [EventController::class, 'publishAccepted']);
    Route::patch('events/{event}', [EventController::class, 'update']);
    Route::delete('events/{event}', [EventController::class, 'destroy']);
    Route::post('events/{event}/notes', [EventNoteController::class, 'store']);
    Route::post('events/{event}/notes/{note}/reply', [EventNoteController::class, 'reply'])
        ->whereNumber('note');
    Route::post('events/{event}/notes/reply', [EventNoteController::class, 'replyForEvent']);
    Route::get('notes/unseen', [EventNoteController::class, 'unseen']);
    Route::post('notes/{note}/seen', [EventNoteController::class, 'markSeen'])
        ->whereNumber('note');
    Route::get('calendar/export', [CalendarExportController::class, 'export']);

    Route::get('objectives', [ObjectiveController::class, 'index']);
    Route::post('objectives', [ObjectiveController::class, 'store']);
    Route::put('objectives/{objective}', [ObjectiveController::class, 'update']);
    Route::delete('objectives/{objective}', [ObjectiveController::class, 'destroy']);

    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations', [InvitationController::class, 'store']);
    Route::post('invitations/{invitation}/revoke', [InvitationController::class, 'revoke']);

    Route::get('inventory', [InventoryController::class, 'index']);
    Route::post('inventory', [InventoryController::class, 'store']);
    Route::put('inventory/{inventory}', [InventoryController::class, 'update']);
    Route::delete('inventory/{inventory}', [InventoryController::class, 'destroy']);

    Route::get('meetings', [MeetingController::class, 'index']);
    Route::get('meetings/{meeting}', [MeetingController::class, 'show']);
    Route::post('meetings', [MeetingController::class, 'store']);
    Route::patch('meetings/{meeting}', [MeetingController::class, 'update']);
    Route::post('meetings/{meeting}/close-agenda', [MeetingController::class, 'closeAgenda']);
    Route::post('meetings/{meeting}/start', [MeetingController::class, 'start']);
    Route::post('meetings/{meeting}/adjourn', [MeetingController::class, 'adjourn']);

    Route::get('meetings/{meeting}/points', [MeetingPointController::class, 'index']);
    Route::post('meetings/{meeting}/points', [MeetingPointController::class, 'store']);
    Route::post('meetings/{meeting}/points/reorder', [MeetingPointController::class, 'reorder']);
    Route::post('meetings/{meeting}/notes', [MeetingNoteController::class, 'store']);
    Route::get('meetings/{meeting}/summary.pdf', [MeetingSummaryExportController::class, 'export']);
    Route::patch('meeting-points/{point}', [MeetingPointController::class, 'update']);
    Route::post('meeting-points/{point}/review', [MeetingPointController::class, 'review']);
    Route::post('meeting-points/{point}/activate', [MeetingPointController::class, 'activate']);
    Route::post('meeting-points/{point}/finalize', [MeetingPointController::class, 'finalize']);
    Route::post('meeting-points/{point}/notes', [MeetingPointNoteController::class, 'store']);
});

Route::middleware('integration.token')->prefix('integrations/clubs')->group(function () {
    Route::get('catalog', [ClubCalendarController::class, 'catalog']);
    Route::post('calendar', [ClubCalendarController::class, 'importCalendar']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('admin/departments', [AdminUserController::class, 'listDepartments']);
    Route::get('admin/users', [AdminUserController::class, 'listUsers']);
    Route::post('admin/users', [AdminUserController::class, 'storeUser']);
    Route::put('admin/users/{user}', [AdminUserController::class, 'updateUser']);
    Route::delete('admin/users/{user}', [AdminUserController::class, 'destroyUser']);
});

Route::middleware(['auth:sanctum', 'secretary'])->group(function () {
    Route::get('secretary/departments', [AdminUserController::class, 'listDepartmentsWithUsers']);
    Route::put('secretary/departments/{department}', [AdminUserController::class, 'updateDepartment']);
    Route::get('secretary/users', [AdminUserController::class, 'listUsers']);
    Route::put('secretary/users/{user}', [AdminUserController::class, 'updateUser']);
});

Route::middleware(['auth:sanctum', 'superadmin'])->group(function () {
    Route::get('superadmin/churches', [SuperAdminController::class, 'index']);
    Route::post('superadmin/churches', [SuperAdminController::class, 'storeChurch']);
    Route::put('superadmin/churches/{church}', [SuperAdminController::class, 'update']);
    Route::delete('superadmin/churches/{church}', [SuperAdminController::class, 'destroy']);
    Route::post('superadmin/churches/{church}/invitations', [SuperAdminController::class, 'generateInvite']);
    Route::get('superadmin/churches/{church}/departments', [SuperAdminController::class, 'listDepartments']);
    Route::post('superadmin/churches/{church}/departments', [SuperAdminController::class, 'storeDepartment']);
    Route::put('superadmin/churches/{church}/departments/{department}', [SuperAdminController::class, 'updateDepartment']);
    Route::delete('superadmin/churches/{church}/departments/{department}', [SuperAdminController::class, 'destroyDepartment']);
    Route::delete('superadmin/churches/{church}/departments/{department}/events', [SuperAdminController::class, 'destroyDepartmentEvents']);
    Route::get('superadmin/churches/{church}/events', [SuperAdminController::class, 'listChurchEvents']);
    Route::delete('superadmin/churches/{church}/events', [SuperAdminController::class, 'destroyChurchEvents']);
    Route::get('superadmin/churches/{church}/users', [SuperAdminController::class, 'listUsers']);
    Route::post('superadmin/churches/{church}/users', [SuperAdminController::class, 'storeUser']);
    Route::put('superadmin/churches/{church}/users/{user}', [SuperAdminController::class, 'updateUser']);
    Route::delete('superadmin/churches/{church}/users/{user}', [SuperAdminController::class, 'destroyUser']);
});
