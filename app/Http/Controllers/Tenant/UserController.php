<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CsvImporter;
use App\Services\TenantEntitlement;
use App\Support\Audit\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        // Explicit scoping di application layer (RLS tetap berjaga di bawah)
        $users = User::where('tenant_id', $request->user()->tenant_id)
            ->where('role', UserRole::User->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        return Inertia::render('Tenant/Users/Index', ['users' => $users]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', User::class);

        // Hard limit check
        if (! app(TenantEntitlement::class)->canAddUser($request->user()->tenant)) {
            abort(403, 'Upgrade package Anda untuk menambah user.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Str::random(32), // password sementara; user pakai flow reset
                'role' => UserRole::User->value,
                'tenant_id' => $request->user()->tenant_id, // SERVER-SIDE, bukan dari request
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Validasi unique di-check lewat scope RLS; constraint global DB
            // tetap otoritas terakhir. Tangkap dan jadikan error validasi yang ramah.
            throw ValidationException::withMessages([
                'email' => 'Email sudah terdaftar di platform.',
            ]);
        }

        Audit::log('user.created', $user, ['role' => UserRole::User->value]);

        return redirect()->route('tenant.users.index')->with('success', 'Learner berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('update', $user);

        $actor = $request->user();

        if ($actor->id === $user->id) {
            abort(403, 'Anda tidak dapat mengubah akun sendiri dari halaman ini.');
        }

        if (! $user->isUser()) {
            abort(403, 'Tenant Admin hanya dapat mengelola akun learner.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => ['required', 'boolean'],
        ]);

        $statusChanged = (bool) $validated['is_active'] !== $user->is_active;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
        ]);
        $user->save();

        if ($statusChanged) {
            Audit::log($validated['is_active'] ? 'user.enabled' : 'user.disabled', $user);
        }

        Audit::log('user.updated', $user);

        return redirect()->route('tenant.users.index')->with('success', 'Data learner berhasil diperbarui.');
    }

    public function import(Request $request)
    {
        Gate::authorize('create', User::class);

        // Hard limit check
        if (! app(TenantEntitlement::class)->canAddUser($request->user()->tenant)) {
            abort(403, 'Upgrade package Anda untuk menambah user.');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'], // Max 2MB
        ]);

        $importer = new CsvImporter;
        $importer->importUsers(
            $validated['file'],
            $request->user()->tenant_id,
            $request->user()->id
        );

        return redirect()->route('tenant.users.index')->with('success', 'Import user berhasil.');
    }
}
