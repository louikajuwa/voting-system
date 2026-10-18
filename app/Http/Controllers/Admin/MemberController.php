<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    /** Create a member. Email is out of scope, so we show a one-time temp password. */
    public function store(Request $request): RedirectResponse
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

        return $this->respond(route('admin.members.index'), "Member added. Temporary password: {$temp}", [
            'temp_password' => $temp,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeMember($user);
        $data = $request->validate(['role' => ['required', Rule::in(self::ROLES)]]);
        $user->syncRoles($data['role']);

        return $this->respond(route('admin.members.index'), 'Role updated.');
    }

    public function destroy(User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeMember($user);
        abort_if($user->id === auth()->id(), 403, 'You cannot remove yourself.');
        $user->delete();

        return $this->respond(route('admin.members.index'), 'Member removed.');
    }

    private function authorizeMember(User $user): void
    {
        abort_unless($user->organization_id === auth()->user()->organization_id, 403);
    }
}
