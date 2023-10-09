<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;


class ProfileController extends Controller {

    public function getProfile() {
        $user = auth()->user();
        if ($user) {
            return [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email
            ];
        }
        return response()->json(['message' => 'Group not found'], 404);
    }

    public function saveProfile(Request $request) {
        $user = auth()->user();
        // validate the request
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' .    $user->id,
            'email'    => 'required|string|email|max:255|unique:users,email,' . $user->id
        ]);
        $user->name     = $request->name;
        $user->username = $request->username;
        $user->email    = $request->email;
        $user->save();

        return [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email
        ];
    }

    public function changePassword(Request $request) {
        $user = auth()->user();
        // validate the request
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password changed'
        ]);
    }

}