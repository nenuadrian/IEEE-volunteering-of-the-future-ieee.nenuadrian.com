<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide CMS plumbing carried over from the original platform:
 * settings, navigation menus, transactional email templates, the media
 * library and editable static pages (About, FAQ, policies…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location')->default('header'); // header | footer
            $table->string('label');
            $table->string('link_type')->default('url');   // url | route | page
            $table->string('value')->nullable();           // URL, route name or page slug
            $table->foreignId('parent_id')->nullable()
                ->constrained('menu_items')->nullOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('new_tab')->default(false);
            $table->timestamps();

            $table->index(['location', 'display_order']);
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->string('button_label')->nullable();
            $table->text('body')->nullable();          // sanitised HTML rendered from editor_data
            $table->json('editor_data')->nullable();   // EditorJS block payload
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('file_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('disk')->default('public');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->json('editor_data')->nullable();
            $table->string('status', 20)->default('published'); // draft | published
            $table->json('meta')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('media');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('settings');
    }
};
