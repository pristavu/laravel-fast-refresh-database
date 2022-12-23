<?php

namespace Plannrc\LaravelFastRefreshDatabase\Traits;

use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Plannrc\LaravelFastRefreshDatabase\Data\FastRefreshDatabaseState;

trait FastRefreshDatabase
{
    use RefreshDatabase;

    /**
     * Refresh a conventional test database.
     *
     * @return void
     * @throws \JsonException
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $cachedChecksum = FastRefreshDatabaseState::$cachedChecksum ??= $this->getCachedMigrationChecksum();
            $currentChecksum = FastRefreshDatabaseState::$currentChecksum ??= $this->calculateMigrationChecksum();

            if ($cachedChecksum !== $currentChecksum) {
                $this->artisan('migrate:fresh', $this->migrateFreshUsing());

                $this->app[Kernel::class]->setArtisan(null);

                $this->storeMigrationChecksum($currentChecksum);
            }

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Calculate a checksum based on the migrations name and last modified date
     *
     * @return string
     * @throws \JsonException
     */
    protected function calculateMigrationChecksum(): string
    {
        $finder = Finder::create()
            ->in(database_path('migrations'))
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->files();

        $migrations = array_map(static function (SplFileInfo $fileInfo) {
            return [$fileInfo->getMTime(), $fileInfo->getPath()];
        }, iterator_to_array($finder));

        // Reset the array keys so there is less data

        $migrations = array_values($migrations);

        // Add the current git branch

        $checkBranch = new Process(['git', 'branch', '--show-current']);
        $checkBranch->run();

        $migrations['gitBranch'] = trim($checkBranch->getOutput());

        // Create a hash

        return hash('sha256', json_encode($migrations, JSON_THROW_ON_ERROR));
    }

    /**
     * Get the cached migration checksum
     *
     * @return string|null
     */
    protected function getCachedMigrationChecksum(): ?string
    {
        return rescue(static fn () => file_get_contents(base_path('migrationChecksum.txt')), null, false);
    }

    /**
     * Store the migration checksum
     *
     * @param string $checksum
     * @return void
     */
    protected function storeMigrationChecksum(string $checksum): void
    {
        file_put_contents(base_path('migrationChecksum.txt'), $checksum);
    }
}