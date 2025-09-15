<?php

namespace AporteWeb\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Junges\ACL\Models\Permission;
use App\Models\ConfigGeneral;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Devuelve el estado de autenticación y datos del usuario.
     */
    public function checkAuth(Request $request)
    {
        $user = \auth()->guard('web')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not authenticated',
            ]);
        }

        // Permisos del usuario (organización)
        $userPermissions = $user->getOrgAllPermissions();
        $permissions = [];
        foreach ($userPermissions as $permission) {
            $permissions[$permission->name] = [
                'description' => $permission->description,
                'access' => true,
            ];
        }

        // Config general (ajustar si es necesario)
        $config = ConfigGeneral::select([
            'cotizacion_default_tipo_de_contenedor_id',
        ])->first()?->toArray() ?? [];

        return response()->json([
            'user' => [
                'id'                        => $user->id,
                'name'                      => $user->name,
                'email'                     => $user->email,
                'username'                  => $user->username,
                'original_user'             => \session()->get('original_user'),
                'environment'               => $user->environment,
                'permissions'               => $permissions,
                'organization_id'           => $user->organization?->id,
                'organization_name'         => $user->organization?->name,
                'organization_type_id'      => $user->organization?->type?->id,
                'organization_type_name'    => $user->organization?->type?->name,
                'organization_logo_url'     => $user->organization?->logo_url,
                'organization_favicon_url'  => $user->organization?->favicon_url,
                'default_home'              => $user->organization?->type?->default_home,
            ],
            'status' => 'success',
            'config' => $config,
        ]);
    }

    /**
     * Inicia sesión.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $this->buildCredentials($request->username, $request->password);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return \redirect()->intended('api/check-auth');
        }

        return \response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Recuperación de contraseña (genera token simple y dispara evento futuro).
     */
    public function recoverPassword(Request $request)
    {
        $request->validate(['username' => 'required|string']);

        $user = $this->findUserByUsernameOrEmail($request->username);
        if (!$user) {
            return \response()->json(['error' => 'User not found'], 404);
        }

        // generar una clave temporal de 12 caracteres alfanuméricos
        $tempPassword = Str::random(12);
        $user->password = bcrypt($tempPassword);
        $user->save();

        // Enviar notificación de recuperación (mail o queue)
        $loginUrl = url('/login');
        Mail::to($user->email)
            ->send(new \App\Mail\SendNotification(
                "Your temporary password is: <strong>{$tempPassword}</strong><br>Login here: <a href=\"{$loginUrl}\">{$loginUrl}</a><br>Please log in and change your password immediately.",
                'Password Recovery'
            ));

        return \response()->json(['status' => 'ok', 'message' => 'Recovery process initiated']);
    }

    /**
     * Cierra la sesión actual.
     */
    public function logout(Request $request)
    {
        \auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return \response()->json(['message' => 'Logged out']);
    }

    /* =====================================================
     * Helpers internos
     * ===================================================== */

    protected function buildCredentials(string $usernameField, string $password): array
    {
        if (filter_var($usernameField, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $usernameField, 'password' => $password];
        }
        return ['username' => $usernameField, 'password' => $password];
    }

    protected function findUserByUsernameOrEmail(string $username)
    {
        $userModel = \app(User::class);
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return $userModel->where('email', $username)->first();
        }
        return $userModel->where('username', $username)->first();
    }
}
