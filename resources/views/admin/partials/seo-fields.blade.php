{{--
    Collapsible per-page SEO overrides. Include with the post/page model:
      @include('admin.partials.seo-fields', ['model' => $post])
    Values are stored in meta['seo'] (see HandlesSeoMeta). Each field falls back
    to a sensible default (handled in partials/seo.blade.php + Post accessors).
--}}
<div class="card p-6" x-data="{ open: {{ $model->meta('seo') ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left">
        <span class="label mb-0">SEO &amp; social</span>
        <span class="text-warm-gray transition" :class="open && 'rotate-180'">▾</span>
    </button>

    <div x-show="open" x-cloak class="mt-4 space-y-4">
        <div>
            <label class="label" for="seo_title">Meta title</label>
            <input id="seo_title" name="seo_title" maxlength="70"
                   value="{{ old('seo_title', $model->meta('seo.title')) }}" class="input"
                   placeholder="Defaults to the page title">
            <p class="mt-1 text-xs text-warm-gray">Shown in the browser tab and search results.</p>
            <x-input-error :messages="$errors->get('seo_title')" class="mt-1" />
        </div>

        <div>
            <label class="label" for="seo_description">Meta description</label>
            <textarea id="seo_description" name="seo_description" rows="3" maxlength="200" class="input"
                      placeholder="Defaults to the excerpt, then the site tagline">{{ old('seo_description', $model->meta('seo.description')) }}</textarea>
            <p class="mt-1 text-xs text-warm-gray">Aim for ~150-160 characters.</p>
            <x-input-error :messages="$errors->get('seo_description')" class="mt-1" />
        </div>

        <div>
            <label class="label" for="seo_image">Social share image URL</label>
            <input id="seo_image" name="seo_image" value="{{ old('seo_image', $model->meta('seo.image')) }}" class="input"
                   placeholder="https://… (~1200×630)">
            <p class="mt-1 text-xs text-warm-gray">Used for Open Graph / Twitter cards. Defaults to the featured or site image.</p>
            <x-input-error :messages="$errors->get('seo_image')" class="mt-1" />
        </div>

        <div>
            <label class="label" for="seo_canonical">Canonical URL</label>
            <input id="seo_canonical" name="seo_canonical" value="{{ old('seo_canonical', $model->meta('seo.canonical')) }}" class="input"
                   placeholder="https://… (optional)">
            <p class="mt-1 text-xs text-warm-gray">Set only if this content also lives at another URL.</p>
            <x-input-error :messages="$errors->get('seo_canonical')" class="mt-1" />
        </div>

        <label class="flex items-start gap-2">
            <input type="checkbox" name="seo_noindex" value="1"
                   @checked(old('seo_noindex', $model->meta('seo.noindex')))
                   class="mt-0.5 rounded border-light-gray text-brand focus:ring-brand">
            <span class="text-sm text-warmer-gray">
                Hide this page from search engines
                <span class="mt-0.5 block text-xs text-warm-gray">Adds <code>noindex</code> for this page only.</span>
            </span>
        </label>
    </div>
</div>
