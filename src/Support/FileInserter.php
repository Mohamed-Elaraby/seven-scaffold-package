<?php

namespace Seven\Scaffold\Support;

use Illuminate\Filesystem\Filesystem;

class FileInserter
{
    public function __construct(private Filesystem $fs) {}

public function ensureFile(string $path, string $defaultContent): void
{
    if (!$this->fs->exists($path)) {
        $dir = dirname($path);
        if (!$this->fs->exists($dir)) {
            $this->fs->makeDirectory($dir, 0755, true);
        }
        $this->fs->put($path, $defaultContent);
    }
}

public function ensureMarkerExists(string $path, string $marker): void
{
    if (!$this->fs->exists($path)) return;

    $content = $this->fs->get($path);
    if (!str_contains($content, $marker)) {
        $content .= "\n{$marker}\n";
        $this->fs->put($path, $content);
    }
}

public function insertAfterMarkerOnce(string $path, string $marker, string $snippet, int $indentSpaces = 0): void
{
    if (!$this->fs->exists($path)) return;

    $content = $this->fs->get($path);
    $snippetIndented = str_repeat(' ', $indentSpaces) . rtrim($snippet) . "\n";

    if (str_contains($content, $snippetIndented) || str_contains($content, rtrim($snippet))) {
        return;
    }

    if (!str_contains($content, $marker)) {
        $content .= "\n{$marker}\n";
    }

    $content = str_replace($marker, $marker . "\n" . $snippetIndented, $content);
    $this->fs->put($path, $content);
}

public function insertSeederCall(string $databaseSeederPath, string $seederClass): void
{
    if (!$this->fs->exists($databaseSeederPath)) return;

    $marker = '// <seven-scaffold-seeders>';
    $line = "        \$this->call({$seederClass}::class);";

    $content = $this->fs->get($databaseSeederPath);

    if (str_contains($content, $line)) return;

    if (str_contains($content, $marker)) {
        $content = str_replace($marker, $marker . "\n" . $line, $content);
        $this->fs->put($databaseSeederPath, $content);
        return;
    }

    // fallback: inject after run() opening brace
    $content = preg_replace(
        '/public function run\(\): void\s*\{\s*/',
        "public function run(): void\n    {\n{$line}\n",
        $content,
        1
    );

    $this->fs->put($databaseSeederPath, $content);
}
}
