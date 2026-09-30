<?php

namespace App\Http\Controllers;

use App\Enums\PermissionModule;
use App\Http\Requests\User\UpdateUserPermissionsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Super Admin screen for a member's per-component View/Create/Edit/Delete
 * access — see User::hasPermission() for how it's stored and enforced.
 */
class UserPermissionController extends Controller
{
    public function edit(User $user): View
    {
        $this->authorize('managePermissions', $user);

        return view('users.permissions', [
            'user' => $user,
            'modules' => PermissionModule::cases(),
        ]);
    }

    public function update(UpdateUserPermissionsRequest $request, User $user): RedirectResponse
    {
        $user->forceFill(['permissions' => $request->permissions()])->save();

        return redirect()->route('users.permissions.edit', $user)->with('success', "Permissions updated for {$user->name}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('managePermissions', $user);

        $user->forceFill(['permissions' => null])->save();

        return redirect()->route('users.permissions.edit', $user)->with('success', "{$user->name} now has full access.");
    }
}
