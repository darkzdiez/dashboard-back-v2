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
        try {
            $config = ConfigGeneral::select([
                'cotizacion_default_tipo_de_contenedor_id',
            ])->first()?->toArray() ?? [];
        } catch (\Throwable $th) {
            $config = [];
        }

        return response()->json([
            'user' => [
                'id'                        => $user->id,
                'name'                      => $user->name,
                'email'                     => $user->email,
                'username'                  => $user->username,
                'must_change_password'      => $user->must_change_password,
                'locale'                    => app()->getLocale(),
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
            $authenticatedUser = Auth::user();

            // si env('USE_SOCIAL_LOGIN') es true
            // validar si el env('APP_ENV') si es dev o local y la columna access_testing es true
            // validar si el env('APP_ENV') si es prod y la columna access_production es true
            if (
                env('USE_SOCIAL_LOGIN') &&
                (
                    (in_array(env('APP_ENV'), ['local', 'dev']) && !$authenticatedUser->access_testing) ||
                    (env('APP_ENV') === 'prod' && !$authenticatedUser->access_production)
                )
            ) {
                Auth::logout();
                $request->session()->invalidate();
                
                return \response()->json([
                    'status' => 'error',
                    'message' => 'El usuario no tiene permiso para acceder a este entorno'
                ], 500);
            }

            // Verificar si el usuario tiene habilitado el login con contraseña
            if (!$authenticatedUser->login_with_password) {
                Auth::logout();
                $request->session()->invalidate();
                
                return \response()->json([
                    'status' => 'error',
                    'message' => 'El login con contraseña está deshabilitado para este usuario'
                ], 500);
            }

            $request->session()->regenerate();

            if ($authenticatedUser) {
                $description = "El usuario {$authenticatedUser->name} inició sesión";

                activity()
                    ->causedBy($authenticatedUser)
                    ->onModel($authenticatedUser)
                    ->withRef('uuid', $authenticatedUser->uuid ?? null)
                    ->forEvent('login')
                    ->withProperties([
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'guard' => 'web',
                    ])
                    ->log($description);
            }

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
            return \response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        // generar una clave temporal de 12 caracteres alfanuméricos
        $tempPassword = Str::random(12);
        $user->password = bcrypt($tempPassword);
        $user->must_change_password = true;
        $user->save();

        // Enviar notificación de recuperación (mail o queue)
        $loginUrl = url('/login');
        $appName = config('app.name', 'Plataforma');

        $emailBody = '
            <h2 style="color: #333; margin-top: 0;">Recuperación de contraseña</h2>
            <p>Hola <strong>' . e($user->name) . '</strong>,</p>
            <p>Recibimos una solicitud para restablecer tu contraseña. Se generó una contraseña temporal para que puedas acceder a tu cuenta:</p>
            <div style="background-color: #f4f4f4; border-left: 4px solid #3490dc; padding: 15px; margin: 20px 0; font-size: 16px;">
                <strong>Contraseña temporal:</strong> <code style="font-size: 18px; letter-spacing: 1px;">' . $tempPassword . '</code>
            </div>
            <p>Por favor, iniciá sesión y cambiá tu contraseña inmediatamente:</p>
            <div style="text-align: center; margin: 25px 0;">
                <a href="' . $loginUrl . '" style="background-color: #3490dc; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: 600; display: inline-block;">Iniciar sesión</a>
            </div>
            <p style="color: #999; font-size: 12px;">Si no solicitaste este cambio, contactá al administrador de ' . e($appName) . '.</p>
        ';

        Mail::to($user->email)
            ->send(new \App\Mail\SendNotification($emailBody, 'Recuperación de contraseña - ' . $appName));

        $recoverDescription = "Se generó una contraseña temporal para {$user->name}";

        activity()
            ->onModel($user)
            ->withRef('uuid', $user->uuid ?? null)
            ->forEvent('password-recovery')
            ->withProperties([
                'username' => $request->username,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log($recoverDescription);

        return \response()->json(['status' => 'ok', 'message' => 'Se envió un correo con las instrucciones para recuperar tu contraseña']);
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
