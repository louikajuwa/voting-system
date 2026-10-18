<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function edit(): View
    {
        return view('admin.organization', ['org' => auth()->user()->organization]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $org = auth()->user()->organization;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:64'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);

        if ($request->hasFile('logo')) {
            if ($org->logo) {
                Storage::disk('public')->delete($org->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $before = $org->only(['name', 'timezone', 'logo']);
        $org->update($data);
        AuditLog::record('organization.updated', $org, $before, $org->only(['name', 'timezone', 'logo']));

        return $this->respond(route('admin.org.edit'), 'Organization updated.', [
            'name' => $org->name,
            'logo' => $org->logo ? Storage::disk('public')->url($org->logo) : null,
        ]);
    }
}
