<?php

namespace App\Providers;

use App\Mail\TemplatedMail;
use App\Models\Application;
use App\Models\HourLog;
use App\Models\MenuItem;
use App\Models\Opportunity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /** Per-request cache of the data shared with every view. */
    private ?array $sharedViewData = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Two roles: "user" (everyone) and "admin" (the admin panel).
        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('manage-opportunity', fn (User $user, Opportunity $opportunity) => $opportunity->canBeManagedBy($user));

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Paginator::defaultView('vendor.pagination.ieee');

        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => TemplatedMail::for($notifiable, 'verify_email', [
            'action_url' => $url,
        ]));

        ResetPassword::toMailUsing(fn ($notifiable, string $token) => TemplatedMail::for($notifiable, 'reset_password', [
            'action_url' => route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]),
        ]));

        Event::listen(Verified::class, function (Verified $event): void {
            try {
                Mail::send(TemplatedMail::for($event->user, 'welcome', [
                    'action_url' => route('dashboard'),
                ]));
            } catch (\Throwable $e) {
                Log::error('Welcome email delivery failed: '.$e->getMessage());
            }
        });

        // Site settings + menus for every view. Guarded so early artisan calls
        // (before migrations) don't fail.
        View::composer('*', function ($view) {
            if ($this->sharedViewData === null) {
                if (! Schema::hasTable('menu_items')) {
                    $this->sharedViewData = ['headerMenu' => collect(), 'footerMenu' => collect(), 'siteSettings' => $this->defaultSettings()];
                } else {
                    $this->sharedViewData = [
                        'headerMenu' => $this->menu('header'),
                        'footerMenu' => $this->menu('footer'),
                        'siteSettings' => array_merge($this->defaultSettings(), [
                            'site_name' => Setting::get('site_name', 'IEEE Volunteering'),
                            'site_tagline' => Setting::get('site_tagline', 'Find your next way to give back to the IEEE community'),
                            'seo_default_description' => Setting::get('seo_default_description', ''),
                            'social_image' => Setting::get('social_image', ''),
                            'twitter_handle' => Setting::get('twitter_handle', ''),
                            'search_indexable' => (bool) Setting::get('search_indexable', false),
                        ]),
                    ];
                }
            }

            $view->with($this->sharedViewData);
        });

        // "Needs action" badge in the header: applications awaiting a decision
        // and hours awaiting approval on opportunities the user manages.
        View::composer('partials.header', function ($view) {
            $user = auth()->user();
            $count = 0;

            if ($user) {
                $owned = Opportunity::ownedBy($user)->pluck('id');
                if ($owned->isNotEmpty()) {
                    $count = Application::whereIn('opportunity_id', $owned)->where('status', Application::PENDING)->count()
                        + HourLog::whereIn('opportunity_id', $owned)->where('status', HourLog::PENDING)->count();
                }
            }

            $view->with('needsActionCount', $count);
        });
    }

    private function defaultSettings(): array
    {
        return [
            'site_name' => 'IEEE Volunteering',
            'site_tagline' => 'Find your next way to give back to the IEEE community',
            'seo_default_description' => '',
            'social_image' => '',
            'twitter_handle' => '',
            'search_indexable' => false,
        ];
    }

    private function menu(string $location)
    {
        return MenuItem::with('children')
            ->where('location', $location)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('display_order')
            ->get();
    }
}
