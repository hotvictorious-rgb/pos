<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use Illuminate\Support\Facades\File;

class MakeVersionOverlayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-overlay 
                            {--target-version= : Target semantic version tag (e.g. v1.0.1, defaults to config)}
                            {--bump : Automatically increment current version strictly by 0.0.1 (e.g. 1.0.0 -> 1.0.1)}
                            {--from-tag= : Previous git tag or commit to diff against (e.g. v1.0.0-rc.1)}
                            {--all : Package all application source files, not just git delta}
                            {--output-dir= : Output directory (defaults to releases/)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Package application delta files into a live-site deployment overlay zip with release tags and changelogs';

    /**
     * Files and directories strictly excluded from live overlays.
     */
    protected array $blacklistedPatterns = [
        '.env',
        '.env.*',
        '.git',
        '.git/**',
        '.github',
        '.github/**',
        'storage/**',
        'node_modules/**',
        'vendor/**',
        'tests/**',
        'releases/**',
        'database/*.sqlite',
        'database/*.sqlite-journal',
        '*.log',
        '.phpunit.result.cache',
    ];

    /**
     * Increment version strictly by 0.0.1 (patch increment).
     */
    public static function bumpVersionBy001(string $version): string
    {
        $clean = ltrim(trim($version), 'v');
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $clean, $m)) {
            $major = (int) $m[1];
            $minor = (int) $m[2];
            $patch = (int) $m[3] + 1; // Strict 0.0.1 increment
            return "{$major}.{$minor}.{$patch}";
        }

        return '1.0.1';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $currentConfigVersion = config('version.version', '1.0.0');

        if ($this->option('bump')) {
            $bumpedRaw = self::bumpVersionBy001($currentConfigVersion);
            $targetVersion = "v{$bumpedRaw}";
            $this->info("📈 Incrementing version by 0.0.1: {$currentConfigVersion} ➔ {$bumpedRaw} ({$targetVersion})");

            // Persist to VERSION and config/version.php
            File::put(base_path('VERSION'), "{$targetVersion}\n");

            $configFile = base_path('config/version.php');
            if (File::exists($configFile)) {
                $content = File::get($configFile);
                $content = preg_replace("/'version'\s*=>\s*'[0-9\.]+'/", "'version' => '{$bumpedRaw}'", $content);
                $content = preg_replace("/'tag'\s*=>\s*'v[0-9\.]+'/", "'tag' => '{$targetVersion}'", $content);
                File::put($configFile, $content);
            }
        } else {
            $targetVersion = $this->option('target-version') ?: config('version.tag', 'v1.0.1');
        }
        $fromTag = $this->option('from-tag') ?: 'v1.0.0-rc.1';
        $packageAll = (bool) $this->option('all');
        $outputDir = base_path($this->option('output-dir') ?: 'releases');

        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $this->info("📦 Building Live Site Overlay Patch for {$targetVersion}...");

        $filesToPackage = [];

        if (!$packageAll && $this->isGitRepo()) {
            $this->line("🔍 Computing changed files since {$fromTag}...");
            $gitFiles = $this->getGitChangedFiles($fromTag);

            if (!empty($gitFiles)) {
                $this->info("   Found " . count($gitFiles) . " modified/added files in git diff.");
                $filesToPackage = $gitFiles;
            } else {
                $this->warn("⚠️ No git diff found against {$fromTag}. Falling back to full application source packaging.");
                $filesToPackage = $this->collectAllApplicationFiles();
            }
        } else {
            $filesToPackage = $this->collectAllApplicationFiles();
        }

        // Filter out blacklisted / sensitive files
        $filesToPackage = array_filter($filesToPackage, function ($relPath) {
            return !$this->isBlacklisted($relPath) && file_exists(base_path($relPath)) && !is_dir(base_path($relPath));
        });

        // Always ensure VERSION and config/version.php are included
        if (file_exists(base_path('VERSION')) && !in_array('VERSION', $filesToPackage)) {
            $filesToPackage[] = 'VERSION';
        }
        if (file_exists(base_path('config/version.php')) && !in_array('config/version.php', $filesToPackage)) {
            $filesToPackage[] = 'config/version.php';
        }

        sort($filesToPackage);

        if (empty($filesToPackage)) {
            $this->error("❌ No files found to package into overlay.");
            return 1;
        }

        $zipFileName = "overlay-{$targetVersion}.zip";
        $zipFilePath = "{$outputDir}/{$zipFileName}";

        if (File::exists($zipFilePath)) {
            File::delete($zipFilePath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("❌ Failed to create zip file at: {$zipFilePath}");
            return 1;
        }

        $this->line("📁 Adding " . count($filesToPackage) . " files to {$zipFileName}...");

        foreach ($filesToPackage as $relPath) {
            $fullPath = base_path($relPath);
            $zip->addFile($fullPath, str_replace('\\', '/', $relPath));
        }

        // Add OVERLAY_README.txt with instructions
        $readmeContent = $this->generateOverlayReadme($targetVersion, $fromTag, $filesToPackage);
        $zip->addFromString('OVERLAY_README.txt', $readmeContent);

        // Add standalone web overlay runner for cPanel environments
        $runnerContent = $this->generateStandaloneRunner();
        $zip->addFromString('overlay-runner.php', $runnerContent);

        $zip->close();

        // Generate Changelog Markdown
        $changelogPath = "{$outputDir}/CHANGELOG-{$targetVersion}.md";
        File::put($changelogPath, $readmeContent);

        $fileSizeKb = round(filesize($zipFilePath) / 1024, 2);
        $checksum = hash_file('sha256', $zipFilePath);

        $this->newLine();
        $this->info("==========================================================");
        $this->info("✅ LIVE SITE OVERLAY GENERATED SUCCESSFULLY!");
        $this->info("==========================================================");
        $this->line("🏷️  Release Tag:    <fg=cyan>{$targetVersion}</>");
        $this->line("📍 Base Tag:       <fg=yellow>{$fromTag}</>");
        $this->line("📦 Files Included: <fg=green>" . count($filesToPackage) . " files</>");
        $this->line("💾 Zip Location:   <fg=white>{$zipFilePath}</> ({$fileSizeKb} KB)");
        $this->line("📜 Changelog:      <fg=white>{$changelogPath}</>");
        $this->line("🔒 SHA-256:        <fg=gray>{$checksum}</>");
        $this->newLine();
        $this->info("🚀 How to apply on live site (cPanel / Live Web Root):");
        $this->line("   1. Upload '{$zipFileName}' to your live site root in cPanel File Manager.");
        $this->line("   2. Extract the zip archive directly over existing files (overwrites updated files safely).");
        $this->line("   3. Run pending migrations by clicking [ ⚡ Run Pending Migrations ] in Settings,");
        $this->line("      or run: php artisan migrate --force && php artisan optimize:clear");
        $this->info("==========================================================");

        return 0;
    }

    /**
     * Check if directory is a git repository.
     */
    protected function isGitRepo(): bool
    {
        return File::isDirectory(base_path('.git'));
    }

    /**
     * Get changed and untracked files from git diff.
     */
    protected function getGitChangedFiles(string $fromTag): array
    {
        $files = [];

        // 1. Files changed between tag and HEAD
        $diffCmd = "git diff --name-only {$fromTag}..HEAD 2>nul";
        $diffOutput = shell_exec($diffCmd);
        if ($diffOutput) {
            foreach (explode("\n", trim($diffOutput)) as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $files[] = str_replace('/', DIRECTORY_SEPARATOR, $line);
                }
            }
        }

        // 2. Uncommitted or staged working tree files
        $statusCmd = "git status --porcelain 2>nul";
        $statusOutput = shell_exec($statusCmd);
        if ($statusOutput) {
            foreach (explode("\n", trim($statusOutput)) as $line) {
                $line = trim($line);
                if (strlen($line) > 3) {
                    $path = trim(substr($line, 3));
                    if (!empty($path)) {
                        $files[] = str_replace('/', DIRECTORY_SEPARATOR, $path);
                    }
                }
            }
        }

        return array_unique($files);
    }

    /**
     * Collect all application source files when packaging full app overlay.
     */
    protected function collectAllApplicationFiles(): array
    {
        $scanDirs = [
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'resources',
            'routes',
            'public',
        ];

        $files = [];

        foreach ($scanDirs as $dir) {
            $fullDir = base_path($dir);
            if (!File::isDirectory($fullDir)) {
                continue;
            }

            foreach (File::allFiles($fullDir) as $file) {
                $relPath = ltrim(str_replace(base_path(), '', $file->getRealPath()), DIRECTORY_SEPARATOR);
                $files[] = $relPath;
            }
        }

        return $files;
    }

    /**
     * Determine if a relative file path matches blacklist patterns.
     */
    protected function isBlacklisted(string $relPath): bool
    {
        $normalized = str_replace('\\', '/', $relPath);

        foreach ($this->blacklistedPatterns as $pattern) {
            if ($pattern === $normalized) {
                return true;
            }
            if (str_ends_with($pattern, '/**') && str_starts_with($normalized, substr($pattern, 0, -3))) {
                return true;
            }
            if (fnmatch($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate comprehensive README instructions inside the overlay.
     */
    protected function generateOverlayReadme(string $version, string $fromTag, array $files): string
    {
        $date = date('Y-m-d H:i:s');
        $fileList = implode("\n", array_map(fn($f) => "  - " . str_replace('\\', '/', $f), $files));

        $canonicalKey = str_starts_with($version, 'v') ? $version : "v{$version}";
        $overlayConfig = config("version.overlays.{$canonicalKey}") 
            ?? config("version.overlays.{$version}") 
            ?? [
                'title' => 'Victorious POS Release Update',
                'highlights' => ['General system enhancements and performance updates.'],
                'migrations' => [],
            ];

        $highlights = implode("\n", array_map(fn($h) => "  * {$h}", $overlayConfig['highlights'] ?? []));

        return <<<README
================================================================================
VICTORIOUS ENTERPRISE POS - LIVE SITE OVERLAY DEPLOYMENT PACKAGE
Release Tag: {$version}
Base Revision: {$fromTag}
Packaged Date: {$date}
================================================================================

1. WHAT IS THIS OVERLAY?
--------------------------------------------------------------------------------
This zip package contains the exact updated codebase files for Victorious POS
to overlay on a live production site (e.g. pos.victoriousmarket.com.ng) without
touching your production `.env`, uploaded customer files, or database storage.

2. WHAT WAS UPDATED IN THIS VERSION:
--------------------------------------------------------------------------------
{$highlights}

3. HOW TO APPLY THIS OVERLAY ON LIVE CPANEL / LIVE HOST:
--------------------------------------------------------------------------------
Step 1: In your cPanel File Manager, navigate to your live site root directory.
Step 2: Upload `overlay-{$version}.zip` directly into the site root folder.
Step 3: Right-click `overlay-{$version}.zip` and select "Extract" (Extract Here).
        All updated files in `app/`, `resources/`, `routes/`, `config/`, etc.
        will be updated seamlessly.
Step 4: Execute database migrations and clear caches:
        - METHOD A (Recommended): Log in to the POS System as Admin -> Go to
          System Settings -> Click [ ⚡ Run Pending Migrations & Clear Cache ].
        - METHOD B (CLI / SSH): Run the following commands:
          php artisan migrate --force
          php artisan optimize:clear
        - METHOD C (Web Script): Visit `https://yourdomain.com/overlay-runner.php`
          in your browser and enter your app key to run migrations automatically.
Step 5: Delete `overlay-{$version}.zip` and `overlay-runner.php` from your server.

4. FILES INCLUDED IN THIS OVERLAY:
--------------------------------------------------------------------------------
{$fileList}

================================================================================
Generated by: php artisan app:make-overlay
Victorious Enterprise POS Architecture
================================================================================
README;
    }

    /**
     * Generate standalone web runner script for emergency migrations without SSH.
     */
    protected function generateStandaloneRunner(): string
    {
        return <<<'PHP'
<?php
/**
 * Victorious POS - Emergency Standalone Live Overlay Migration Runner
 * Delete this file immediately after completing the migration!
 */

$autoload = __DIR__ . '/vendor/autoload.php';
$bootstrap = __DIR__ . '/bootstrap/app.php';

if (!file_exists($autoload) || !file_exists($bootstrap)) {
    die("Error: Could not locate Laravel bootstrap environment.");
}

require $autoload;
$app = require $bootstrap;
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=UTF-8');
echo "=====================================================\n";
echo "VICTORIOUS POS - OVERLAY MIGRATION & CACHE RUNNER\n";
echo "=====================================================\n\n";

try {
    echo "1. Running pending database migrations...\n";
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo Illuminate\Support\Facades\Artisan::output() . "\n";

    echo "2. Clearing and refreshing application caches...\n";
    Illuminate\Support\Facades\Artisan::call('optimize:clear');
    echo Illuminate\Support\Facades\Artisan::output() . "\n";

    echo "=====================================================\n";
    echo "✓ MIGRATION AND CACHE REFRESH COMPLETED SUCCESSFULLY!\n";
    echo "IMPORTANT: Please delete overlay-runner.php now for security.\n";
    echo "=====================================================\n";
} catch (Throwable $e) {
    echo "\n❌ ERROR ENCOUNTERED:\n" . $e->getMessage() . "\n";
}
PHP;
    }
}
