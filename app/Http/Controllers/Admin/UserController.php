<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditWriter;
use App\Services\UserAccountMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('role')->orderBy('rt_code')->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.form', ['userRecord' => new User, 'mode' => 'create']);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['userRecord' => $user, 'mode' => 'edit']);
    }

    public function store(Request $request): RedirectResponse
    {
        return UserAccountMutation::run(function () use ($request): RedirectResponse {
            $this->authorizeActor($request);
            $data = $this->validated($request, true);
            $record = User::create([
                'name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'],
                'role' => $data['role'], 'rt_code' => $data['role'] === 'admin_rt' ? trim($data['rt_code']) : null, 'is_active' => true,
            ]);
            AuditWriter::write('user.created', $record, [], ['email' => $record->email, 'role' => $record->role, 'rt_code' => $record->rt_code]);

            return redirect()->route('admin.users.index')->with('success', 'Akun berhasil dibuat. Sampaikan kredensial dengan aman kepada pemilik akun.');
        });
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        return UserAccountMutation::run(function () use ($request, $user): RedirectResponse {
            $this->authorizeActor($request);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $data = $this->validated($request, false);
            if ($user->id === $request->user()->id && (empty($data['is_active']) || $data['role'] !== 'super_admin')) {
                return back()->withInput($request->except(['password', 'password_confirmation']))->withErrors(['role' => 'Anda tidak dapat menonaktifkan atau mengubah peran akun sendiri di halaman ini.']);
            }
            if ($user->role === 'super_admin' && ($data['role'] !== 'super_admin' || empty($data['is_active'])) && User::where('role', 'super_admin')->where('is_active', true)->count() <= 1) {
                return back()->withInput($request->except(['password', 'password_confirmation']))->withErrors(['role' => 'Super Admin aktif terakhir tidak boleh diubah menjadi tidak aktif atau kehilangan perannya.']);
            }
            $before = ['role' => $user->role, 'rt_code' => $user->rt_code, 'is_active' => $user->is_active];
            $values = ['name' => $data['name'], 'email' => strtolower($data['email']), 'role' => $data['role'], 'rt_code' => $data['role'] === 'admin_rt' ? trim($data['rt_code']) : null, 'is_active' => (bool) $data['is_active']];
            if (! empty($data['password'])) {
                $values['password'] = $data['password'];
            }
            $user->update($values);
            AuditWriter::write('user.updated', $user, $before, ['role' => $user->role, 'rt_code' => $user->rt_code, 'is_active' => $user->is_active]);

            return redirect()->route('admin.users.index')->with('success', 'Akun diperbarui.');
        });
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        return UserAccountMutation::run(function () use ($request, $user): RedirectResponse {
            $this->authorizeActor($request);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->id === $request->user()->id) {
                return back()->withErrors(['account' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
            }
            if ($user->role === 'super_admin' && $user->is_active && User::where('role', 'super_admin')->where('is_active', true)->count() <= 1) {
                return back()->withErrors(['account' => 'Super Admin aktif terakhir tidak dapat dinonaktifkan.']);
            }
            $before = ['is_active' => $user->is_active];
            $user->update(['is_active' => false]);
            AuditWriter::write('user.deactivated', $user, $before, ['is_active' => false]);

            return back()->with('success', 'Akun dinonaktifkan tanpa menghapus jejak audit.');
        });
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        return UserAccountMutation::run(function () use ($request, $user): RedirectResponse {
            $this->authorizeActor($request);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->update(['is_active' => true]);
            AuditWriter::write('user.activated', $user, ['is_active' => false], ['is_active' => true]);

            return back()->with('success', 'Akun diaktifkan kembali.');
        });
    }

    private function validated(Request $request, bool $creating): array
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email')), 'UTF-8'),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['bail', 'required', 'email', 'max:190', function (string $attribute, mixed $value, \Closure $fail) use ($creating, $request): void {
                $duplicate = User::query()->whereRaw('LOWER(email) = ?', [(string) $value]);
                if (! $creating) {
                    $duplicate->whereKeyNot($request->route('user')?->id ?? 0);
                }
                if ($duplicate->exists()) {
                    $fail('Email sudah digunakan oleh akun lain.');
                }
            }],
            'role' => ['required', 'in:super_admin,admin_rt'],
            'rt_code' => ['required_if:role,admin_rt', 'nullable', 'string', 'max:20'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:12', 'max:200', 'confirmed', function (string $attribute, mixed $value, \Closure $fail): void {
                if (config('hashing.driver') === 'bcrypt' && strlen((string) $value) > 72) {
                    $fail('Kata sandi terlalu panjang untuk penyimpanan aman (maksimal 72 byte).');
                }
            }],
            'is_active' => [$creating ? 'nullable' : 'required', 'boolean'],
        ]);
    }

    private function authorizeActor(Request $request): void
    {
        $actor = User::query()->find($request->user()?->id);
        abort_unless($actor && $actor->is_active && $actor->isSuperAdmin(), 403);
    }
}
