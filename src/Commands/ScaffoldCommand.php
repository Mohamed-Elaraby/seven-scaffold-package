<?php

namespace Seven\Scaffold\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Seven\Scaffold\Support\FileInserter;
use Seven\Scaffold\Support\Stub;

class ScaffoldCommand extends Command
{
    protected $signature = 'seven:scaffold {name}
    {--area=admin : admin|front}
    {--views= : views folder (default plural of name)}
    {--layout= : blade layout name (e.g. admin.layouts.app)}
    {--prefix=admin : url prefix (admin default)}
    {--name-prefix=admin. : route name prefix}
    {--middleware=auth : group middleware}
    {--seeder : generate seeder + register in DatabaseSeeder}
    {--resource : generate resource controller + resource route}
';

    protected $description = 'Generate model, migration, controller, CRUD views (blueprint), seeder, and routes';


    public function handle(Filesystem $fs): int
    {
        $name = Str::studly($this->argument('name'));           // Branch
        $area = strtolower($this->option('area')) === 'front' ? 'front' : 'admin';

        $viewsFolder = $this->option('views') ?: Str::kebab(Str::pluralStudly($name)); // branches
        $uri = Str::kebab(Str::pluralStudly($name));           // branches

        // 1) Model + Migration
        $this->call('make:model', ['name' => $name, '-m' => true]);

        // 2) Controller namespace
        $ns = $area === 'admin' ? 'Admin' : 'Front';
        $controllerName = "{$ns}/{$name}Controller";

        $this->call('make:controller', [
            'name' => $controllerName,
            '--resource' => (bool) $this->option('resource'),
        ]);

        $defaultLayout = $area === 'admin' ? 'admin.layouts.app' : 'front.layouts.app';
        $layout = $this->option('layout') ?: $defaultLayout;

        // 3) Views (CRUD blueprint)
        Stub::publishCrudViews($fs, $area, $viewsFolder, [
            'layout' => $layout,
            'title' => Str::headline($viewsFolder),
            'view_base' => "{$area}.{$viewsFolder}",
            'index_route' => "{{ route('{$area}.{$uri}.index') }}",
            'create_route' => "{{ route('{$area}.{$uri}.create') }}",
            'store_route' => "{{ route('{$area}.{$uri}.store') }}",
            'show_route' => "{{ route('{$area}.{$uri}.show', \$row->id) }}",
            'edit_route' => "{{ route('{$area}.{$uri}.edit', \$row->id) }}",
            'update_route' => "{{ route('{$area}.{$uri}.update', \$item->id) }}",
            'destroy_route' => "{{ route('{$area}.{$uri}.destroy', \$row->id) }}",
        ]);

        $viewsPath = resource_path("views/{$area}/{$viewsFolder}");
        $count = count(glob($viewsPath.'/*.blade.php'));
        $this->info("Views generated ({$count} files): {$viewsPath}");

        // 4) Seeder + register
        $inserter = new FileInserter($fs);

        if ($this->option('seeder')) {
            $seederClass = "{$name}Seeder";
            $this->call('make:seeder', ['name' => $seederClass]);
            $inserter->insertSeederCall(database_path('seeders/DatabaseSeeder.php'), $seederClass);
        }

        // 5) Routes injection
        $routeFile = base_path($area === 'admin' ? 'routes/admin.php' : 'routes/front.php');

        $inserter->ensureFile(
            $routeFile,
            "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n// <seven-scaffold-routes>\n"
        );

        $inserter->ensureMarkerExists($routeFile, '// <seven-scaffold-routes>');

        $controllerFqn = "App\\Http\\Controllers\\{$ns}\\{$name}Controller";

        if ($this->option('resource')) {
            $inserter->insertAfterMarkerOnce(
                $routeFile,
                '// <seven-scaffold-routes>',
                "Route::resource('{$uri}', \\{$controllerFqn}::class);",
                indentSpaces: 8
            );
        } else {
            $inserter->insertAfterMarkerOnce(
                $routeFile,
                '// <seven-scaffold-routes>',
                "Route::get('{$uri}', [\\{$controllerFqn}::class, 'index'])->name('{$uri}.index');",
                indentSpaces: 8
            );
        }

        $this->info("✅ Seven Scaffold generated: {$name} ({$area})");
        return self::SUCCESS;
    }
}
