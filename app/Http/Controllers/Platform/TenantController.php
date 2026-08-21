<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount('users')->orderBy('name')->get();

        return Inertia::render('Platform/Tenants/Index', ['tenants' => $tenants]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        // Slug unik otomatis — server-side, tidak bisa diinjeksi client
        $slug = Str::slug($validated['name']);
        $base = $slug;
        $n = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => 'active',
        ]);

        Audit::log('tenant.created', $tenant, ['slug' => $slug]);

        return redirect()->route('platform.tenants.index');
    }
}