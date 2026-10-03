<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Category;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use App\Services\IeeeOpportunitySync;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic, backdated demo data so every page and chart has something to
 * show from day one ("fake history, real from now on"):
 *
 *  - ~420 volunteers across all ten IEEE regions, joining over 30 months
 *  - the real opportunities from volunteer.ieee.org (bundled snapshot)
 *  - ~160 locally created opportunities with owners and co-owners
 *  - applications, decisions, hour logs, ratings, endorsements, saves,
 *    searches and the matching activity stream
 *
 * All demo accounts use the reserved @volunteer-demo.test domain so they can
 * never receive email and can be removed with `php artisan demo:purge`.
 */
class DemoDataSeeder extends Seeder
{
    public const DOMAIN = 'volunteer-demo.test';

    private Generator $faker;

    private Carbon $now;

    private Carbon $origin;

    /** @var Collection<int, User> */
    private Collection $users;

    /** @var array<int, array<int,int>> user id => skill ids */
    private array $userSkills = [];

    /** @var array<string,int> */
    private array $skillIds = [];

    private array $activities = [];

    private User $admin;

    private User $demo;

    private string $password;

    /** Faker locale per country (Latin-script names); "@pool" uses a transliterated name pool below. */
    private const COUNTRY_LOCALES = [
        'United States' => 'en_US', 'Canada' => 'en_CA', 'United Kingdom' => 'en_GB', 'Germany' => 'de_DE',
        'France' => 'fr_FR', 'Italy' => 'it_IT', 'Spain' => 'es_ES', 'Romania' => 'ro_RO', 'Poland' => 'pl_PL',
        'Netherlands' => 'nl_NL', 'Switzerland' => 'de_CH', 'Sweden' => 'sv_SE', 'Portugal' => 'pt_PT',
        'Turkiye' => 'tr_TR', 'Hungary' => 'hu_HU', 'Nigeria' => 'en_NG', 'Kenya' => 'en_UG', 'South Africa' => 'en_ZA',
        'Brazil' => 'pt_BR', 'Mexico' => 'es_ES', 'Colombia' => 'es_ES', 'Peru' => 'es_PE', 'Chile' => 'es_ES',
        'Argentina' => 'es_AR', 'Ecuador' => 'es_PE', 'India' => 'en_IN', 'Singapore' => 'en_SG',
        'Malaysia' => 'ms_MY', 'Indonesia' => 'id_ID', 'Philippines' => 'en_PH', 'Australia' => 'en_AU',
        'New Zealand' => 'en_NZ', 'Bangladesh' => 'en_IN', 'Sri Lanka' => 'en_IN', 'Pakistan' => 'en_IN',
        'Greece' => '@greek', 'Egypt' => '@arabic', 'Saudi Arabia' => '@arabic', 'United Arab Emirates' => '@arabic',
        'China' => '@chinese', 'Hong Kong' => '@chinese', 'Taiwan' => '@chinese', 'Japan' => '@japanese',
        'South Korea' => '@korean', 'Thailand' => '@thai',
    ];

    private const NAME_POOLS = [
        'greek' => [['Nikos', 'Eleni', 'Giorgos', 'Maria', 'Dimitris', 'Katerina', 'Yannis', 'Sofia', 'Kostas', 'Anna'], ['Papadopoulos', 'Georgiou', 'Nikolaidis', 'Konstantinou', 'Papadakis', 'Vlachos', 'Ioannou', 'Karagiannis']],
        'arabic' => [['Ahmed', 'Omar', 'Youssef', 'Mariam', 'Fatima', 'Layla', 'Khaled', 'Nour', 'Hassan', 'Salma', 'Karim', 'Amira', 'Tarek', 'Hana'], ['El-Sayed', 'Hassan', 'Mansour', 'Abdallah', 'Farouk', 'Haddad', 'Nasser', 'Saleh', 'Khalil', 'Ibrahim', 'Al-Harbi', 'Al-Mansoori']],
        'chinese' => [['Wei', 'Jing', 'Hao', 'Xin', 'Yu', 'Lei', 'Min', 'Jun', 'Ying', 'Chen', 'Mei', 'Zhen'], ['Wang', 'Li', 'Zhang', 'Liu', 'Chen', 'Yang', 'Huang', 'Zhao', 'Wu', 'Zhou', 'Lin', 'Xu']],
        'japanese' => [['Haruto', 'Yui', 'Sota', 'Aoi', 'Ren', 'Hina', 'Kenji', 'Sakura', 'Takumi', 'Yuki'], ['Sato', 'Suzuki', 'Takahashi', 'Tanaka', 'Watanabe', 'Ito', 'Yamamoto', 'Nakamura']],
        'korean' => [['Min-jun', 'Seo-yeon', 'Ji-ho', 'Ha-eun', 'Do-yun', 'Ji-woo', 'Seo-jun', 'Su-bin'], ['Kim', 'Lee', 'Park', 'Choi', 'Jung', 'Kang', 'Yoon', 'Han']],
        'thai' => [['Somchai', 'Nattaya', 'Anan', 'Pimchanok', 'Krit', 'Siriporn', 'Thanawat', 'Kanya'], ['Srisuk', 'Wongsawat', 'Chaiyaporn', 'Boonmee', 'Saetang', 'Rattanakorn']],
    ];

    /** Faker's en_* surname lists are whimsical; use common surnames instead. */
    private const COMMON_SURNAMES = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
        'Hernandez', 'Lopez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee',
        'Thompson', 'White', 'Harris', 'Clark', 'Lewis', 'Robinson', 'Walker', 'Young', 'Allen', 'King',
        'Wright', 'Scott', 'Nguyen', 'Hill', 'Green', 'Adams', 'Baker', 'Nelson', 'Carter', 'Mitchell',
        'Patel', 'Shah', 'Chen', 'Kim', 'Rivera', 'Campbell', 'Parker', 'Evans', 'Edwards', 'Collins',
        'Stewart', 'Morris', 'Murphy', 'Cook', 'Rogers', 'Morgan', 'Cooper', 'Peterson', 'Reed', 'Bailey',
        'Bell', 'Kelly', 'Howard', 'Ward', 'Cox', 'Richardson', 'Wood', 'Watson', 'Brooks', 'Bennett',
        'Gray', 'James', 'Hughes', 'Price', 'Long', 'Foster', 'Ross', 'Powell', 'Sullivan', 'Russell',
        'Jenkins', 'Perry', 'Butler', 'Fisher', 'Henderson', 'Coleman', 'Patterson', 'Jordan', 'Reynolds',
        'Hamilton', 'Graham', 'Wallace', 'Griffin', 'West', 'Cole', 'Hayes', 'Gibson', 'Ellis', 'Stevens',
        'Murray', 'Ford', 'Marshall', 'Owens', 'McDonald', 'Harrison', 'Kennedy', 'Wells', 'Webb', 'Tucker',
        'Freeman', 'Burns', 'Henry', 'Simpson', 'Crawford', 'Porter', 'Mason', 'Shaw', 'Gordon', 'Hunter',
        'Palmer', 'Robertson', 'Black', 'Holmes', 'Stone', 'Boyd', 'Mills', 'Warren', 'Fox', 'Rose',
        'Ferguson', 'Ryan', 'Weaver', 'Daniels', 'Gardner', 'Payne', 'Dunn', 'Pierce', 'Arnold', 'Tran',
        'Spencer', 'Hawkins', 'Grant', 'Hansen', 'Hart', 'Elliott', 'Cunningham', 'Knight', 'Bradley', 'O\'Brien',
    ];

    private const REGION_WEIGHTS = ['R10' => 30, 'R8' => 22, 'R9' => 10, 'R7' => 5, 'R1' => 6, 'R2' => 5, 'R3' => 5, 'R4' => 6, 'R5' => 5, 'R6' => 6];

    private const GRADE_WEIGHTS = ['M' => 28, 'StM' => 18, 'GSM' => 16, 'SM' => 14, 'AM' => 5, 'LM' => 5, 'F' => 3, 'LS' => 2, 'LF' => 1, 'AF' => 4, 'INDV' => 2, 'SA MBR' => 2];

    private const HEADLINES = [
        'Electrical engineer & YP volunteer', 'PhD candidate in machine learning', 'Embedded systems developer',
        'Power systems engineer', 'Student branch chair', 'Software engineer and mentor', 'Robotics researcher',
        'Telecom engineer (5G/RAN)', 'Data scientist', 'Professor of electronics', 'Product manager in IoT',
        'Biomedical engineering student', 'Cybersecurity analyst', 'Renewable energy consultant',
        'Section treasurer & event organiser', 'Technical writer', 'UX designer for engineering tools',
        'Signal processing engineer', 'WIE affinity group chair', 'Cloud architect', 'Hardware design engineer',
        'STEM outreach coordinator', 'Antenna design engineer', 'Graduate student in smart grids',
    ];

    private const ORGANISATIONS = [
        'a national grid operator', 'a semiconductor company', 'a university research lab', 'a telecom operator',
        'a robotics start-up', 'a medical devices company', 'an automotive supplier', 'a cloud provider',
        'a consulting firm', 'a public research institute', 'a renewable energy developer', 'an aerospace company',
    ];

    private const CONFERENCES = [
        'IEEE ICC', 'IEEE GLOBECOM', 'IEEE IROS', 'IEEE ICASSP', 'IEEE VTC-Spring', 'IEEE PES General Meeting',
        'IEEE TENCON', 'IEEE LATINCOM', 'IEEE SusTech', 'IEEE R10 HTC', 'IEEE ISGT Europe', 'IEEE CDC',
    ];

    private const EVENTS = [
        'IEEE Sections Congress', 'R8 YP Summit', 'WIE International Leadership Summit', 'Student Branch Congress',
        'IEEE Day Celebration', 'IEEE R10 Student, YP, WIE & LM Congress', 'IEEE Humanitarian Tech Summit',
        'SB Leadership Training Workshop', 'YP Career Fair', 'IEEE Xtreme Programming Competition',
    ];

    private const TOPICS = ['Machine Learning', '5G', 'Smart Grid', 'Robotics', 'Cyber Security', 'IoT', 'Cloud Computing', 'Quantum Computing', 'Signal Processing', 'Embedded Systems'];

    private const STANDARDS = ['IEEE 802.11 (Wi-Fi)', 'IEEE 2030.5 (Smart Energy Profile)', 'IEEE 7000 (Ethics in System Design)', 'IEEE 1547 (DER Interconnection)', 'IEEE P2874 (Spatial Web)', 'IEEE 2413 (IoT Architecture)'];

    private const HUMANITARIAN = ['Clean Water Monitoring', 'Off-grid Solar for Rural Clinics', 'Flood Early-warning Sensors', 'Assistive Technology Workshop', 'Low-cost Air Quality Network', 'Digital Classrooms for Rural Schools'];

    public function run(): void
    {
        mt_srand(2026);
        $this->faker = FakerFactory::create('en_US');
        $this->faker->seed(2026);
        $this->now = now()->startOfMinute();
        $this->origin = $this->now->copy()->subMonths(30)->startOfMonth();
        $this->password = Hash::make(env('DEMO_PASSWORD') ?: (app()->environment('production') ? Str::password(20) : 'password'));
        $this->skillIds = Skill::pluck('id', 'name')->all();
        $this->admin = User::admins()->orderBy('id')->firstOrFail();

        $count = (int) env('DEMO_USERS', 420);

        $this->command?->info("Creating {$count} demo volunteers…");
        $this->createUsers($count);

        $this->command?->info('Importing opportunities from the volunteer.ieee.org snapshot…');
        $this->importSnapshot();

        $this->command?->info('Creating locally organised opportunities…');
        $this->createLocalOpportunities(160);
        $this->createDemoUserOpportunities();

        $this->command?->info('Simulating applications, hours and endorsements…');
        $this->simulateParticipation();

        $this->command?->info('Saved opportunities, searches and activity…');
        $this->savedOpportunities();
        $this->searchLogs(4200);
        $this->flushActivities();

        $this->command?->info('Demo login: volunteer@'.self::DOMAIN.' / '.(env('DEMO_PASSWORD') ?: (app()->environment('production') ? '(random — set DEMO_PASSWORD)' : 'password')));
    }

    // =====================================================================
    // Users
    // =====================================================================

    private function createUsers(int $count): void
    {
        $skillNames = array_keys($this->skillIds);
        $usedEmails = [];
        $memberNumber = 90100000;

        // The showcase account people can sign in with.
        $this->demo = $this->makeUser('Alex Rivera', 'volunteer@'.self::DOMAIN, $this->origin->copy()->addMonths(2), 'R8', 'United Kingdom and Ireland', 'United Kingdom', 'en_GB', $memberNumber++);
        $this->demo->profile->update([
            'headline' => 'Software engineer, YP volunteer and mentor',
            'bio' => "I'm a software engineer based in London who loves helping IEEE members connect. I've volunteered as a Young Professionals event organiser, a paper reviewer and a mentor for student branches across Region 8.\n\nI'm especially interested in opportunities around machine learning, technical content and growing inclusive communities.",
            'cv_statement' => 'Engineer and community builder with five years of IEEE volunteering across events, content and mentoring. I enjoy turning ideas into well-run programmes and helping new volunteers find their place.',
            'membership_grade' => 'M', 'member_since' => 2019, 'city' => 'London', 'society' => 'IEEE Young Professionals',
            'linkedin_url' => 'https://www.linkedin.com/in/example-alex-rivera', 'github_url' => 'https://github.com/example-alex',
            'availability' => 'available', 'hours_per_month' => 12, 'show_email' => true,
        ]);
        $this->attachSkills($this->demo, ['Web and mobile development', 'Machine Learning and AI', 'Mentoring', 'Conference/event organizing', 'Written communication', 'Public speaking', 'Project planning and management', 'Scientific paper review']);

        for ($i = 0; $i < $count; $i++) {
            $region = $this->weighted(self::REGION_WEIGHTS);
            $sections = config('volunteering.sections')[$region];
            $section = array_keys($sections)[mt_rand(0, count($sections) - 1)];
            $country = $sections[$section];
            $locale = self::COUNTRY_LOCALES[$country] ?? 'en_US';
            if ($section === 'Montreal') {
                $locale = 'fr_CA';
            }
            $f = FakerFactory::create(str_starts_with($locale, '@') ? 'en_US' : $locale);
            $f->seed(1000 + $i);

            $name = $this->personName($f, $locale);

            $base = Str::slug(Str::ascii($name), '.') ?: 'volunteer';
            $email = $base.'@'.self::DOMAIN;
            $n = 1;
            while (isset($usedEmails[$email])) {
                $email = $base.(++$n).'@'.self::DOMAIN;
            }
            $usedEmails[$email] = true;

            // Platform growth: more sign-ups recently.
            $joined = $this->origin->copy()->addSeconds((int) (pow(mt_rand() / mt_getrandmax(), 0.75) * $this->origin->diffInSeconds($this->now->copy()->subDays(1))));

            $user = $this->makeUser($name, $email, $joined, $region, $section, $country, $locale, $memberNumber + $i * 7);

            $grade = $this->weighted(self::GRADE_WEIGHTS);
            $profession = $this->pick(self::HEADLINES);
            $user->profile->update([
                'headline' => mt_rand(1, 100) <= 85 ? $profession : null,
                'bio' => mt_rand(1, 100) <= 80 ? $this->bio($user->firstName(), $profession, $section, $country) : null,
                'membership_grade' => mt_rand(1, 100) <= 92 ? $grade : null,
                'member_since' => in_array($grade, ['StM', 'GSM'], true) ? mt_rand(2019, 2025) : mt_rand(1992, 2024),
                'city' => mt_rand(1, 100) <= 55 ? $this->cityFor($f, $country) : null,
                'society' => mt_rand(1, 100) <= 55 ? $this->pick(config('volunteering.societies')) : null,
                'linkedin_url' => mt_rand(1, 100) <= 45 ? 'https://www.linkedin.com/in/example-'.Str::slug(Str::ascii($name)) : null,
                'website_url' => mt_rand(1, 100) <= 10 ? 'https://example.com/'.Str::slug(Str::ascii($name)) : null,
                'availability' => $this->weighted(['available' => 70, 'limited' => 22, 'unavailable' => 8]),
                'hours_per_month' => mt_rand(1, 100) <= 60 ? $this->pick([2, 4, 5, 8, 10, 15, 20]) : null,
                'is_public' => mt_rand(1, 100) <= 94,
                'show_email' => mt_rand(1, 100) <= 25,
            ]);

            $this->attachSkills($user, (array) array_rand(array_flip($skillNames), mt_rand(2, 9)));
        }

        $this->users = User::with('profile')->where('email', 'like', '%@'.self::DOMAIN)->get()->keyBy('id');

        // A handful of suspended accounts so the admin tools have something to show.
        $this->users->where('id', '!=', $this->demo->id)->random(4)->each(fn ($u) => $u->forceFill(['suspended_at' => $this->now->copy()->subDays(mt_rand(5, 120))])->saveQuietly());
    }

    private function makeUser(string $name, string $email, Carbon $joined, string $region, string $section, string $country, string $locale, int $memberNumber): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $this->password, 'role' => User::ROLE_USER]);

        $lastLogin = $joined->copy()->addSeconds((int) (pow(mt_rand() / mt_getrandmax(), 0.3) * max(1, $joined->diffInSeconds($this->now))));

        $user->forceFill([
            'email_verified_at' => $joined->copy()->addMinutes(mt_rand(2, 600)),
            'created_at' => $joined,
            'updated_at' => $joined,
            'last_login_at' => $lastLogin,
        ])->saveQuietly();

        $user->profile->forceFill([
            'region' => $region,
            'section' => $section,
            'country' => $country,
            'ieee_member_number' => (string) $memberNumber,
            'created_at' => $joined,
            'updated_at' => $joined,
        ])->saveQuietly();

        $this->activity('user.registered', $user->id, $user, $joined);

        return $user;
    }

    private function attachSkills(User $user, array $names): void
    {
        $ids = array_values(array_filter(array_map(fn ($n) => $this->skillIds[$n] ?? null, $names)));
        $user->skills()->syncWithoutDetaching($ids);
        $this->userSkills[$user->id] = array_values(array_unique(array_merge($this->userSkills[$user->id] ?? [], $ids)));
    }

    // =====================================================================
    // Opportunities
    // =====================================================================

    private function importSnapshot(): void
    {
        $hits = json_decode(file_get_contents(database_path('seeders/data/ieee-opportunities.json')), true) ?: [];
        $sync = app(IeeeOpportunitySync::class);
        $run = $sync->run(null, $hits, closeMissing: false);

        $run->update(['log' => array_merge([['action' => 'note', 'title' => 'Initial import from the bundled snapshot (database seeder)', 'id' => null]], $run->log ?? [])]);

        // The skills on imported opportunities should exist in the volunteer pool too.
        $this->skillIds = Skill::pluck('id', 'name')->all();
    }

    private function createLocalOpportunities(int $count): void
    {
        $templates = $this->templates();
        $categories = Category::pluck('id', 'name');
        $organisers = $this->users->filter(fn ($u) => ! $u->isSuspended())->random((int) ($this->users->count() * 0.22))->values();

        for ($i = 0; $i < $count; $i++) {
            $categoryName = $this->weighted(['Event-based' => 24, 'Content-based' => 20, 'General' => 18, 'Technical' => 18, 'Strategy' => 11, 'Humanitarian' => 9]);
            $tpl = $this->pick($templates[$categoryName]);
            $owner = $organisers[$i % $organisers->count()];
            $profile = $owner->profile;

            $created = $this->origin->copy()->addSeconds((int) (pow(mt_rand() / mt_getrandmax(), 0.8) * $this->origin->diffInSeconds($this->now->copy()->subDays(2))));
            if ($created->lt($owner->created_at)) {
                $created = $owner->created_at->copy()->addDays(mt_rand(1, 20));
            }
            if ($created->gt($this->now)) {
                $created = $this->now->copy()->subDays(mt_rand(1, 10));
            }

            $opportunity = $this->makeOpportunity($tpl, $categories[$categoryName], $owner, $created, $profile->region, $profile->section, $profile->country);

            // Some opportunities are run by a small team.
            if (mt_rand(1, 100) <= 32) {
                $eligible = $this->users->filter(fn ($u) => $u->id !== $owner->id && $u->created_at->lt($created) && ! $u->isSuspended());
                $co = $eligible->isEmpty() ? collect() : $eligible->random(min($eligible->count(), mt_rand(1, 3)));
                foreach ($co as $person) {
                    $opportunity->owners()->attach($person->id, ['role' => 'co_owner', 'added_by' => $owner->id, 'created_at' => $created, 'updated_at' => $created]);
                    $this->activity('opportunity.owner_added', $owner->id, $opportunity, $created->copy()->addMinutes(10), ['user_id' => $person->id, 'name' => $person->name]);
                }
            }

            // A few re-runs of earlier opportunities.
            if ($i > 20 && mt_rand(1, 100) <= 7) {
                $source = Opportunity::where('source', Opportunity::SOURCE_LOCAL)->where('created_at', '<', $created->copy()->subMonths(3))->inRandomOrder()->first();
                if ($source) {
                    $opportunity->forceFill(['cloned_from_id' => $source->id, 'title' => $source->title, 'description' => $source->description])->saveQuietly();
                    $opportunity->skills()->sync($source->skills()->pluck('skills.id'));
                }
            }
        }

        // Feature a few open ones on the home page.
        Opportunity::open()->where('source', Opportunity::SOURCE_LOCAL)->inRandomOrder()->take(4)->update(['is_featured' => true]);
    }

    /** Two opportunities the demo account runs, one live with applicants waiting. */
    private function createDemoUserOpportunities(): void
    {
        $templates = $this->templates();
        $cat = Category::pluck('id', 'name');

        $past = $this->makeOpportunity($templates['Event-based'][0], $cat['Event-based'], $this->demo, $this->now->copy()->subMonths(9), 'R8', 'United Kingdom and Ireland', 'United Kingdom', forceStatus: Opportunity::COMPLETED);
        $live = $this->makeOpportunity($templates['Technical'][4], $cat['Technical'], $this->demo, $this->now->copy()->subDays(12), 'R8', 'United Kingdom and Ireland', 'United Kingdom', forceStatus: Opportunity::OPEN);
        $live->update(['volunteers_needed' => 4, 'is_featured' => true]);

        $co = $this->users->where('id', '!=', $this->demo->id)->filter(fn ($u) => $u->profile->region === 'R8' && ! $u->isSuspended())->take(2);
        foreach ($co as $person) {
            $live->owners()->attach($person->id, ['role' => 'co_owner', 'added_by' => $this->demo->id]);
        }

        // Clone of the past event, still a draft — shows the clone workflow.
        $this->demo->setRelation('profile', $this->demo->profile);
        Carbon::setTestNow($this->now->copy()->subDays(3));
        $past->cloneFor($this->demo);
        Carbon::setTestNow();
    }

    private function makeOpportunity(array $tpl, int $categoryId, User $owner, Carbon $created, ?string $region, ?string $section, ?string $country, ?string $forceStatus = null): Opportunity
    {
        $vars = $this->vars($section, $country, $created);
        $title = Str::limit($this->fill($tpl['title'], $vars), 200, '');
        $size = $tpl['size'];
        $duration = match ($size) {
            'Quick task' => mt_rand(1, 7),
            'Full day' => mt_rand(0, 1),
            'Small project' => mt_rand(14, 45),
            'Larger project' => mt_rand(60, 180),
            default => mt_rand(180, 365),
        };
        $start = $created->copy()->addDays(mt_rand(3, 35))->startOfDay();
        $end = $start->copy()->addDays($duration);
        $online = mt_rand(1, 100) <= ($tpl['online'] ?? 70);

        $status = $forceStatus ?? match (true) {
            $end->lt($this->now) => $this->weighted([Opportunity::COMPLETED => 92, Opportunity::CANCELLED => 8]),
            $start->lte($this->now) => $this->weighted([Opportunity::OPEN => 50, Opportunity::IN_PROGRESS => 40, Opportunity::ON_HOLD => 10]),
            default => $this->weighted([Opportunity::OPEN => 90, Opportunity::ON_HOLD => 10]),
        };
        if (! $forceStatus && $created->gt($this->now->copy()->subDays(50)) && mt_rand(1, 100) <= 6) {
            $status = Opportunity::DRAFT;
        }

        $needed = mt_rand($tpl['needed'][0], $tpl['needed'][1]);
        $hours = mt_rand($tpl['hours'][0], $tpl['hours'][1]);

        $opportunity = new Opportunity([
            'slug' => Opportunity::generateSlug($title),
            'title' => $title,
            'description' => $this->fill(implode("\n\n", $tpl['desc']), $vars),
            'category_id' => $categoryId,
            'status' => $status,
            'is_online' => $online,
            'location' => $online ? null : trim(($section && $section !== $country ? $section.', ' : '').$country, ', '),
            'country' => $online ? null : $country,
            'region' => mt_rand(1, 100) <= 70 ? $region : null,
            'section' => mt_rand(1, 100) <= 55 ? $section : null,
            'society' => mt_rand(1, 100) <= 60 ? ($owner->profile->society ?: $this->pick(config('volunteering.societies'))) : null,
            'experience_level' => $tpl['exp'],
            'project_size' => $size,
            'membership_grades' => mt_rand(1, 100) <= 55 ? null : array_values(array_unique([$this->weighted(self::GRADE_WEIGHTS), 'M', 'SM', 'GSM'])),
            'upskills' => $tpl['upskills'],
            'ideal_traits' => $tpl['traits'] ?? null,
            'hours_estimate' => $hours,
            'hours_frequency' => $tpl['freq'],
            'volunteers_needed' => $needed,
            'start_date' => $start,
            'end_date' => $end,
            'source' => Opportunity::SOURCE_LOCAL,
            'created_by' => $owner->id,
            'views_count' => mt_rand(15, 60) * ($needed + 1),
            'published_at' => $status === Opportunity::DRAFT ? null : $created,
            'closed_at' => in_array($status, [Opportunity::COMPLETED, Opportunity::CANCELLED], true) ? $end->copy()->addDays(mt_rand(0, 10))->min($this->now) : null,
        ]);
        $opportunity->created_at = $created;
        $opportunity->updated_at = $created;
        $opportunity->saveQuietly();

        $opportunity->skills()->sync(array_values(array_filter(array_map(fn ($n) => $this->skillIds[$n] ?? null, $tpl['skills']))));
        $opportunity->owners()->attach($owner->id, ['role' => 'owner', 'added_by' => $owner->id, 'created_at' => $created, 'updated_at' => $created]);

        $this->activity($status === Opportunity::DRAFT ? 'opportunity.created' : 'opportunity.published', $owner->id, $opportunity, $created);

        return $opportunity;
    }

    // =====================================================================
    // Participation: applications, hours, ratings, endorsements
    // =====================================================================

    private function simulateParticipation(): void
    {
        $opportunities = Opportunity::query()
            ->whereNot('status', Opportunity::DRAFT)
            ->with(['skills', 'owners'])
            ->get();

        $active = $this->users->filter(fn ($u) => ! $u->isSuspended());
        $engagement = $active->mapWithKeys(fn ($u) => [$u->id => $u->id === $this->demo->id ? 5 : $this->weighted([1 => 30, 2 => 30, 3 => 22, 5 => 12, 8 => 6])]);
        // Lifetime application caps keep any one person's history believable.
        $caps = $engagement->map(fn ($w, $id) => $id === $this->demo->id ? 16 : $w * 3 + 1);
        $appliedCount = [];

        $demoLiveId = Opportunity::where('created_by', $this->demo->id)->where('status', Opportunity::OPEN)->value('id');
        $applications = [];
        $hourLogs = [];
        $endorsements = [];
        $endorsementSkills = [];

        foreach ($opportunities as $opportunity) {
            $published = $opportunity->published_at ?? $opportunity->created_at;
            $ownerIds = $opportunity->owners->pluck('id')->all();
            $deciders = $ownerIds ?: [$this->admin->id];
            $skillIds = $opportunity->skills->pluck('id')->all();
            $needed = max(1, $opportunity->volunteers_needed);

            $imported = $opportunity->source === Opportunity::SOURCE_IEEE;
            $target = $imported
                ? mt_rand(0, min(12, $needed * 3))
                : min(28, (int) round($needed * (mt_rand(12, 35) / 10)) + mt_rand(0, 3));

            if ($opportunity->id === $demoLiveId) {
                $target = 9;
            }

            // Who is likely to apply: people who joined before, with overlapping skills.
            $pool = $active->filter(fn ($u) => $u->created_at->lt($this->now->copy()->subDays(1)) && ! in_array($u->id, $ownerIds, true) && ($appliedCount[$u->id] ?? 0) < $caps[$u->id]);
            $candidates = $pool->sortByDesc(function ($u) use ($skillIds, $engagement, $opportunity) {
                $overlap = count(array_intersect($this->userSkills[$u->id] ?? [], $skillIds));
                $sameRegion = $opportunity->region && $u->profile->region === $opportunity->region ? 1.5 : 0;

                // The showcase account leans towards finished work so its CV has history.
                $showcase = $u->id === $this->demo->id && $opportunity->status === Opportunity::COMPLETED ? 6 : 0;

                return ($overlap * 2 + $sameRegion + $engagement[$u->id] + $showcase) * (mt_rand(50, 150) / 100);
            })->take($target)->values();

            $confirmed = 0;
            $firstAcceptance = null;

            foreach ($candidates as $k => $volunteer) {
                $windowEnd = ($opportunity->end_date ?? $this->now)->copy()->min($this->now);
                $earliest = $published->copy()->max($volunteer->created_at);
                if ($earliest->gte($windowEnd)) {
                    continue;
                }
                $applied = $earliest->copy()->addSeconds((int) (pow(mt_rand() / mt_getrandmax(), 1.8) * min($earliest->diffInSeconds($windowEnd), 60 * 86400)));
                $decidedAt = $applied->copy()->addHours(mt_rand(6, 240))->min($this->now);
                $recent = $applied->gt($this->now->copy()->subDays(12));

                $status = match ($opportunity->status) {
                    Opportunity::COMPLETED => $confirmed < $needed && mt_rand(1, 100) <= 78 ? Application::COMPLETED : $this->weighted([Application::REJECTED => 70, Application::WITHDRAWN => 30]),
                    Opportunity::CANCELLED => $this->weighted([Application::WITHDRAWN => 60, Application::REJECTED => 40]),
                    default => match (true) {
                        $recent && mt_rand(1, 100) <= 65 => Application::PENDING,
                        $confirmed < $needed && mt_rand(1, 100) <= 68 => Application::ACCEPTED,
                        default => $this->weighted([Application::REJECTED => 72, Application::WITHDRAWN => 18, Application::PENDING => 10]),
                    },
                };

                // The demo account always has a healthy mix.
                if ($volunteer->id === $this->demo->id && $status === Application::REJECTED && mt_rand(1, 100) <= 60) {
                    $status = $opportunity->status === Opportunity::COMPLETED ? Application::COMPLETED : Application::ACCEPTED;
                }

                $appliedCount[$volunteer->id] = ($appliedCount[$volunteer->id] ?? 0) + 1;
                $isConfirmed = in_array($status, Application::CONFIRMED, true);
                if ($isConfirmed) {
                    $confirmed++;
                    if ($confirmed === $needed) {
                        $firstAcceptance = $decidedAt;
                    }
                }

                $completedAt = null;
                if ($status === Application::COMPLETED) {
                    $completedAt = ($opportunity->closed_at ?? $opportunity->end_date ?? $decidedAt->copy()->addDays(20))->copy()->max($decidedAt)->min($this->now);
                }

                $decider = $this->pick($deciders);
                $applications[] = [
                    'opportunity_id' => $opportunity->id,
                    'user_id' => $volunteer->id,
                    'status' => $status,
                    'motivation' => $this->motivation($opportunity, $volunteer),
                    'owner_note' => $status === Application::REJECTED && mt_rand(1, 100) <= 40 ? 'Thank you for applying — we received many strong applications. Please keep an eye out for our next call!' : null,
                    'decided_by' => in_array($status, [Application::PENDING, Application::WITHDRAWN], true) ? null : $decider,
                    'decided_at' => in_array($status, [Application::PENDING, Application::WITHDRAWN], true) ? null : $decidedAt,
                    'completed_at' => $completedAt,
                    'withdrawn_at' => $status === Application::WITHDRAWN ? $decidedAt : null,
                    'owner_rating' => $status === Application::COMPLETED && mt_rand(1, 100) <= 85 ? $this->weighted([5 => 55, 4 => 35, 3 => 10]) : null,
                    'volunteer_rating' => $isConfirmed && mt_rand(1, 100) <= ($status === Application::COMPLETED ? 70 : 25) ? $this->weighted([5 => 50, 4 => 35, 3 => 12, 2 => 3]) : null,
                    'volunteer_feedback' => null,
                    'created_at' => $applied,
                    'updated_at' => $completedAt ?? $decidedAt,
                    '_decider' => $decider,
                    '_skills' => $skillIds,
                    '_owner' => $ownerIds[0] ?? $this->admin->id,
                    '_freq' => $opportunity->hours_frequency,
                    '_size' => $opportunity->project_size,
                    '_estimate' => $opportunity->hours_estimate,
                    '_end' => $opportunity->end_date,
                ];
            }

            if ($firstAcceptance) {
                $opportunity->forceFill(['filled_at' => $firstAcceptance])->saveQuietly();
            }
        }

        // Insert applications in chunks, keeping our metadata for the follow-ups.
        foreach (array_chunk($applications, 500) as $chunk) {
            DB::table('applications')->insert(array_map(fn ($row) => array_filter($row, fn ($k) => ! str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY), $chunk));
        }

        $ids = [];
        foreach (DB::table('applications')->get(['id', 'opportunity_id', 'user_id']) as $a) {
            $ids[$a->opportunity_id.'-'.$a->user_id] = $a->id;
        }

        $applicationMorph = (new Application)->getMorphClass();
        $hourMorph = (new HourLog)->getMorphClass();

        foreach ($applications as $row) {
            $appId = $ids[$row['opportunity_id'].'-'.$row['user_id']] ?? null;
            if (! $appId) {
                continue;
            }

            $this->activities[] = $this->activityRow('application.submitted', $row['user_id'], $applicationMorph, $appId, $row['created_at']);
            if ($row['decided_at']) {
                $type = $row['status'] === Application::REJECTED ? 'application.rejected' : 'application.accepted';
                $this->activities[] = $this->activityRow($type, $row['_decider'], $applicationMorph, $appId, $row['decided_at']);
            }
            if ($row['withdrawn_at']) {
                $this->activities[] = $this->activityRow('application.withdrawn', $row['user_id'], $applicationMorph, $appId, $row['withdrawn_at']);
            }
            if ($row['completed_at']) {
                $this->activities[] = $this->activityRow('application.completed', $row['_decider'], $applicationMorph, $appId, $row['completed_at']);
            }

            if (! in_array($row['status'], Application::CONFIRMED, true)) {
                continue;
            }

            // Hours.
            $from = Carbon::parse($row['decided_at'])->addDays(2);
            $until = ($row['completed_at'] ? Carbon::parse($row['completed_at']) : ($row['_end'] ? Carbon::parse($row['_end'])->min($this->now) : $this->now))->min($this->now);
            if ($from->gt($until)) {
                $from = $until->copy();
            }

            $single = in_array($row['_size'], ['Quick task', 'Full day'], true);
            $date = $from->copy();
            $guard = 0;
            do {
                $hours = $single
                    ? max(1, min(12, (int) ($row['_estimate'] ?: mt_rand(2, 8))))
                    : $this->pick([1, 1.5, 2, 2, 3, 3, 4, 5, 6]);
                $recent = $date->gt($this->now->copy()->subDays(16));
                $status = $row['status'] === Application::ACCEPTED && $recent && mt_rand(1, 100) <= 70
                    ? HourLog::PENDING
                    : $this->weighted([HourLog::APPROVED => 96, HourLog::REJECTED => 4]);
                $reviewed = $status === HourLog::PENDING ? null : $date->copy()->addDays(mt_rand(1, 6))->min($this->now);

                $hourLogs[] = [
                    'application_id' => $appId,
                    'user_id' => $row['user_id'],
                    'opportunity_id' => $row['opportunity_id'],
                    'worked_on' => $date->toDateString(),
                    'hours' => $hours,
                    'description' => mt_rand(1, 100) <= 60 ? $this->pick(['Prepared materials and attended the planning call', 'Reviewed submissions', 'Drafted and edited content', 'Coordinated with the team', 'Ran the session and wrapped up follow-ups', 'Worked on the deliverables agreed with the organisers', 'Volunteered on site']) : null,
                    'status' => $status,
                    'reviewed_by' => $reviewed ? $row['_decider'] : null,
                    'reviewed_at' => $reviewed,
                    'created_at' => $date->copy()->setTime(mt_rand(8, 21), mt_rand(0, 59)),
                    'updated_at' => $reviewed ?? $date,
                ];
                $date->addDays(mt_rand(6, 21));
                $guard++;
            } while (! $single && $date->lte($until) && $guard < 40);

            // Endorsements for a good share of completed contributions.
            if ($row['status'] === Application::COMPLETED && ($row['user_id'] === $this->demo->id || mt_rand(1, 100) <= 58)) {
                $endorsements[] = [
                    'user_id' => $row['user_id'],
                    'endorser_id' => $row['_decider'],
                    'opportunity_id' => $row['opportunity_id'],
                    'application_id' => $appId,
                    'message' => $this->endorsementText($this->users[$row['user_id']]->firstName() ?? 'They'),
                    'is_public' => true,
                    'created_at' => $row['completed_at'],
                    'updated_at' => $row['completed_at'],
                ];
                $shared = array_values(array_intersect($row['_skills'], $this->userSkills[$row['user_id']] ?? [])) ?: $row['_skills'];
                $endorsementSkills[$appId] = array_slice($this->shuffle($shared), 0, mt_rand(1, 3));
            }
        }

        foreach (array_chunk($hourLogs, 1000) as $chunk) {
            DB::table('hour_logs')->insert($chunk);
        }
        foreach (array_chunk($endorsements, 500) as $chunk) {
            DB::table('endorsements')->insert($chunk);
        }

        $endorsementIds = DB::table('endorsements')->pluck('id', 'application_id');
        $pivot = [];
        foreach ($endorsementSkills as $appId => $skills) {
            foreach ($skills as $skillId) {
                if (isset($endorsementIds[$appId])) {
                    $pivot[] = ['endorsement_id' => $endorsementIds[$appId], 'skill_id' => $skillId];
                }
            }
        }
        foreach (array_chunk($pivot, 1000) as $chunk) {
            DB::table('endorsement_skill')->insertOrIgnore($chunk);
        }

        // Activity for hours and endorsements (sampled for hours to keep the log readable).
        $endorsementMorph = (new \App\Models\Endorsement)->getMorphClass();
        foreach (DB::table('endorsements')->get(['id', 'endorser_id', 'created_at']) as $e) {
            $this->activities[] = $this->activityRow('endorsement.given', $e->endorser_id, $endorsementMorph, $e->id, $e->created_at);
        }
        foreach (DB::table('hour_logs')->get(['id', 'user_id', 'reviewed_by', 'status', 'created_at', 'reviewed_at', 'hours']) as $h) {
            if (mt_rand(1, 100) <= 45) {
                $this->activities[] = $this->activityRow('hours.logged', $h->user_id, $hourMorph, $h->id, $h->created_at, ['hours' => (float) $h->hours]);
            }
            if ($h->reviewed_at && mt_rand(1, 100) <= 30) {
                $this->activities[] = $this->activityRow('hours.'.$h->status, $h->reviewed_by, $hourMorph, $h->id, $h->reviewed_at, ['hours' => (float) $h->hours]);
            }
        }
    }

    private function savedOpportunities(): void
    {
        $visible = Opportunity::visible()->get(['id', 'created_at']);
        $rows = [];
        foreach ($this->users as $user) {
            $n = $user->id === $this->demo->id ? 5 : $this->weighted([0 => 35, 1 => 25, 2 => 18, 3 => 12, 5 => 10]);
            foreach ($visible->random(min($n, $visible->count())) as $o) {
                $at = $o->created_at->copy()->max($user->created_at)->addDays(mt_rand(0, 20))->min($this->now);
                $rows[$user->id.'-'.$o->id] = ['user_id' => $user->id, 'opportunity_id' => $o->id, 'created_at' => $at];
            }
        }
        foreach (array_chunk(array_values($rows), 1000) as $chunk) {
            DB::table('saved_opportunities')->insertOrIgnore($chunk);
        }
    }

    private function searchLogs(int $count): void
    {
        $terms = [
            'machine learning' => 9, 'event' => 8, 'social media' => 8, 'mentor' => 7, 'young professionals' => 7,
            'paper review' => 6, 'web' => 6, 'graphic design' => 5, 'women in engineering' => 5, 'student' => 6,
            'humanitarian' => 4, 'conference' => 6, 'newsletter' => 3, 'video' => 4, 'leadership' => 4, 'data' => 4,
            'python' => 3, 'standards' => 3, 'power' => 3, '5g' => 2, 'robotics' => 3, 'ambassador' => 4,
            'translation' => 2, 'india' => 3, 'online' => 3, 'committee' => 3,
        ];
        $zero = ['quantum photonics' => 2, 'blockchain voting' => 1, 'mobile app tester' => 2, 'drone racing' => 1, 'semiconductor fab' => 1, 'arabic translation' => 2, 'k-12 robotics coach' => 1, 'space weather' => 1];
        $regions = array_keys(config('volunteering.regions'));
        $sizes = array_keys(config('volunteering.project_sizes'));
        $start = $this->now->copy()->subMonths(12);
        $userIds = $this->users->keys()->all();

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $at = $start->copy()->addSeconds((int) (pow(mt_rand() / mt_getrandmax(), 0.8) * $start->diffInSeconds($this->now)));
            $scope = mt_rand(1, 100) <= 78 ? 'opportunities' : 'volunteers';
            $isZero = mt_rand(1, 100) <= 7;
            $query = mt_rand(1, 100) <= 72 ? ($isZero ? $this->weighted($zero) : $this->weighted($terms)) : null;
            $filters = array_filter([
                'region' => mt_rand(1, 100) <= 22 ? $this->pick($regions) : null,
                'size' => $scope === 'opportunities' && mt_rand(1, 100) <= 15 ? $this->pick($sizes) : null,
                'online' => $scope === 'opportunities' && mt_rand(1, 100) <= 25 ? true : null,
                'accepting' => $scope === 'opportunities' && mt_rand(1, 100) <= 30 ? true : null,
            ]);
            if (! $query && ! $filters) {
                $filters = ['online' => true];
            }

            $rows[] = [
                'user_id' => mt_rand(1, 100) <= 70 ? $this->pick($userIds) : null,
                'scope' => $scope,
                'query' => $query,
                'filters' => $filters ? json_encode($filters) : null,
                'results_count' => $isZero && $query ? 0 : mt_rand($filters ? 1 : 3, $query ? 25 : 60),
                'created_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('search_logs')->insert($chunk);
        }
    }

    // =====================================================================
    // Activity helpers
    // =====================================================================

    private function activity(string $type, ?int $userId, $subject, Carbon $at, array $props = []): void
    {
        $this->activities[] = $this->activityRow($type, $userId, $subject->getMorphClass(), $subject->getKey(), $at, $props);
    }

    private function activityRow(string $type, ?int $userId, string $subjectType, int $subjectId, $at, array $props = []): array
    {
        return [
            'user_id' => $userId,
            'type' => $type,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $props ? json_encode($props) : null,
            'created_at' => $at instanceof Carbon ? $at : Carbon::parse($at),
        ];
    }

    private function flushActivities(): void
    {
        usort($this->activities, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
        foreach (array_chunk($this->activities, 1000) as $chunk) {
            DB::table('activities')->insert($chunk);
        }
        $this->activities = [];
    }

    // =====================================================================
    // Text generation
    // =====================================================================

    private function templates(): array
    {
        $comm = ['Communication', 'Organization Abilities'];

        return [
            'Content-based' => [
                ['title' => 'Newsletter Editor — {unit}', 'skills' => ['Newsletter editing', 'Written communication', 'Graphic design'], 'size' => 'Ongoing', 'upskills' => $comm, 'hours' => [3, 6], 'freq' => 'month', 'needed' => [1, 2], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['{unit} publishes a quarterly newsletter that reaches members across {region_name}. We are looking for an editor to plan each issue, collect stories from volunteers and polish the final copy.', 'You will work with a small team of contributors, keep the editorial calendar on track and coordinate with our designer. Great writing and an eye for detail matter more than prior editing experience.']],
                ['title' => 'Social Media Coordinator for {unit}', 'skills' => ['Social media management', 'Online content creation', 'Online marketing'], 'size' => 'Larger project', 'upskills' => ['Communication', 'Leadership Qualities'], 'hours' => [2, 5], 'freq' => 'week', 'needed' => [1, 3], 'exp' => 'Basic experience', 'online' => 100,
                    'desc' => ['Help {unit} grow its community online. You will plan and schedule posts across LinkedIn, Instagram and X, highlight member achievements and promote upcoming events.', 'We will share our brand guidelines and content calendar. You should be comfortable writing short, engaging copy and tracking simple engagement metrics.']],
                ['title' => 'Video Editor for {event} Highlights', 'skills' => ['A/V recording and editing', 'Online content creation'], 'size' => 'Small project', 'upskills' => $comm, 'hours' => [10, 25], 'freq' => 'overall', 'needed' => [1, 2], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['We recorded hours of talks, panels and interviews at {event} and need help turning them into a two-minute highlights reel and a set of short clips for social media.', 'Familiarity with Premiere Pro, DaVinci Resolve or similar tools is expected. You will receive the raw footage, branding assets and a shot list.']],
                ['title' => 'Graphic Designer — {unit} Brand Refresh', 'skills' => ['Graphic design', 'UX design'], 'size' => 'Small project', 'upskills' => ['Communication', 'Problem Solving'], 'hours' => [15, 30], 'freq' => 'overall', 'needed' => [1, 2], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['{unit} is refreshing its visual identity within the IEEE master brand guidelines. We need a designer to create templates for slides, posters and social media cards.', 'You will present two concepts to the committee, iterate on feedback and hand over editable source files.']],
                ['title' => 'Blog Writer: Stories from {unit} Members', 'skills' => ['Written communication', 'Storytelling', 'Reporting'], 'size' => 'Ongoing', 'upskills' => $comm, 'hours' => [2, 4], 'freq' => 'month', 'needed' => [2, 5], 'exp' => 'Basic experience', 'online' => 100,
                    'desc' => ['Interview members about their careers, projects and volunteering journeys and turn their stories into short blog posts for the {unit} website.', 'Each post is around 600 words. We will introduce you to interviewees and review drafts with you.']],
                ['title' => 'Technical Content Translator ({language})', 'skills' => ['Translation', 'Technical writing'], 'size' => 'Quick task', 'upskills' => ['Communication'], 'hours' => [4, 10], 'freq' => 'overall', 'needed' => [1, 3], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['Help make IEEE resources accessible to more members by translating a set of short guides and event materials into {language}.', 'You should be fluent in {language} and English and comfortable with technical terminology.']],
                ['title' => 'Photographer for {event}', 'skills' => ['Photography', 'A/V recording and editing'], 'size' => 'Full day', 'upskills' => ['Communication', 'Operational Acumen'], 'hours' => [6, 9], 'freq' => 'overall', 'needed' => [1, 2], 'exp' => 'Basic experience', 'online' => 0,
                    'desc' => ['Capture the energy of {event}: keynotes, workshops, networking and the volunteer team behind the scenes.', 'Bring your own camera. You will deliver a curated set of edited photos within a week of the event.']],
            ],
            'Event-based' => [
                ['title' => 'Local Volunteer Crew — {event}', 'skills' => ['Conference/event organizing', 'Volunteer coordination', 'Public speaking'], 'size' => 'Full day', 'upskills' => ['Organization Abilities', 'Operational Acumen'], 'hours' => [6, 10], 'freq' => 'overall', 'needed' => [5, 15], 'exp' => 'Not applicable', 'online' => 0,
                    'desc' => ['{event} is coming to {section} and we need a friendly crew to welcome attendees, guide speakers, staff the registration desk and keep sessions running on time.', 'No prior experience needed — we will run a short briefing before the event. Volunteers receive a certificate and access to the networking reception.']],
                ['title' => 'Session Chair Support at {conference}', 'skills' => ['Conference/event organizing', 'Academic research', 'Public speaking'], 'size' => 'Small project', 'upskills' => ['Leadership Qualities', 'Communication'], 'hours' => [8, 16], 'freq' => 'overall', 'needed' => [3, 8], 'exp' => 'Some experience', 'online' => 30,
                    'desc' => ['Support session chairs at {conference}: check in speakers, keep time, collect slides and help moderate Q&A.', 'Ideal for graduate students and young professionals who want to meet researchers in their field.']],
                ['title' => 'Workshop Facilitator: {topic} Bootcamp', 'skills' => ['Workshop facilitation', 'Teaching and training', 'Machine Learning and AI'], 'size' => 'Small project', 'upskills' => ['Leadership Qualities', 'Communication'], 'hours' => [10, 20], 'freq' => 'overall', 'needed' => [2, 4], 'exp' => 'Some experience', 'online' => 60,
                    'desc' => ['We are running a hands-on {topic} bootcamp for students and early-career members in {section}. Help design exercises, run breakout rooms and answer questions.', 'You should be confident with the fundamentals of {topic} and enjoy explaining concepts to beginners.']],
                ['title' => 'Virtual Event Host — {unit} Webinar Series', 'skills' => ['Public speaking', 'Conference/event organizing', 'Online content creation'], 'size' => 'Ongoing', 'upskills' => $comm, 'hours' => [2, 4], 'freq' => 'month', 'needed' => [1, 2], 'exp' => 'Basic experience', 'online' => 100,
                    'desc' => ['Host the monthly {unit} webinar: introduce speakers, moderate the chat and keep the session engaging.', 'We provide the platform, run-of-show and technical support. A clear speaking voice and reliable internet connection are all you need.']],
                ['title' => 'Hackathon Mentor — {topic} Challenge', 'skills' => ['Mentoring', 'Web and mobile development', 'Object-oriented programming'], 'size' => 'Quick task', 'upskills' => ['Leadership Qualities', 'Problem Solving'], 'hours' => [6, 12], 'freq' => 'overall', 'needed' => [4, 10], 'exp' => 'Some experience', 'online' => 50,
                    'desc' => ['Guide student teams through a 36-hour {topic} hackathon. Mentors drop into team channels, unblock technical problems and give feedback on pitches.', 'Sign up for one or more 3-hour shifts.']],
                ['title' => 'Logistics Coordinator — {event}', 'skills' => ['Project planning and management', 'Conference/event organizing', 'Financial management'], 'size' => 'Larger project', 'upskills' => ['Organization Abilities', 'Operational Acumen'], 'hours' => [4, 8], 'freq' => 'week', 'needed' => [1, 2], 'exp' => 'Some experience', 'online' => 40,
                    'desc' => ['Own venue, catering and travel logistics for {event}. You will work with the organising committee, track the budget and coordinate suppliers.', 'Previous event planning experience is a plus.']],
            ],
            'General' => [
                ['title' => '{unit} Ambassador ({year})', 'skills' => ['Public speaking', 'Online marketing', 'Volunteer coordination'], 'size' => 'Ongoing', 'upskills' => ['Communication', 'Leadership Qualities'], 'hours' => [2, 4], 'freq' => 'month', 'needed' => [5, 20], 'exp' => 'Not applicable', 'online' => 100,
                    'desc' => ['Become an ambassador for {unit} in your section. Ambassadors share opportunities, welcome new members and collect feedback for the leadership team.', 'You will join a global cohort with monthly calls and resources to help you get started.']],
                ['title' => 'Membership Development Volunteer — {section} Section', 'skills' => ['Talent management', 'Data analysis', 'Public Relations'], 'size' => 'Larger project', 'upskills' => ['Operational Acumen', 'Problem Solving'], 'hours' => [2, 5], 'freq' => 'week', 'needed' => [1, 3], 'exp' => 'Basic experience', 'online' => 70,
                    'desc' => ['Help the {section} Section understand why members join, stay and leave. Analyse renewal data, run short surveys and propose actions for the executive committee.', 'Comfort with spreadsheets is helpful; we will share anonymised data.']],
                ['title' => 'Mentor for Student Members', 'skills' => ['Mentoring', 'Talent management'], 'size' => 'Ongoing', 'upskills' => ['Leadership Qualities', 'Communication'], 'hours' => [1, 3], 'freq' => 'month', 'needed' => [5, 15], 'exp' => 'Extensive experience', 'online' => 100,
                    'desc' => ['Share your experience with a student member through monthly one-to-one conversations about careers, research and professional growth.', 'Mentors are matched by field and region. A short orientation is provided.']],
                ['title' => 'Secretary, {unit} Executive Committee', 'skills' => ['Written communication', 'Project planning and management'], 'size' => 'Ongoing', 'upskills' => ['Organization Abilities', 'Operational Acumen'], 'hours' => [3, 5], 'freq' => 'month', 'needed' => [1, 1], 'exp' => 'Some experience', 'online' => 80,
                    'desc' => ['Keep the {unit} executive committee organised: prepare agendas, take minutes, track action items and maintain our shared drive.', 'This is a one-year appointment with the option to continue.']],
                ['title' => 'Awards Committee Member — {unit}', 'skills' => ['Scientific paper review', 'Talent management'], 'size' => 'Small project', 'upskills' => ['Problem Solving'], 'hours' => [8, 15], 'freq' => 'overall', 'needed' => [3, 5], 'exp' => 'Extensive experience', 'online' => 100,
                    'desc' => ['Review nominations for the annual {unit} awards, score them against published criteria and join two calibration calls.', 'Senior members and fellows are especially encouraged to apply.']],
            ],
            'Humanitarian' => [
                ['title' => 'SIGHT Project Volunteer: {humanitarian}', 'skills' => ['Humanitarian technology', 'IoT', 'Community development'], 'size' => 'Larger project', 'upskills' => ['Problem Solving', 'Leadership Qualities'], 'hours' => [3, 6], 'freq' => 'week', 'needed' => [3, 6], 'exp' => 'Some experience', 'online' => 40,
                    'desc' => ['Join a Special Interest Group on Humanitarian Technology (SIGHT) team building a {humanitarian} project with a local partner in {country}.', 'Engineers, designers and community organisers are all welcome — the project needs both technical and people skills.']],
                ['title' => 'STEM Outreach Volunteer — {section} Schools Programme', 'skills' => ['STEM outreach', 'Teaching and training', 'Workshop facilitation'], 'size' => 'Ongoing', 'upskills' => ['Communication', 'Leadership Qualities'], 'hours' => [2, 4], 'freq' => 'month', 'needed' => [5, 12], 'exp' => 'Not applicable', 'online' => 10,
                    'desc' => ['Inspire the next generation of engineers by running hands-on activities in local schools around {section}. Activity kits and lesson plans are provided.', 'Volunteers must complete the IEEE safeguarding module before visiting schools.']],
                ['title' => 'Disaster Response Tech Volunteer ({country})', 'skills' => ['Disaster response', 'Cloud Computing', 'Data analysis'], 'size' => 'Small project', 'upskills' => ['Problem Solving', 'Operational Acumen'], 'hours' => [10, 30], 'freq' => 'overall', 'needed' => [2, 5], 'exp' => 'Some experience', 'online' => 80,
                    'desc' => ['Support a humanitarian partner in {country} with mapping, data dashboards and connectivity planning after recent flooding.', 'Remote volunteers work in short sprints coordinated by the HTB team.']],
                ['title' => 'Digital Literacy Trainer for {community}', 'skills' => ['Teaching and training', 'Community development'], 'size' => 'Small project', 'upskills' => ['Communication'], 'hours' => [8, 16], 'freq' => 'overall', 'needed' => [2, 6], 'exp' => 'Not applicable', 'online' => 30,
                    'desc' => ['Run friendly sessions that help {community} use email, video calls and online safety tools with confidence.', 'Patience and empathy matter most; we provide training materials.']],
            ],
            'Strategy' => [
                ['title' => 'Strategic Planning Committee Member — {unit}', 'skills' => ['Strategy development', 'Data analysis', 'Project planning and management'], 'size' => 'Larger project', 'upskills' => ['Leadership Qualities', 'Operational Acumen'], 'hours' => [3, 5], 'freq' => 'month', 'needed' => [2, 5], 'exp' => 'Extensive experience', 'online' => 100,
                    'desc' => ['Help {unit} set its priorities for the next three years. You will analyse member feedback, benchmark other units and draft recommendations for the board.', 'Expect a monthly committee call plus asynchronous work.']],
                ['title' => 'Partnerships Lead — {unit}', 'skills' => ['External partnership management', 'Negotiation', 'Fundraising'], 'size' => 'Ongoing', 'upskills' => ['Leadership Qualities', 'Communication'], 'hours' => [3, 6], 'freq' => 'month', 'needed' => [1, 1], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['Build relationships with industry and academic partners who can support {unit} programmes with speakers, venues and sponsorship.', 'You will maintain a partner pipeline and report quarterly to the committee.']],
                ['title' => 'Data & Metrics Lead, {unit}', 'skills' => ['Data analysis', 'Data visualization', 'Reporting'], 'size' => 'Larger project', 'upskills' => ['Problem Solving', 'Operational Acumen'], 'hours' => [2, 4], 'freq' => 'week', 'needed' => [1, 2], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['Define and track the metrics that show whether {unit} programmes are working — participation, retention, satisfaction — and build a simple dashboard for volunteers.', 'Experience with spreadsheets or BI tools is helpful.']],
                ['title' => 'Diversity & Inclusion Strategy Volunteer — {unit}', 'skills' => ['Strategy development', 'Community development', 'Survey design'], 'size' => 'Small project', 'upskills' => ['Leadership Qualities', 'Communication'], 'hours' => [10, 20], 'freq' => 'overall', 'needed' => [2, 4], 'exp' => 'Basic experience', 'online' => 100,
                    'desc' => ['Help {unit} make its events and leadership more representative. Review current practice, gather input from members and propose concrete actions.']],
            ],
            'Technical' => [
                ['title' => 'Paper Reviewer — {conference} {year}', 'skills' => ['Scientific paper review', 'Academic research', 'Signal processing'], 'size' => 'Small project', 'upskills' => ['Problem Solving'], 'hours' => [8, 20], 'freq' => 'overall', 'needed' => [5, 20], 'exp' => 'Extensive experience', 'online' => 100,
                    'desc' => ['{conference} {year} is looking for reviewers. Each reviewer receives 3–5 papers matched to their expertise and submits structured reviews through the conference system.', 'A PhD or equivalent research experience is expected.']],
                ['title' => 'Technical Program Committee Member — {conference}', 'skills' => ['Academic research', 'Scientific paper review', 'Conference/event organizing'], 'size' => 'Larger project', 'upskills' => ['Leadership Qualities', 'Problem Solving'], 'hours' => [20, 40], 'freq' => 'overall', 'needed' => [3, 8], 'exp' => 'Extensive experience', 'online' => 100,
                    'desc' => ['Shape the technical programme of {conference}: assign reviewers, make accept/reject recommendations and help build sessions.']],
                ['title' => 'Standards Working Group Contributor: {standard}', 'skills' => ['Standards development', 'Technical writing'], 'size' => 'Ongoing', 'upskills' => ['Problem Solving', 'Communication'], 'hours' => [2, 4], 'freq' => 'month', 'needed' => [3, 10], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['Contribute to the {standard} working group: review draft text, propose comment resolutions and join monthly calls.', 'A great way to influence technology used around the world.']],
                ['title' => 'Open Source Maintainer — {unit} Tools', 'skills' => ['Web and mobile development', 'Object-oriented programming', 'Cloud Computing'], 'size' => 'Ongoing', 'upskills' => ['Problem Solving', 'Leadership Qualities'], 'hours' => [2, 5], 'freq' => 'week', 'needed' => [1, 3], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['{unit} maintains open-source tools used by volunteers to run events and manage membership data. Help triage issues, review pull requests and ship improvements.']],
                ['title' => 'Web Developer for {unit} Website', 'skills' => ['Web and mobile development', 'UX design', 'Database administration'], 'size' => 'Larger project', 'upskills' => ['Problem Solving', 'Communication'], 'hours' => [3, 6], 'freq' => 'week', 'needed' => [2, 4], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['Rebuild the {unit} website so volunteers can update events and news without technical help. You will work with our content team on structure, accessibility and hosting.', 'Experience with a modern web framework or CMS is expected.']],
                ['title' => '{topic} Tutorial Author', 'skills' => ['Technical writing', 'Machine Learning and AI', 'Teaching and training'], 'size' => 'Small project', 'upskills' => ['Communication'], 'hours' => [10, 20], 'freq' => 'overall', 'needed' => [1, 3], 'exp' => 'Some experience', 'online' => 100,
                    'desc' => ['Write a beginner-friendly tutorial series on {topic} for the IEEE Learning Network community, with worked examples and exercises.']],
            ],
        ];
    }

    private function vars(?string $section, ?string $country, Carbon $at): array
    {
        $unit = $this->pick([
            $this->pick(config('volunteering.societies')),
            ($section ?: 'Bangalore').' Section',
            $this->pick(['IEEE Young Professionals', 'IEEE Women in Engineering', 'IEEE Student Activities']),
        ]);

        return [
            '{unit}' => $unit,
            '{section}' => $section ?: 'your local',
            '{country}' => $country ?: 'Kenya',
            '{region_name}' => 'the region',
            '{event}' => $this->pick(self::EVENTS).' '.$at->copy()->addMonths(2)->year,
            '{conference}' => $this->pick(self::CONFERENCES),
            '{year}' => (string) $at->copy()->addMonths(3)->year,
            '{topic}' => $this->pick(self::TOPICS),
            '{standard}' => $this->pick(self::STANDARDS),
            '{humanitarian}' => $this->pick(self::HUMANITARIAN),
            '{language}' => $this->pick(['Spanish', 'Portuguese', 'French', 'Arabic', 'Hindi', 'Indonesian']),
            '{community}' => $this->pick(['Senior Citizens', 'Rural Schools', 'Refugee Communities', 'Women Entrepreneurs']),
        ];
    }

    private function fill(string $text, array $vars): string
    {
        return strtr($text, $vars);
    }

    private function bio(string $first, string $profession, string $section, string $country): string
    {
        $org = $this->pick(self::ORGANISATIONS);
        $interests = $this->pick(['mentoring students', 'organising technical events', 'making engineering more inclusive', 'science communication', 'humanitarian technology', 'standards and policy', 'building local communities']);

        return $this->pick([
            "I'm {$first}, a ".lcfirst($profession)." working at {$org} in {$country}. I've been active in the {$section} Section for a few years and enjoy {$interests}.",
            "{$profession} based in {$country}. Outside work I volunteer with my local IEEE section ({$section}), with a particular interest in {$interests}. Always happy to connect with fellow members.",
            "Hi! I'm {$first}. I work at {$org} and volunteer because IEEE gave me my first network as a student. These days I focus on {$interests} and helping new volunteers get started.",
        ]);
    }

    private function motivation(Opportunity $opportunity, User $user): string
    {
        return $this->pick([
            "I'd love to contribute to this — I have relevant experience and can commit the time requested. It would also help me grow my network in the community.",
            'This matches my skills well and is something I care about. I have volunteered on similar activities before and would be glad to help.',
            "I'm looking to get more involved with IEEE and this opportunity looks like a great fit. I'm reliable, organised and happy to learn.",
            'I have done similar work in my day job and would like to put those skills to use for IEEE members. Available evenings and weekends.',
            'As an active member of my section I would be excited to support this initiative and bring a fresh perspective.',
        ]);
    }

    private function endorsementText(string $first): string
    {
        return $this->pick([
            "{$first} was an outstanding volunteer — proactive, reliable and great with people. They took ownership of their tasks from day one and delivered ahead of schedule.",
            "Working with {$first} was a pleasure. They communicated clearly, kept the team on track and brought creative ideas that genuinely improved the outcome.",
            "{$first} went above and beyond. Their technical knowledge and calm approach made a real difference, and attendees noticed. I would happily work with them again.",
            "Dependable, thoughtful and well-organised. {$first} handled every request professionally and helped onboard newer volunteers too.",
            "{$first} brought energy and expertise to the team. Their contributions were high quality and always on time — highly recommended for future roles.",
        ]);
    }

    private function personName(Generator $f, string $locale): string
    {
        if (str_starts_with($locale, '@')) {
            [$first, $last] = self::NAME_POOLS[substr($locale, 1)];

            return $this->pick($first).' '.$this->pick($last);
        }

        for ($i = 0; $i < 5; $i++) {
            $last = in_array($locale, ['en_US', 'en_CA', 'en_AU', 'en_NZ'], true) ? $this->pick(self::COMMON_SURNAMES) : $f->lastName();
            $name = $f->firstName().' '.$last;
            if (preg_match('/^[\p{Latin}\s\'\-\.]+$/u', $name)) {
                return $name;
            }
        }

        return $this->faker->firstName().' '.$this->pick(self::COMMON_SURNAMES);
    }

    private function cityFor(Generator $f, string $country): ?string
    {
        $city = $f->city();

        return preg_match('/^[\p{Latin}\s\'\-\.]+$/u', $city) ? Str::limit($city, 80, '') : null;
    }

    // =====================================================================
    // Randomness helpers (seeded via mt_srand for reproducible output)
    // =====================================================================

    private function pick(array $items)
    {
        return $items[array_keys($items)[mt_rand(0, count($items) - 1)]];
    }

    private function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    /** @param array<string|int, int> $weights */
    private function weighted(array $weights)
    {
        $total = array_sum($weights);
        $roll = mt_rand(1, $total);
        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
