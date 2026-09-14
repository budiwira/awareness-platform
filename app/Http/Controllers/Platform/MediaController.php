<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\TrainingModule;
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

    public function serve(string $filename)
    {
        Gate::authorize('viewAny', TrainingModule::class);

        $path = "module-media/{$filename}";

        if (! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->response($path);
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
