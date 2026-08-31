<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserDeletedByAdmin;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = User::with('tenant')
            ->withTrashed();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'tenant_name' => $u->tenant?->name ?? '-',
                'tenant_id' => $u->tenant_id,
                'is_active' => $u->is_active,
                'deleted_at' => $u->deleted_at?->format('d M Y'),
            ]);

        return Inertia::render('Platform/Users/Index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $userToDelete = User::withTrashed()->findOrFail($validated['user_id']);

        // Guard: super admin tidak bisa soft delete diri sendiri
        if ($userToDelete->id === $request->user()->id) {
            return redirect()->back()->withErrors([
                'user_id' => 'Anda tidak bisa menghapus akun Anda sendiri.',
            ]);
        }

        // Soft delete
        $userToDelete->delete();

        // Notifikasi tenant_admin tenant tersebut (kecuali yang dihapus sendiri)
        if ($userToDelete->tenant_id) {
            $tenantAdmins = User::where('tenant_id', $userToDelete->tenant_id)
                ->where('role', 'tenant_admin')
                ->where('id', '!=', $userToDelete->id)
                ->whereNull('deleted_at')
                ->get();

            foreach ($tenantAdmins as $admin) {
                $admin->notify(new UserDeletedByAdmin($userToDelete));
            }
        }

        // Audit log
        Audit::log('user.soft_deleted_by_admin', $userToDelete, [
            'deleted_user_id' => $userToDelete->id,
            'deleted_user_email' => $userToDelete->email,
            'tenant_id' => $userToDelete->tenant_id,
        ]);

        return redirect()->route('platform.users.index')->with('success', 'User berhasil dihapus.');
    }
}
