<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(protected UserService $userService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        return view('users.index', [
            'users' => $this->userService->list($request->only(['search', 'role', 'status'])),
            'filters' => $request->only(['search', 'role', 'status']),
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'teams' => Team::orderBy('name')->get(),
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('team');

        return view('users.show', ['user' => $user]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'teams' => Team::orderBy('name')->get(),
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated());

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function suspend(User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);

        $this->userService->suspend($user);

        return back()->with('success', "{$user->name} has been suspended.");
    }

    public function activate(User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);

        $this->userService->activate($user);

        return back()->with('success', "{$user->name} has been activated.");
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $newPassword = $this->userService->resetPassword($user);

        return back()->with('success', "Password reset for {$user->name}. New temporary password: {$newPassword}");
    }
}
