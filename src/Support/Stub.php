<?php

namespace Seven\Scaffold\Support;

use Illuminate\Filesystem\Filesystem;

class Stub
{
    public static function publishCrudViews(Filesystem $fs, string $area, string $viewsFolder, array $vars): void
    {
        $files = ['index', 'create', 'edit', 'show', '_form'];

        $targetDir = resource_path("views/{$area}/{$viewsFolder}");
        if (!$fs->exists($targetDir)) {
            $fs->makeDirectory($targetDir, 0755, true);
        }

        foreach ($files as $file) {
            $stubPath = __DIR__ . "/../../stubs/views/{$area}/resource/{$file}.blade.stub";
            if (!$fs->exists($stubPath)) continue;

            $content = $fs->get($stubPath);

            foreach ($vars as $key => $value) {
                $content = str_replace('{{ '.$key.' }}', $value, $content);
            }

            $targetFile = "{$targetDir}/{$file}.blade.php";
            if (!$fs->exists($targetFile)) {
                $fs->put($targetFile, $content);
            }
        }
    }
}
