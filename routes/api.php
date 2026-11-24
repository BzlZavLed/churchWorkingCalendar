<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebAuthnController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ObjectiveController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicChurchContactConsentController;
use App\Http\Controllers\PublicEventFeedController;
use App\Http\Controllers\CalendarExportController;
use App\Http\Controllers\ChurchContactController;
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

Route::post('integrations/whatsapp/webhook', [\App\Http\Controllers\Integrations\WhatsAppWebhookController::class, 'receive']);
Route::get('integrations/whatsapp/webhook', [\App\Http\Controllers\Integrations\WhatsAppWebhookController::class, 'verify'])->middleware('throttle:30,1');

Route::get('public/churches/{church}/attendance/status', [\App\Http\Controllers\MemberAttendanceController::class, 'publicStatus'])->middleware('throttle:60,1');
Route::get('public/churches/{church}/attendance/surnames', [\App\Http\Controllers\MemberAttendanceController::class, 'publicSurnames'])->middleware('throttle:30,1');
Route::get('public/churches/{church}/attendance/family', [\App\Http\Controllers\MemberAttendanceController::class, 'publicFamily'])->middleware('throttle:30,1');
Route::post('public/churches/{church}/attendance', [\App\Http\Controllers\MemberAttendanceController::class, 'publicSave'])->middleware('throttle:20,1');
Route::get('public/churches/{church}/visitor-form-images/{image}', [\App\Http\Controllers\VisitorFormController::class, 'image'])->whereUuid('image');
Route::get('public/churches/{church}/visitor-logo', [\App\Http\Controllers\VisitorFormController::class, 'logo']);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'registerWithInvite']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('recover', [\App\Http\Controllers\AccountPasswordController::class, 'requestLink'])->middleware('throttle:5,1');
    Route::post('reset-password', [\App\Http\Controllers\AccountPasswordController::class, 'reset'])->middleware('throttle:5,1');
    Route::post('webauthn/register/options', [WebAuthnController::class, 'registerOptions']);
    Route::post('webauthn/register/verify', [WebAuthnController::class, 'registerVerify']);
    Route::post('webauthn/authenticate/options', [WebAuthnController::class, 'authenticateOptions']);
    Route::post('webauthn/authenticate/verify', [WebAuthnController::class, 'authenticateVerify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('whatsapp-link', [\App\Http\Controllers\WhatsAppPreferencesController::class, 'link'])->middleware(['module.access','throttle:5,1']);
        Route::delete('whatsapp-link', [\App\Http\Controllers\WhatsAppPreferencesController::class, 'unlink'])->middleware('module.access');
        Route::get('whatsapp-history', [\App\Http\Controllers\WhatsAppPreferencesController::class, 'history'])->middleware(['module.access','throttle:60,1']);
        Route::get('whatsapp-preferences', [\App\Http\Controllers\WhatsAppPreferencesController::class, 'show'])->middleware('module.access');
        Route::patch('whatsapp-preferences', [\App\Http\Controllers\WhatsAppPreferencesController::class, 'update'])->middleware(['module.access','throttle:10,1']);
    });
});

Route::prefix('public')->group(function () {
    Route::get('churches/{church}/visit', [ChurchContactController::class, 'publicForm'])->middleware('throttle:60,1');
    Route::post('churches/{church}/visit', [ChurchContactController::class, 'publicStore'])->middleware('throttle:10,1');
    Route::get('churches', [PublicCatalogController::class, 'churches']);
    Route::get('churches/{church}/departments', [PublicCatalogController::class, 'departments']);
    Route::get('consent/{token}', [PublicChurchContactConsentController::class, 'show']);
    Route::post('consent/{token}/revoke', [PublicChurchContactConsentController::class, 'revoke']);
    Route::get('churches/{church:slug}/events', [PublicEventFeedController::class, 'index']);
    Route::get('churches/{church:slug}/events.ics', [PublicEventFeedController::class, 'ics']);
    Route::get('invitations/{code}', [PublicCatalogController::class, 'invitation']);
    Route::get('events', [PublicEventFeedController::class, 'index']);
    Route::get('events.ics', [PublicEventFeedController::class, 'ics']);
});

Route::middleware(['auth:sanctum', 'superadmin.context', 'module.access'])->group(function () {
    Route::post('activity/page-view', [\App\Http\Controllers\PageActivityController::class, 'store'])->middleware('throttle:120,1');
    Route::get('access', [\App\Http\Controllers\ChurchRoleController::class, 'current']);
    Route::get('reports/attendance', [\App\Http\Controllers\AttendanceReportController::class, 'index']);
    Route::get('reports/events', [EventController::class, 'index']);
    Route::get('reports/objectives', [ObjectiveController::class, 'index']);
    Route::post('reports/events/{event}/notes', [EventNoteController::class, 'store']);
    Route::post('committees/{committee}/reports', [\App\Http\Controllers\CommitteeReportController::class, 'store']);
    Route::get('committees/{committee}/reports/{report}/preview', [\App\Http\Controllers\CommitteeReportController::class, 'preview']);
    Route::get('committees/{committee}/reports/{report}/download', [\App\Http\Controllers\CommitteeReportController::class, 'download']);
    Route::get('committees', [\App\Http\Controllers\CommitteeController::class, 'index']);
    Route::post('committees', [\App\Http\Controllers\CommitteeController::class, 'save']);
    Route::patch('committees/{committee}', [\App\Http\Controllers\CommitteeController::class, 'save']);
    Route::get('events', [EventController::class, 'index']);
    Route::post('conference-events', [\App\Http\Controllers\ConferenceEventController::class, 'store']);
    Route::post('conference-events/publish-all', [\App\Http\Controllers\ConferenceEventController::class, 'publish']);
    Route::post('conference-events/{event}/publish', [\App\Http\Controllers\ConferenceEventController::class, 'publish']);
    Route::post('conference-events/import', [\App\Http\Controllers\ConferenceEventController::class, 'import']);
    Route::patch('conference-events/{event}', [\App\Http\Controllers\ConferenceEventController::class, 'update']);
    Route::delete('conference-events/{event}', [\App\Http\Controllers\ConferenceEventController::class, 'destroy']);
    Route::post('events/hold', [EventController::class, 'storeHold']);
    Route::post('events/{event}/extend', [EventController::class, 'extendHold']);
    Route::post('events/{event}/lock', [EventController::class, 'lockEvent']);
    Route::post('events/{event}/request-open', [EventController::class, 'requestOpen']);
    Route::post('events/{event}/review', [EventController::class, 'review']);
    Route::patch('events/{event}', [EventController::class, 'update']);
    Route::delete('events/{event}', [EventController::class, 'destroy']);
    Route::post('events/{event}/notes', [EventNoteController::class, 'store']);
    Route::post('events/{event}/notes/{note}/reply', [EventNoteController::class, 'reply'])
        ->whereNumber('note');
    Route::post('events/{event}/notes/reply', [EventNoteController::class, 'replyForEvent']);
    Route::get('notes/unseen', [EventNoteController::class, 'unseen']);
    Route::post('notes/{note}/seen', [EventNoteController::class, 'markSeen'])
        ->whereNumber('note');
    Route::get('calendar/objectives', [ObjectiveController::class, 'index']);
    Route::get('calendar/export', [CalendarExportController::class, 'export']);

    Route::get('qr-designs/{kind}', [\App\Http\Controllers\QrDesignController::class, 'show']);
    Route::put('qr-designs/{kind}', [\App\Http\Controllers\QrDesignController::class, 'save']);

    Route::get('objectives', [ObjectiveController::class, 'index']);
    Route::get('objectives/{objective}/events', [ObjectiveController::class, 'events']);
    Route::post('objectives', [ObjectiveController::class, 'store']);
    Route::put('objectives/{objective}', [ObjectiveController::class, 'update']);
    Route::delete('objectives/{objective}', [ObjectiveController::class, 'destroy']);

    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations', [InvitationController::class, 'store']);
    Route::post('invitations/{invitation}/revoke', [InvitationController::class, 'revoke']);

    Route::get('budget', [\App\Http\Controllers\BudgetController::class, 'index']);
    Route::put('budget/settings', [\App\Http\Controllers\BudgetController::class, 'settings']);
    Route::put('budget/plan', [\App\Http\Controllers\BudgetController::class, 'plan']);
    Route::post('budget/start', [\App\Http\Controllers\BudgetController::class, 'start']);
    Route::post('budget/entries', [\App\Http\Controllers\BudgetController::class, 'entry']);
    Route::post('budget/allocations', [\App\Http\Controllers\BudgetController::class, 'publish']);
    Route::post('budget/allocations/preview', [\App\Http\Controllers\BudgetController::class, 'preview']);

    Route::get('inventory', [InventoryController::class, 'index']);
    Route::post('inventory', [InventoryController::class, 'store']);
    Route::put('inventory/{inventory}', [InventoryController::class, 'update']);
    Route::delete('inventory/{inventory}', [InventoryController::class, 'destroy']);

    Route::get('meeting-points/report', [MeetingPointController::class, 'report']);
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

    Route::get('attendance/status', [\App\Http\Controllers\MemberAttendanceController::class, 'status']);
    Route::get('attendance/services', [\App\Http\Controllers\AttendanceServiceController::class, 'index']);
    Route::post('attendance/services', [\App\Http\Controllers\AttendanceServiceController::class, 'save']);
    Route::put('attendance/services/{service}', [\App\Http\Controllers\AttendanceServiceController::class, 'save']);
    Route::post('attendance/sessions', [\App\Http\Controllers\AttendanceServiceController::class, 'open']);
    Route::patch('attendance/sessions/{session}', [\App\Http\Controllers\AttendanceServiceController::class, 'status']);
    Route::get('attendance/surnames', [\App\Http\Controllers\MemberAttendanceController::class, 'surnames'])->middleware('throttle:120,1');
    Route::get('attendance/family', [\App\Http\Controllers\MemberAttendanceController::class, 'family']);
    Route::post('attendance/batch', [\App\Http\Controllers\MemberAttendanceController::class, 'batch']);
    Route::post('attendance', [\App\Http\Controllers\MemberAttendanceController::class, 'save']);
    Route::get('members/birthdays', [\App\Http\Controllers\MemberBirthdayController::class, 'index']);
    Route::post('members/birthdays/message', [\App\Http\Controllers\MemberBirthdayController::class, 'generate'])->middleware('throttle:5,1');
    Route::get('members', [\App\Http\Controllers\ChurchMemberController::class, 'index']);
    Route::get('members/template.csv', [\App\Http\Controllers\ChurchMemberController::class, 'download']);
    Route::post('members/import', [\App\Http\Controllers\ChurchMemberController::class, 'import']);
    Route::post('members', [\App\Http\Controllers\ChurchMemberController::class, 'save']);
    Route::get('members/import-drafts', [\App\Http\Controllers\MemberImportDraftController::class, 'index']);
    Route::post('members/import-drafts', [\App\Http\Controllers\MemberImportDraftController::class, 'save']);
    Route::delete('members/import-drafts/{draft}', [\App\Http\Controllers\MemberImportDraftController::class, 'destroy']);
    Route::get('members/import-drafts/{draft}', [\App\Http\Controllers\MemberImportDraftController::class, 'show']);
    Route::put('members/import-drafts/{draft}', [\App\Http\Controllers\MemberImportDraftController::class, 'save']);
    Route::post('members/import-drafts/{draft}/commit', [\App\Http\Controllers\MemberImportDraftController::class, 'commit']);
    Route::get('members/export-options', [\App\Http\Controllers\MemberExportController::class, 'index']);
    Route::get('members/export.csv', [\App\Http\Controllers\MemberExportController::class, 'download']);
    Route::post('members/export-presets', [\App\Http\Controllers\MemberExportController::class, 'save']);
    Route::delete('members/export-presets/{preset}', [\App\Http\Controllers\MemberExportController::class, 'destroy']);
    Route::get('members/families', [\App\Http\Controllers\MemberFamilyController::class, 'index']);
    Route::post('members/families/move', [\App\Http\Controllers\MemberFamilyController::class, 'move']);
    Route::get('members/{member}', [\App\Http\Controllers\ChurchMemberController::class, 'show']);
    Route::delete('members/{member}', [\App\Http\Controllers\ChurchMemberController::class, 'destroy']);
    Route::patch('members/{member}', [\App\Http\Controllers\ChurchMemberController::class, 'save']);

    Route::post('visitor-form/images', [\App\Http\Controllers\VisitorFormController::class, 'uploadImage']);
    Route::patch('visitor-form/logo', [\App\Http\Controllers\VisitorFormController::class, 'alignLogo']);
    Route::post('visitor-form/logo', [\App\Http\Controllers\VisitorFormController::class, 'updateLogo']);
    Route::delete('visitor-form/logo', [\App\Http\Controllers\VisitorFormController::class, 'removeLogo']);
    Route::get('visitor-form', [\App\Http\Controllers\VisitorFormController::class, 'show']);
    Route::post('visitor-form', [\App\Http\Controllers\VisitorFormController::class, 'publish']);
    Route::patch('church-contacts/{contact}', [ChurchContactController::class, 'update']);
    Route::get('church-contacts', [ChurchContactController::class, 'index']);
    Route::patch('church-contacts/{contact}/consent', [ChurchContactController::class, 'restoreConsent']);
    Route::post('church-contacts', [ChurchContactController::class, 'store']);
});

Route::middleware('integration.token')->prefix('integrations/clubs')->group(function () {
    Route::get('catalog', [ClubCalendarController::class, 'catalog']);
    Route::post('calendar', [ClubCalendarController::class, 'importCalendar']);
});

Route::middleware(['auth:sanctum', 'superadmin.context', 'module.access', 'admin'])->group(function () {
    Route::get('admin/departments', [AdminUserController::class, 'listDepartments']);
    Route::get('admin/users', [AdminUserController::class, 'listUsers']);
    Route::post('admin/users', [AdminUserController::class, 'storeUser']);
    Route::put('admin/users/{user}', [AdminUserController::class, 'updateUser']);
    Route::delete('admin/users/{user}', [AdminUserController::class, 'destroyUser']);
});

Route::middleware(['auth:sanctum', 'superadmin.context', 'module.access', 'secretary'])->group(function () {
    Route::post('secretary/departments', [AdminUserController::class, 'storeDepartment']);
    Route::post('secretary/users', [AdminUserController::class, 'storeUser']);
    Route::get('secretary/departments', [AdminUserController::class, 'listDepartmentsWithUsers']);
    Route::put('secretary/departments/{department}', [AdminUserController::class, 'updateDepartment']);
    Route::get('secretary/users', [AdminUserController::class, 'listUsers']);
    Route::get('secretary/users/{user}/activity', [\App\Http\Controllers\UserActivityController::class, 'clerk']);
    Route::put('secretary/users/{user}', [AdminUserController::class, 'updateUser']);
    Route::post('secretary/users/{user}/password-link', [\App\Http\Controllers\AccountPasswordController::class, 'sendForUser'])->middleware('throttle:5,1');
});

Route::middleware(['auth:sanctum', 'superadmin.context'])->group(function () {
    Route::get('secretary/whatsapp-diagnostics', [\App\Http\Controllers\ChurchIntegrationController::class, 'diagnostics'])->middleware('throttle:30,1');
    Route::post('secretary/whatsapp-diagnostics/check', [\App\Http\Controllers\ChurchIntegrationController::class, 'checkProviders'])->middleware('throttle:3,1');
    Route::get('secretary/whatsapp-history', [\App\Http\Controllers\WhatsAppPreferencesController::class, 'churchHistory'])->middleware('throttle:60,1');
    Route::post('secretary/church-logo', [\App\Http\Controllers\VisitorFormController::class, 'settingsUpload']);
    Route::delete('secretary/church-logo', [\App\Http\Controllers\VisitorFormController::class, 'settingsRemove']);
    Route::get('secretary/integrations', [\App\Http\Controllers\ChurchIntegrationController::class, 'clerkShow']);
    Route::put('secretary/integrations', [\App\Http\Controllers\ChurchIntegrationController::class, 'clerkUpdate'])->middleware('throttle:20,1');
});

Route::middleware(['auth:sanctum', 'superadmin'])->group(function () {
    Route::get('superadmin/users/{user}/activity', [\App\Http\Controllers\UserActivityController::class, 'superadmin']);
    Route::get('superadmin/email-activity', [\App\Http\Controllers\SuperAdminEmailActivityController::class, 'index']);
    Route::get('superadmin/churches/{church}/integrations/providers', [\App\Http\Controllers\ChurchIntegrationController::class, 'providers'])->middleware('throttle:20,1');
    Route::get('superadmin/churches/{church}/integrations', [\App\Http\Controllers\ChurchIntegrationController::class, 'show']);
    Route::put('superadmin/churches/{church}/integrations', [\App\Http\Controllers\ChurchIntegrationController::class, 'update']);
    Route::get('superadmin/churches/{church}/roles', [\App\Http\Controllers\ChurchRoleController::class, 'index']);
    Route::post('superadmin/churches/{church}/roles', [\App\Http\Controllers\ChurchRoleController::class, 'store']);
    Route::put('superadmin/churches/{church}/roles/{role}', [\App\Http\Controllers\ChurchRoleController::class, 'update']);
    Route::put('superadmin/churches/{church}/users/{user}/role', [\App\Http\Controllers\ChurchRoleController::class, 'assign']);
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
