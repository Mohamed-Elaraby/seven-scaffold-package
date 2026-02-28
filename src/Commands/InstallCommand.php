<?php

namespace Seven\Scaffold\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Seven\Scaffold\Support\FileInserter;

class InstallCommand extends Command
{
    protected $signature = 'seven:scaffold:install {--force : Overwrite existing route files}';
    protected $description = 'Install Seven Scaffold requirements (route files, markers, web.php requires, DatabaseSeeder marker)';

    public function handle(Filesystem $fs): int
    {
        $inserter = new FileInserter($fs);
        $force = (bool) $this->option('force');

        // 1) routes/admin.php
        $adminRoutesPath = base_path('routes/admin.php');
        $adminContent = <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth'])
    ->group(function (){

        // <seven-scaffold-routes>

    });

PHP;

        $this->writeFile($fs, $adminRoutesPath, $adminContent, $force);

        // 2) routes/front.php
        $frontRoutesPath = base_path('routes/front.php');
        $frontContent = <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

Route::middleware([])
    ->group(function (){

        // <seven-scaffold-routes>

    });

PHP;

        $this->writeFile($fs, $frontRoutesPath, $frontContent, $force);

        // 3) ensure web.php requires
        $webPath = base_path('routes/web.php');
        if ($fs->exists($webPath)) {
            $web = $fs->get($webPath);

            $requireAdmin = "require __DIR__.'/admin.php';";
            $requireFront = "require __DIR__.'/front.php';";

            if (!str_contains($web, $requireAdmin)) {
                $web .= "\n{$requireAdmin}\n";
            }
            if (!str_contains($web, $requireFront)) {
                $web .= "{$requireFront}\n";
            }

            $fs->put($webPath, $web);
        }

        // 4) ensure DatabaseSeeder marker exists
        $dbSeederPath = database_path('seeders/DatabaseSeeder.php');
        if ($fs->exists($dbSeederPath)) {
            $marker = '// <seven-scaffold-seeders>';
            $content = $fs->get($dbSeederPath);

            if (!str_contains($content, $marker)) {
                // try inject marker inside run()
                $content = preg_replace(
                        '/public function run\(\): void\s*\{\s*/',
                        "public function run(): void\n    {\n        {$marker}\n",
                        $content,
                        1
                    ) ?? $content;
                $fs->put($dbSeederPath, $content);
            }
        }

        $this->info('✅ Seven Scaffold install completed.');
        $this->line('- routes/admin.php + routes/front.php ready');
        $this->line('- routes/web.php updated to require them');
        $this->line('- DatabaseSeeder marker ensured');

        return self::SUCCESS;
    }

    private function writeFile(Filesystem $fs, string $path, string $content, bool $force): void
    {
        if ($fs->exists($path) && !$force) {
            return;
        }

        $dir = dirname($path);
        if (!$fs->exists($dir)) {
            $fs->makeDirectory($dir, 0755, true);
        }

        $fs->put($path, $content);
    }
}