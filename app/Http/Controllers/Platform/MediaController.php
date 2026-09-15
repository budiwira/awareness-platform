<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\TrainingModule;
use App\Services\TenantEntitlement;
use App\Services\UserAccessManager;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function store(Request $request, TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpeg,jpg,png,webp,gif'],
        ]);

        $file = $request->file('file');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        Storage::disk('private')->putFileAs('module-media', $file, $filename);

        $url = route('platform.media.serve', $filename);

        return response()->json(['url' => $url]);
    }

    public function serve(Request $request, string $filename)
    {
        abort_unless(preg_match('/\A[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+\z/', $filename), 404);

        if (! Gate::allows('access-platform-dashboard')) {
            Gate::authorize('access-user-dashboard');
            $user = $request->user();
            abort_unless($user->tenant !== null, 403);

            $modules = TrainingModule::whereHas('assignments', function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('tenant_id', $user->tenant_id);
            })->where(function ($query) use ($user) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $user->tenant_id);
            })->get();

            $access = app(UserAccessManager::class);
            $entitlement = app(TenantEntitlement::class);

            abort_unless($modules->contains(function (TrainingModule $module) use ($user, $access, $entitlement, $filename) {
                return $this->containsMedia($module, $filename)
                && $entitlement->hasModule($user->tenant, $module->id)
                && $access->hasModuleAccess($user, $module);
            }), 403);
        }

        $path = "module-media/{$filename}";

        if (! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    private function containsMedia(TrainingModule $module, string $filename): bool
    {
        $document = new DOMDocument;
        $document->loadHTML($module->content_html ?: '<p></p>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $urls = [route('platform.media.serve', $filename), route('platform.media.serve', $filename, false)];

        foreach ($document->getElementsByTagName('img') as $image) {
            if (in_array($image->getAttribute('src'), $urls, true)) {
                return true;
            }
        }

        return false;
    }

    public function storeGeneric(Request $request)
    {
        Gate::authorize('create', TrainingModule::class);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpeg,jpg,png,webp,gif'],
        ]);

        $file = $request->file('file');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        Storage::disk('private')->putFileAs('module-media', $file, $filename);

        $url = route('platform.media.serve', $filename);

        return response()->json(['url' => $url]);
    }
}
