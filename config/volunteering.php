<?php

/*
|--------------------------------------------------------------------------
| IEEE Volunteering reference data
|--------------------------------------------------------------------------
| Vocabulary shared by forms, filters, the API importer and the analytics.
| Values mirror what volunteer.ieee.org uses so synced opportunities map
| cleanly onto local ones.
*/

return [

    // Public opportunity search API on volunteer.ieee.org (no auth required).
    'api' => [
        'search_url' => env('IEEE_VOLUNTEER_API_URL', 'https://volunteerapi.ieee.org/opportunity/public/search'),
        'origin' => env('IEEE_VOLUNTEER_API_ORIGIN', 'https://volunteer.ieee.org'),
        'page_size' => (int) env('IEEE_VOLUNTEER_API_PAGE_SIZE', 100),
        'timeout' => (int) env('IEEE_VOLUNTEER_API_TIMEOUT', 30),
        // Run the sync automatically once a day via the scheduler.
        'schedule_daily' => (bool) env('IEEE_VOLUNTEER_SYNC_DAILY', true),
        // Public link to an opportunity on the original platform.
        'public_opportunity_url' => 'https://volunteer.ieee.org/opportunities/:id',
    ],

    'regions' => [
        'R1' => 'Region 1 · Northeastern USA',
        'R2' => 'Region 2 · Eastern USA',
        'R3' => 'Region 3 · Southeastern USA',
        'R4' => 'Region 4 · Central USA',
        'R5' => 'Region 5 · Southwestern USA',
        'R6' => 'Region 6 · Western USA',
        'R7' => 'Region 7 · Canada',
        'R8' => 'Region 8 · Europe, Middle East & Africa',
        'R9' => 'Region 9 · Latin America',
        'R10' => 'Region 10 · Asia & Pacific',
    ],

    // A representative set of sections per region, with the country used for
    // directory filters. Users may also type a section not listed here.
    'sections' => [
        'R1' => ['Boston' => 'United States', 'New Hampshire' => 'United States', 'Providence' => 'United States', 'Connecticut' => 'United States', 'Long Island' => 'United States', 'New York' => 'United States', 'North Jersey' => 'United States', 'Rochester' => 'United States', 'Syracuse' => 'United States', 'Schenectady' => 'United States'],
        'R2' => ['Philadelphia' => 'United States', 'Baltimore' => 'United States', 'Washington' => 'United States', 'Northern Virginia' => 'United States', 'Pittsburgh' => 'United States', 'Princeton/Central Jersey' => 'United States', 'Delaware Bay' => 'United States', 'Richmond' => 'United States'],
        'R3' => ['Atlanta' => 'United States', 'Florida West Coast' => 'United States', 'Miami' => 'United States', 'Orlando' => 'United States', 'Eastern North Carolina' => 'United States', 'Huntsville' => 'United States', 'Nashville' => 'United States', 'Columbia' => 'United States'],
        'R4' => ['Chicago' => 'United States', 'Southeastern Michigan' => 'United States', 'Milwaukee' => 'United States', 'Twin Cities' => 'United States', 'Cincinnati' => 'United States', 'Columbus' => 'United States', 'Cleveland' => 'United States', 'Lafayette' => 'United States'],
        'R5' => ['Dallas' => 'United States', 'Houston' => 'United States', 'Central Texas' => 'United States', 'Denver' => 'United States', 'Oklahoma City' => 'United States', 'St. Louis' => 'United States', 'Kansas City' => 'United States', 'New Orleans' => 'United States'],
        'R6' => ['Santa Clara Valley' => 'United States', 'San Francisco Bay Area' => 'United States', 'Los Angeles Council' => 'United States', 'San Diego' => 'United States', 'Seattle' => 'United States', 'Oregon' => 'United States', 'Phoenix' => 'United States', 'Utah' => 'United States'],
        'R7' => ['Toronto' => 'Canada', 'Montreal' => 'Canada', 'Ottawa' => 'Canada', 'Vancouver' => 'Canada', 'Southern Alberta' => 'Canada', 'Kitchener-Waterloo' => 'Canada'],
        'R8' => ['United Kingdom and Ireland' => 'United Kingdom', 'Germany' => 'Germany', 'France' => 'France', 'Italy' => 'Italy', 'Spain' => 'Spain', 'Romania' => 'Romania', 'Poland' => 'Poland', 'Benelux' => 'Netherlands', 'Switzerland' => 'Switzerland', 'Sweden' => 'Sweden', 'Greece' => 'Greece', 'Portugal' => 'Portugal', 'Turkiye' => 'Turkiye', 'Egypt' => 'Egypt', 'Nigeria' => 'Nigeria', 'Kenya' => 'Kenya', 'South Africa' => 'South Africa', 'Saudi Arabia' => 'Saudi Arabia', 'United Arab Emirates' => 'United Arab Emirates', 'Hungary' => 'Hungary'],
        'R9' => ['South Brazil' => 'Brazil', 'Rio de Janeiro' => 'Brazil', 'Mexico' => 'Mexico', 'Colombia' => 'Colombia', 'Peru' => 'Peru', 'Chile' => 'Chile', 'Argentina' => 'Argentina', 'Ecuador' => 'Ecuador'],
        'R10' => ['Bangalore' => 'India', 'Delhi' => 'India', 'Bombay' => 'India', 'Kerala' => 'India', 'Madras' => 'India', 'Hyderabad' => 'India', 'Kolkata' => 'India', 'Singapore' => 'Singapore', 'Tokyo' => 'Japan', 'Beijing' => 'China', 'Hong Kong' => 'Hong Kong', 'Malaysia' => 'Malaysia', 'Indonesia' => 'Indonesia', 'Philippines' => 'Philippines', 'New South Wales' => 'Australia', 'Victorian' => 'Australia', 'New Zealand Central' => 'New Zealand', 'Bangladesh' => 'Bangladesh', 'Sri Lanka' => 'Sri Lanka', 'Islamabad' => 'Pakistan', 'Seoul' => 'South Korea', 'Taipei' => 'Taiwan', 'Thailand' => 'Thailand'],
    ],

    'membership_grades' => [
        'StM' => 'Student Member',
        'GSM' => 'Graduate Student Member',
        'AM' => 'Associate Member',
        'M' => 'Member',
        'SM' => 'Senior Member',
        'F' => 'Fellow',
        'LM' => 'Life Member',
        'LS' => 'Life Senior Member',
        'LF' => 'Life Fellow',
        'AF' => 'Affiliate',
        'H' => 'Honorary Member',
        'INDV' => 'IEEE Individual',
        'SA MBR' => 'Standards Association Member',
    ],

    'societies' => [
        'IEEE Young Professionals',
        'IEEE Women in Engineering',
        'IEEE Student Activities',
        'IEEE Life Members',
        'IEEE Humanitarian Technologies (HTB / SIGHT)',
        'IEEE Educational Activities',
        'IEEE Standards Association',
        'IEEE Computer Society',
        'IEEE Communications Society',
        'IEEE Power & Energy Society',
        'IEEE Signal Processing Society',
        'IEEE Robotics and Automation Society',
        'IEEE Vehicular Technology Society',
        'IEEE Aerospace and Electronic Systems Society',
        'IEEE Sensors Council',
        'IEEE Systems, Man, and Cybernetics Society',
        'IEEE Technology and Engineering Management Society',
        'IEEE Industry Applications Society',
        'IEEE Engineering in Medicine and Biology Society',
        'IEEE Antennas and Propagation Society',
        'IEEE Photonics Society',
        'IEEE Education Society',
        'IEEE Computational Intelligence Society',
    ],

    'experience_levels' => [
        'Not applicable' => 'No prior experience needed',
        'Basic experience' => 'Some familiarity with the task helps',
        'Some experience' => 'You have done something similar before',
        'Extensive experience' => 'You are an experienced practitioner',
    ],

    // "Duration" in the original UI.
    'project_sizes' => [
        'Quick task' => 'A few hours, one-off',
        'Full day' => 'About one day of effort',
        'Small project' => 'A few weeks',
        'Larger project' => 'A few months',
        'Ongoing' => 'A standing role with no fixed end',
    ],

    // What the volunteer grows by taking part ("Upskills").
    'upskills' => [
        'Communication' => 'Practise written and spoken communication with a global audience.',
        'Problem Solving' => 'Work through open-ended challenges and find practical solutions.',
        'Organization Abilities' => 'Plan, coordinate and keep work on track.',
        'Leadership Qualities' => 'Lead people, make decisions and take ownership.',
        'Operational Acumen' => 'Learn how IEEE runs programmes, budgets and operations.',
        'Others' => 'Other skills specific to this opportunity.',
    ],

    'hours_frequencies' => [
        'overall' => 'in total',
        'week' => 'per week',
        'month' => 'per month',
    ],

    'opportunity_statuses' => [
        'draft' => 'Draft',
        'open' => 'Accepting applicants',
        'in_progress' => 'In progress',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'application_statuses' => [
        'pending' => 'Pending review',
        'accepted' => 'Accepted',
        'rejected' => 'Not selected',
        'withdrawn' => 'Withdrawn',
        'completed' => 'Completed',
    ],

    'availability' => [
        'available' => 'Available for new opportunities',
        'limited' => 'Limited availability',
        'unavailable' => 'Not available right now',
    ],

    // Maximum number of people (owner + co-owners) who can manage one opportunity.
    'max_owners' => 10,

    // Match score weights (sum to 100). See App\Support\MatchScore.
    'match_weights' => [
        'skills' => 60,
        'grade' => 20,
        'location' => 20,
    ],
];
