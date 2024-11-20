<?php
namespace AporteWeb\Dashboard\Libs;

use App\Models\User;
use Junges\ACL\Models\Group;
use Illuminate\Support\Str;

trait WatchdogTrait {

    public function getInstance()
    {
        return $this;
    }

    /**
     * Check if the user has a permission using wildcard
     *
     * @param <int|string> $permission
     * @param string $guardName
     */

    public function canWildcard($permission, string $guardName = 'web'): bool
    {
        if ( is_int($permission) ) {
            return $this->getInstance()->hasPermission($permission);
        }
        if ( is_string($permission) ) {
            $permissions = $this->getInstance()->getAllPermissions($guardName);
            foreach ($permissions as $p) {
                $permission = str_replace('*', '.*', $permission);
                if (preg_match('/^' . $permission . '$/', $p->name)) {
                    return true;
                }
            }
            return false;
        }
        throw new \Exception('Permission must be an integer or a string');
    }

    /**
     * Check if the user has any of the given permissions using wildcard
     *
     * @param array<int, string> $permissions
     * @param string $guardName
     */

    public function canAnyWildcard(array $permissions, string $guardName = 'web'): bool
    {
        foreach ($permissions as $permission) {
            if ($this->getInstance()->canWildcard($permission, $guardName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Abort if the user has any of the given permissions using wildcard
     *
     * @param <int|string|array<int, string>> $permissions
     * @param string $guardName
     */

    public function sniff($permissions, string $guardName = 'web' ): void
    {
        /**
         * invoke examples:
         * 
         * Via model trait:
         * auth()->user()->sniff('user-*');
         * 
         * Via facade:
         * \App\Libs\WatchdogFacade::sniff('user-*');
         * 
         * Via helper:
         * sniff('user-*');
         * 
         * Via class instance:
         * (new \App\Libs\Watchdog)->sniff('user-delete');
         * 
         */
        
        $debug = debug_backtrace();
        
        $levelBacktrace = 0;
        // endsWith in Watchdog
        if ( Str::endsWith($debug[0]['file'], 'Facade.php') || Str::endsWith($debug[0]['file'], 'Helpers.php') ) {
            $levelBacktrace = 1;
        }

        $debug = debug_backtrace()[$levelBacktrace];

        $file = explode('\\', $debug['file']);
        $file = end($file);

        // $class = explode('\\', $debug['class']);
        // $class = end($class);
        // $method = $debug['function'];
        $line = $debug['line'];

        $trace = "$file at line $line";
        if ( is_array($permissions) ) {
            if ( ! $this->getInstance()->canAnyWildcard($permissions, $guardName) ) {
                // detect source file
                // desloguear
                auth()->logout();
                abort(401, 'Unauthorized action. ' . $trace);
            }
        } else {
            if ( ! $this->getInstance()->canWildcard($permissions, $guardName) ) {
                // dd(debug_backtrace());
                auth()->logout();
                abort(401, 'Unauthorized action. ' . $trace);
            }
        }
    }

}