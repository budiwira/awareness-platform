<?php

namespace App\Http\Controllers\Tenant;

use App\Services\CsvImporter;
use App\Services\TenantEntitlement;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Controller;
use App\Models\User;
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
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        return Inertia::render('Tenant/Users/Index', ['users' => $users]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', User::class);

        // Hard limit check
        if (!app(TenantEntitlement::class)->canAddUser($request->user()->tenant)) {
            abort(403, 'Upgrade package Anda untuk menambah user.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in(['user', 'tenant_admin'])],
        ]);

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Str::random(32), // password sementara; user pakai flow reset
                'role' => $validated['role'],
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

        Audit::log('user.created', $user, ['role' => $validated['role']]);

        return redirect()->route('tenant.users.index');
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('update', $user);

        $actor = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'string', Rule::in(['user', 'tenant_admin'])],
            'is_active' => ['required', 'boolean'],
        ]);

        $oldRole = $user->role->value;
        $roleChanged = $validated['role'] !== $oldRole;
        $statusChanged = (bool) $validated['is_active'] !== $user->is_active;

        // Proteksi lockout: jangan bisa menurunkan role / mematikan akun sendiri
        if ($actor->id === $user->id && ($roleChanged || $statusChanged)) {
            abort(403, 'Anda tidak dapat mengubah role atau status akun sendiri.');
        }

        $user->fill($validated);
        $user->save();

        if ($roleChanged) {
            Audit::log('user.role_changed', $user, ['from' => $oldRole, 'to' => $validated['role']]);
        }

        if ($statusChanged) {
            Audit::log($validated['is_active'] ? 'user.enabled' : 'user.disabled', $user);
        }

        Audit::log('user.updated', $user);

        return redirect()->route('tenant.users.index');
    }
        public function import(Request $request)
    {
        Gate::authorize('create', User::class);

        // Hard limit check
        if (!app(TenantEntitlement::class)->canAddUser($request->user()->tenant)) {
            abort(403, 'Upgrade package Anda untuk menambah user.');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'], // Max 2MB
        ]);

        $importer = new CsvImporter();
        $importer->importUsers(
            $validated['file'],
            $request->user()->tenant_id,
            $request->user()->id
        );

        return redirect()->route('tenant.users.index')->with('success', 'Import user berhasil.');
    }
}
