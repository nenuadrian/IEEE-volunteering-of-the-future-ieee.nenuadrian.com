<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Reference data every install needs (safe to re-run): opportunity types,
 * the skills taxonomy, CMS pages, menus, settings and the first admin.
 */
class CoreSeeder extends Seeder
{
    public const CATEGORIES = [
        'Content-based' => ['#00629b', 'Create or edit content — articles, newsletters, videos, podcasts, social media and design.'],
        'Event-based' => ['#e87722', 'Plan, run or support conferences, workshops, meetups, hackathons and webinars.'],
        'General' => ['#6b6b6b', 'Committee roles, ambassador programmes, mentoring and general support for IEEE units.'],
        'Humanitarian' => ['#00843d', 'Apply technology for the benefit of humanity through SIGHT, HTB and community projects.'],
        'Strategy' => ['#4a3aa7', 'Shape plans, partnerships, metrics and long-term direction for IEEE organizational units.'],
        'Technical' => ['#0083a9', 'Paper reviewing, standards work, technical committees and engineering projects.'],
    ];

    public const SKILLS = [
        'Communication' => [
            'Written communication', 'Public speaking', 'Public Relations', 'Social media management',
            'Online content creation', 'Online marketing', 'Graphic design', 'A/V recording and editing',
            'Photography', 'Translation', 'Newsletter editing', 'Reporting', 'Storytelling',
        ],
        'Leadership & management' => [
            'Project planning and management', 'Strategy development', 'Talent management',
            'External partnership management', 'Fundraising', 'Financial management', 'Team leadership',
            'Mentoring', 'Conference/event organizing', 'Volunteer coordination', 'Entrepreneurial experience',
            'Application or grant writing', 'Negotiation',
        ],
        'Research & standards' => [
            'Academic research', 'Scientific paper writing', 'Scientific paper review', 'Data analysis',
            'Technical writing', 'Standards development', 'Survey design',
        ],
        'Technical' => [
            'Web and mobile development', 'Object-oriented programming', 'Machine Learning and AI',
            'Cloud Computing', 'Cyber Security', 'IoT', '5G', 'Circuit design', 'Power Electronics', 'Robotics',
            'Blockchain', 'Distributed systems', 'Image processing', 'Smart Grid',
            'Intelligent transportation systems', 'Autonomous Vehicles', 'Cooperative mobility', 'Smart mobility',
            'Signal processing', 'Embedded systems', 'Renewable energy', 'Biomedical engineering',
            'Antennas and propagation', 'Quantum computing', 'Data visualization', 'UX design',
            'Database administration',
        ],
        'Education & outreach' => [
            'Teaching and training', 'STEM outreach', 'Curriculum development', 'Workshop facilitation',
        ],
        'Humanitarian' => [
            'Humanitarian technology', 'Community development', 'Disaster response',
        ],
    ];

    public function run(): void
    {
        $this->categories();
        $this->skills();
        $this->settings();
        $admin = $this->admin();
        $this->pages($admin);
        $this->menus();
    }

    private function categories(): void
    {
        $order = 0;
        foreach (self::CATEGORIES as $name => [$color, $description]) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'color' => $color, 'description' => $description, 'display_order' => $order++, 'is_active' => true],
            );
        }
    }

    private function skills(): void
    {
        foreach (self::SKILLS as $category => $names) {
            foreach ($names as $name) {
                $skill = Skill::findOrCreateByName($name, $category);
                if (! $skill->category) {
                    $skill->update(['category' => $category]);
                }
            }
        }
    }

    private function settings(): void
    {
        $defaults = [
            'site_name' => 'IEEE Volunteering',
            'site_tagline' => 'Find your next way to give back to the IEEE community.',
            'contact_email' => config('mail.from.address'),
            'seo_default_description' => 'Discover volunteering opportunities across IEEE regions, sections, societies and councils — and track the impact you make.',
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::query()->find($key) === null) {
                Setting::put($key, $value);
            }
        }
    }

    private function admin(): User
    {
        $email = env('SEED_ADMIN_EMAIL', 'admin@example.com');

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            $existing->update(['role' => User::ROLE_ADMIN]);

            return $existing;
        }

        $password = env('SEED_ADMIN_PASSWORD') ?: (app()->environment('production') ? Str::password(16) : 'password');

        $admin = User::create([
            'name' => env('SEED_ADMIN_NAME', 'Platform Admin'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'created_at' => now()->subMonths(30)])->save();
        $admin->profile->update([
            'headline' => 'IEEE Volunteering platform team',
            'region' => 'R8',
            'availability' => 'limited',
            'is_public' => false,
        ]);

        $this->command?->info("Admin account: {$email} / {$password}  (change this password after first login)");

        return $admin;
    }

    private function pages(User $admin): void
    {
        $dir = database_path('seeders/data/pages');

        foreach ([
            'about' => 'About IEEE Volunteering',
            'how-it-works' => 'How it works',
            'faq' => 'Frequently asked questions',
        ] as $slug => $title) {
            if (Page::query()->where('slug', $slug)->exists()) {
                continue;
            }

            Page::create([
                'user_id' => $admin->id,
                'title' => $title,
                'slug' => $slug,
                'body' => clean(file_get_contents("{$dir}/{$slug}.html"), 'content'),
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
    }

    private function menus(): void
    {
        if (MenuItem::query()->where('location', 'footer')->exists()) {
            return;
        }

        $items = [
            ['About', 'page', 'about', false],
            ['How it works', 'page', 'how-it-works', false],
            ['FAQ', 'page', 'faq', false],
            ['Contact us', 'route', 'contact.show', false],
            ['Nondiscrimination Policy', 'url', 'https://www.ieee.org/about/corporate/governance/p9-26.html', true],
            ['IEEE Privacy Policy', 'url', 'https://www.ieee.org/security-privacy.html', true],
        ];

        foreach ($items as $i => [$label, $type, $value, $newTab]) {
            MenuItem::create([
                'location' => 'footer', 'label' => $label, 'link_type' => $type, 'value' => $value,
                'display_order' => $i, 'is_active' => true, 'new_tab' => $newTab,
            ]);
        }
    }
}
