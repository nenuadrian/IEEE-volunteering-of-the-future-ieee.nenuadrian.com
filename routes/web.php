<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HourLogController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\MyOpportunitiesController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\OpportunityManageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VolunteerController;
use App\Http\Controllers\VolunteerProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');

Route::get('/volunteers', [VolunteerController::class, 'index'])->name('volunteers.index');
Route::get('/volunteers/{profile}', [VolunteerController::class, 'show'])->name('volunteers.show');
Route::get('/volunteers/{profile}/cv.pdf', [CvController::class, 'pdf'])->name('volunteers.cv');

Route::view('/accessibility', 'accessibility')->name('accessibility');

Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'submit'])->middleware('throttle:6,1')->name('contact.submit');

/*
|--------------------------------------------------------------------------
| Signed-in volunteers & opportunity owners (every account can do both)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my/opportunities', [MyOpportunitiesController::class, 'index'])->name('my.opportunities');

    // Opportunity authoring (declared before /opportunities/{opportunity}).
    Route::get('/opportunities/create', [OpportunityController::class, 'create'])->name('opportunities.create');
    Route::post('/opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
    Route::get('/opportunities/{opportunity}/edit', [OpportunityController::class, 'edit'])->name('opportunities.edit');
    Route::put('/opportunities/{opportunity}', [OpportunityController::class, 'update'])->name('opportunities.update');
    Route::delete('/opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->name('opportunities.destroy');
    Route::post('/opportunities/{opportunity}/clone', [OpportunityController::class, 'clone'])->name('opportunities.clone');
    Route::post('/opportunities/{opportunity}/status', [OpportunityController::class, 'status'])->name('opportunities.status');
    Route::post('/opportunities/{opportunity}/save', [OpportunityController::class, 'toggleSave'])->name('opportunities.save');

    // Owner workspace: applicants, hours, endorsements, co-owners, impact.
    Route::get('/opportunities/{opportunity}/manage', [OpportunityManageController::class, 'show'])->name('opportunities.manage');
    Route::get('/opportunities/{opportunity}/manage/export', [OpportunityManageController::class, 'export'])->name('opportunities.manage.export');
    Route::post('/opportunities/{opportunity}/owners', [OpportunityManageController::class, 'addOwner'])->name('opportunities.owners.store');
    Route::delete('/opportunities/{opportunity}/owners/{user}', [OpportunityManageController::class, 'removeOwner'])->name('opportunities.owners.destroy');

    // Applications.
    Route::post('/opportunities/{opportunity}/apply', [ApplicationController::class, 'store'])->middleware('throttle:20,1')->name('applications.store');
    Route::post('/applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])->name('applications.withdraw');
    Route::post('/applications/{application}/decide', [ApplicationController::class, 'decide'])->name('applications.decide');
    Route::post('/applications/{application}/complete', [ApplicationController::class, 'complete'])->name('applications.complete');
    Route::post('/applications/{application}/feedback', [ApplicationController::class, 'feedback'])->name('applications.feedback');

    // Hour logging and approval.
    Route::post('/applications/{application}/hours', [HourLogController::class, 'store'])->name('hours.store');
    Route::post('/hours/{hourLog}/review', [HourLogController::class, 'review'])->name('hours.review');
    Route::delete('/hours/{hourLog}', [HourLogController::class, 'destroy'])->name('hours.destroy');

    // Account (Breeze) and volunteer profile.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/volunteer', [VolunteerProfileController::class, 'edit'])->name('profile.volunteer.edit');
    Route::patch('/profile/volunteer', [VolunteerProfileController::class, 'update'])->name('profile.volunteer.update');

    // Typeahead lookups used by the opportunity form.
    Route::get('/lookup/users', [LookupController::class, 'users'])->middleware('throttle:60,1')->name('lookup.users');
});

// Public opportunity page (after /opportunities/create so it isn't shadowed).
Route::get('/opportunities/{opportunity}', [OpportunityController::class, 'show'])->name('opportunities.show');

/*
|--------------------------------------------------------------------------
| Admin panel (role = admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'can:admin'])
    ->group(function () {
        Route::get('/', [Admin\AnalyticsController::class, 'index'])->name('dashboard');
        Route::get('/analytics/export', [Admin\AnalyticsController::class, 'export'])->name('analytics.export');

        Route::get('opportunities', [Admin\OpportunityController::class, 'index'])->name('opportunities.index');
        Route::patch('opportunities/{opportunity}/feature', [Admin\OpportunityController::class, 'feature'])->name('opportunities.feature');
        Route::delete('opportunities/{opportunity}', [Admin\OpportunityController::class, 'destroy'])->name('opportunities.destroy');

        Route::get('sync', [Admin\SyncController::class, 'index'])->name('sync.index');
        Route::post('sync', [Admin\SyncController::class, 'run'])->middleware('throttle:6,1')->name('sync.run');
        Route::get('sync/{syncRun}', [Admin\SyncController::class, 'show'])->name('sync.show');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/export', [Admin\UserController::class, 'export'])->name('users.export');
        Route::get('users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::patch('users/{user}/role', [Admin\UserController::class, 'role'])->name('users.role');
        Route::patch('users/{user}/suspend', [Admin\UserController::class, 'suspend'])->name('users.suspend');
        Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

        Route::get('skills', [Admin\SkillController::class, 'index'])->name('skills.index');
        Route::post('skills', [Admin\SkillController::class, 'store'])->name('skills.store');
        Route::put('skills/{skill}', [Admin\SkillController::class, 'update'])->name('skills.update');
        Route::delete('skills/{skill}', [Admin\SkillController::class, 'destroy'])->name('skills.destroy');
        Route::post('skills/merge', [Admin\SkillController::class, 'merge'])->name('skills.merge');
        Route::get('skills/export', [Admin\SkillController::class, 'export'])->name('skills.export');
        Route::post('skills/import', [Admin\SkillController::class, 'import'])->name('skills.import');

        Route::resource('categories', Admin\CategoryController::class)->except(['show', 'create', 'edit']);

        Route::get('activity', [Admin\ActivityController::class, 'index'])->name('activity.index');

        // Site content & configuration.
        Route::post('editor/upload-image', [Admin\EditorUploadController::class, 'image'])->name('editor.upload');
        Route::resource('pages', Admin\PageController::class)->except('show');
        Route::resource('media', Admin\MediaController::class)->only(['index', 'store', 'destroy']);
        Route::resource('menus', Admin\MenuController::class)->except('show');
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::get('email-templates', [Admin\EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{emailTemplate}/edit', [Admin\EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('email-templates/{emailTemplate}', [Admin\EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::get('email-templates/{emailTemplate}/preview', [Admin\EmailTemplateController::class, 'preview'])->name('email-templates.preview');
        Route::post('email-templates/{emailTemplate}/test', [Admin\EmailTemplateController::class, 'test'])->name('email-templates.test');
    });

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| CMS pages (catch-all — must remain last)
|--------------------------------------------------------------------------
*/
Route::get('/{page}', [PageController::class, 'show'])->name('pages.show');
