<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->string('category', 60)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('headline', 160)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('ieee_member_number', 20)->nullable()->unique();
            $table->string('membership_grade', 16)->nullable()->index();
            $table->unsignedSmallInteger('member_since')->nullable();
            $table->string('region', 8)->nullable()->index();
            $table->string('section', 120)->nullable()->index();
            $table->string('society', 160)->nullable();
            $table->string('country', 80)->nullable()->index();
            $table->string('city', 80)->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('availability', 20)->default('available');
            $table->unsignedSmallInteger('hours_per_month')->nullable();
            $table->text('cv_statement')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('show_email')->default(false);
            $table->timestamps();
        });

        Schema::create('skill_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'skill_id']);
            $table->index('skill_id');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title', 200);
            $table->text('description');
            $table->string('details_url', 500)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            // draft | open | in_progress | on_hold | completed | cancelled
            $table->string('status', 20)->default('draft');

            $table->boolean('is_online')->default(true);
            $table->string('location')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('country', 80)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('region', 8)->nullable()->index();
            $table->string('section', 120)->nullable();
            $table->string('organizational_unit', 160)->nullable();
            $table->string('society', 160)->nullable();

            $table->string('experience_level', 40)->nullable();
            $table->string('project_size', 40)->nullable();
            $table->json('membership_grades')->nullable();
            $table->json('upskills')->nullable();
            $table->string('ideal_traits')->nullable();
            $table->unsignedSmallInteger('hours_estimate')->nullable();
            $table->string('hours_frequency', 20)->nullable(); // overall | week | month
            $table->unsignedSmallInteger('volunteers_needed')->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->string('thumbnail_url', 500)->nullable();
            $table->string('thumbnail_path')->nullable();

            // Provenance: locally created vs. synced from the volunteer.ieee.org API.
            $table->string('source', 20)->default('local')->index();
            $table->string('external_id', 64)->nullable()->unique();
            $table->string('external_creator_id', 32)->nullable()->index();
            $table->string('external_status', 80)->nullable();
            $table->timestamp('external_synced_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cloned_from_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->unsignedInteger('views_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('created_at');
        });

        Schema::create('opportunity_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('co_owner'); // owner | co_owner
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['opportunity_id', 'user_id']);
        });

        Schema::create('opportunity_skill', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['opportunity_id', 'skill_id']);
            $table->index('skill_id');
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // pending | accepted | rejected | withdrawn | completed
            $table->string('status', 20)->default('pending');
            $table->text('motivation')->nullable();
            $table->text('owner_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedTinyInteger('owner_rating')->nullable();     // owner rates the volunteer
            $table->unsignedTinyInteger('volunteer_rating')->nullable(); // volunteer rates the experience
            $table->text('volunteer_feedback')->nullable();
            $table->timestamps();

            $table->unique(['opportunity_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('hour_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->date('worked_on');
            $table->decimal('hours', 6, 2);
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'worked_on']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();           // recipient
            $table->foreignId('endorser_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('endorsement_skill', function (Blueprint $table) {
            $table->foreignId('endorsement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['endorsement_id', 'skill_id']);
        });

        Schema::create('saved_opportunities', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'opportunity_id']);
        });

        // Activity stream: drives personal feeds and the admin audit log.
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60)->index();
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Search analytics: what people look for, and what returns nothing.
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 20)->default('opportunities');
            $table->string('query', 200)->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('created_at')->nullable()->index();
        });

        // History of volunteer.ieee.org API refreshes.
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('ieee');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('running'); // running | success | failed
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('closed_count')->default(0);
            $table->text('error')->nullable();
            $table->json('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'sync_runs', 'search_logs', 'activities', 'saved_opportunities', 'endorsement_skill',
            'endorsements', 'hour_logs', 'applications', 'opportunity_skill', 'opportunity_owners',
            'opportunities', 'categories', 'skill_user', 'profiles', 'skills',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
