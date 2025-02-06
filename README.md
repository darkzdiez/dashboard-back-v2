## Upgrade Version

- Junges\ACL\Http\Models\Permission => Junges\ACL\Models\Permission
- Junges\ACL\Http\Models\Group => Junges\ACL\Models\Group
- Junges\ACL\Traits\UsersTrait => Junges\ACL\Concerns\UsersTrait



sacado: 
/*
if (config('app.debug')){
    $assets_version = hash('md5', rand());
} else {
    $assets_version = '16';
    if ( env('ASSETS_VERSION') ) {
        $assets_version = env('ASSETS_VERSION');
    } else {
        $git_refs_dir = base_path('.git/refs/heads');
        $files = scandir($git_refs_dir, SCANDIR_SORT_ASCENDING);
        // Filtrar los archivos para excluir "." y ".."
        $branch_files = array_filter($files, function ($file) {
            return $file !== '.' && $file !== '..';
        });

        // Obtener el primer archivo (rama)
        $first_branch_file = reset($branch_files);

        if ($first_branch_file) {
            $path_file = $git_refs_dir . '/' . $first_branch_file;
            if (file_exists($path_file)) {
                $assets_version = trim(substr(file_get_contents($path_file), 4));
                // Resto de tu lógica aquí...
            } else {
                // "El archivo de la rama no existe.";
            }
        } else {
            // "No se encontraron archivos de ramas en la carpeta .git/refs/heads.";
        }            
    }
}
*/


/*
// Añade el metodo toRawSql a los query builder
\Illuminate\Database\Query\Builder::macro('toRawSql', function(){
    return array_reduce($this->getBindings(), function($sql, $binding){
        return preg_replace('/\?/', is_numeric($binding) ? $binding : "'".$binding."'" , $sql, 1);
    }, $this->toSql());
});
\Illuminate\Database\Eloquent\Builder::macro('toRawSql', function(){
    return ($this->getQuery()->toRawSql());
});
*/
