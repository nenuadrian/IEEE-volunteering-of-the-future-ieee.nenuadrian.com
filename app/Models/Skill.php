<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'category', 'description'];

    protected static function booted(): void
    {
        static::saving(function (Skill $skill) {
            $skill->name = trim(preg_replace('/\s+/', ' ', $skill->name));
            $skill->slug = Str::slug($skill->name);
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class);
    }

    /** Find a skill by (case-insensitive) name, creating it when missing. */
    public static function findOrCreateByName(string $name, ?string $category = null): self
    {
        $clean = trim(preg_replace('/\s+/', ' ', $name));

        return static::query()->where('slug', Str::slug($clean))->first()
            ?? static::create(['name' => $clean, 'category' => $category]);
    }

    /**
     * Merge this skill into $target: move every user, opportunity and
     * endorsement link across (skipping duplicates) and delete this one.
     */
    public function mergeInto(Skill $target): void
    {
        if ($target->id === $this->id) {
            return;
        }

        DB::transaction(function () use ($target) {
            foreach (['skill_user' => 'user_id', 'opportunity_skill' => 'opportunity_id', 'endorsement_skill' => 'endorsement_id'] as $table => $owner) {
                $existing = DB::table($table)->where('skill_id', $target->id)->pluck($owner)->all();
                DB::table($table)->where('skill_id', $this->id)->whereIn($owner, $existing)->delete();
                DB::table($table)->where('skill_id', $this->id)->update(['skill_id' => $target->id]);
            }
            $this->delete();
        });
    }
}
