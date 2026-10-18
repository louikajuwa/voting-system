<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    use AuthorizesOrganization;

    private const ROLES = ['Admin', 'Manager'];

    public function index(): View
    {
        return view('admin.members');
    }

    /** JSON: org members for the dynamic (no-reload) table. */
    public function list(): JsonResponse
    {
        $me = auth()->id();
        $members = User::where('organization_id', auth()->user()->organization_id)
            ->with('roles')->orderBy('name')->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->getRoleNames()->first() ?? 'Member',
                'is_self' => $u->id === $me,
                'update_url' => route('admin.members.update', $u),
                'delete_url' => route('admin.members.destroy', $u),
            ]);

        return $this->ok('', ['rows' => $members]);
    }

    /** Create a member. Email is out of scope, so we return a one-time temp password in the JSON only. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        $temp = Str::password(12);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($temp),
            'organization_id' => auth()->user()->organization_id,
        ]);
        $user->syncRoles($data['role']);

        AuditLog::record('member.created', $user, [], ['email' => $user->email, 'role' => $data['role']]);

        // The temp password is never flashed to the session; only AJAX callers receive it.
        if (! $request->expectsJson()) {
            return redirect(route('admin.members.index'))->with('status', 'Member added.');
        }

        return $this->ok('Member added.', [
            'temp_password' => $temp,
            'redirect' => route('admin.members.index'),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($user);
        abort_if($user->id === auth()->id(), 403, 'You cannot change your own role.');
        $data = $request->validate(['role' => ['required', Rule::in(self::ROLES)]]);

        $old = $user->getRoleNames()->first();
        abort_if($old === 'Admin' && $data['role'] !== 'Admin' && $this->isLastAdmin($user), 409, 'An organization needs at least one Admin.');
        $user->syncRoles($data['role']);

        AuditLog::record('member.role_changed', $user, ['role' => $old], ['role' => $data['role']]);

        return $this->respond(route('admin.members.index'), 'Role updated.');
    }

    public function destroy(User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($user);
        abort_if($user->id === auth()->id(), 403, 'You cannot remove yourself.');
        abort_if($user->hasRole('Admin') && $this->isLastAdmin($user), 409, 'An organization needs at least one Admin.');

        AuditLog::record('member.deleted', $user, ['email' => $user->email, 'role' => $user->getRoleNames()->first()]);
        $user->delete();

        return $this->respond(route('admin.members.index'), 'Member removed.');
    }

    private function isLastAdmin(User $user): bool
    {
        return User::role('Admin')->where('organization_id', $user->organization_id)
            ->whereKeyNot($user->id)->doesntExist();
    }
}
