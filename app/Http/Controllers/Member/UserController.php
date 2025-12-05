<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = User::where(function ($query) use ($request) {
            if ($request->search) {
                $query->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%");
            }
        })->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return view('member.users.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::get();
        return view('member.users.create', compact('permissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|same:password_confirmation|required_with:password_confirmation',
            'password_confirmation' => 'required_with:password'
        ], [
            'name.required' => 'nama wajib diisi',
            'email.required' => 'email wajib diisi',
            'email.email' => 'format email ' . $request->email . ' tidak sesuai',
            'email.unique' => 'Email sudah ada di database, silakan gunakan email yang lain',
            'password.required_with' => 'Password harus diisikan',
            'password_confirmation.required_with' => 'Konfirmasi password harus diisikan'
        ]);

        $email_verified_at = $request->email_verified_at ? Carbon::now() : null;

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'email_verified_at' => $email_verified_at,
            'password' => bcrypt($request->password)
        ];

        $userBaru = User::create($data);
        $userBaru->syncPermissions($request->permissions);
        return redirect()->route('member.users.index')->with('success', 'Data user berhasil ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $permissions = Permission::get();
        $userPermissions = $user->getPermissionNames()->toArray();
        $data = $user;
        return view('member.users.edit', compact('data', 'permissions', 'userPermissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'new_password' => 'nullable|min:6|same:new_password_confirmation|required_with:new_password_confirmation',
            'new_password_confirmation' => 'required_with:new_password'
        ], [
            'name.required' => 'nama wajib diisi',
            'email.required' => 'email wajib diisi',
            'email.email' => 'format email ' . $request->email . ' tidak sesuai',
            'email.unique' => 'Email sudah ada di database, silakan gunakan email yang lain',
            'new_password.required_with' => 'Password harus diisikan',
            'new_password_confirmation.required_with' => 'Konfirmasi password harus diisikan'
        ]);

        $email_verified_at = $user->email_verified_at ? $user->email_verified_at : Carbon::now();

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'email_verified_at' => $email_verified_at,
            'password' => $request->new_password ? bcrypt($request->new_password) : $user->password
        ];

        User::where('id', $user->id)->update($data);

        $user->syncPermissions($request->permissions);

        return redirect()->route('member.users.index')->with('success', 'Data user berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $posts = Post::where('user_id', $user->id)->get();
        foreach ($posts as $post) {
            if (file_exists(public_path(getenv('CUSTOM_THUMBNAIL_LOCATION') . "/" . $post->thumbnail)) && isset($post->thumbnail)) {
                unlink(public_path(getenv('CUSTOM_THUMBNAIL_LOCATION') . "/" . $post->thumbnail));
            }
        }
        User::where('id', $user->id)->delete();
        return redirect()->back()->with('success', 'Data user berhasil dihapus');
    }

    public function toggleBlock(User $user)
    {
        $pesan = '';
        if ($user->blocked_at == null) {
            $data = [
                'blocked_at' => now()
            ];
            $pesan = "User " . $user->name . " telah di-block";
        } else {
            $data = [
                'blocked_at' => null
            ];
            $pesan = "User " . $user->name . " telah di-unblock";
        }
        User::where('id', $user->id)->update($data);
        return redirect()->back()->with('success', $pesan);
    }
}
