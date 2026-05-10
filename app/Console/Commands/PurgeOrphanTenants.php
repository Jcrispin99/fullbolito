<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PurgeOrphanTenants extends Command
{
    protected $signature = 'tenants:purge-orphans
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Delete orphan tenant storage folders and SQLite database files (left over from tests or migrate:fresh)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $tenantModel */
        $tenantModel = config('tenancy.tenant_model');
        /** @var list<string> $liveIds */
        $liveIds = $tenantModel::query()->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        /** @var array<string,int> $liveSet */
        $liveSet = array_flip($liveIds);

        $this->line(sprintf('Live tenants in central DB: %d', count($liveIds)));
        if ($dry) {
            $this->warn('DRY RUN — nothing will be deleted.');
        }

        $storageOrphans = $this->findStorageOrphans($liveSet);
        $dbOrphans = $this->findDatabaseOrphans($liveSet);

        $this->newLine();
        $this->line(sprintf('Storage orphan folders: %d', count($storageOrphans)));
        $this->line(sprintf('Database orphan files:  %d', count($dbOrphans)));

        if ($storageOrphans === [] && $dbOrphans === []) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        if ($this->getOutput()->isVerbose()) {
            foreach ($storageOrphans as $path) {
                $this->line('  storage: '.$path);
            }
            foreach ($dbOrphans as $path) {
                $this->line('  db:      '.$path);
            }
        } else {
            $this->line('(run with -v to see the full list)');
        }

        if ($dry) {
            return self::SUCCESS;
        }

        if (! $this->confirm('Delete these files/folders?', false)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        foreach ($storageOrphans as $path) {
            File::deleteDirectory($path);
        }
        foreach ($dbOrphans as $path) {
            File::delete($path);
        }

        $this->info(sprintf('Purged %d storage folders and %d database files.', count($storageOrphans), count($dbOrphans)));

        return self::SUCCESS;
    }

    /**
     * @param  array<string,int>  $liveSet  tenant_id => index
     * @return list<string>
     */
    private function findStorageOrphans(array $liveSet): array
    {
        $reserved = ['app', 'framework', 'logs', 'debugbar', 'pail', 'tenant'];
        $storage = storage_path();
        $orphans = [];

        /** @var list<string> $dirs */
        $dirs = File::directories($storage);
        foreach ($dirs as $dir) {
            $name = basename($dir);
            if (in_array($name, $reserved, true)) {
                continue;
            }
            if (isset($liveSet[$name])) {
                continue;
            }
            $orphans[] = $dir;
        }

        return $orphans;
    }

    /**
     * @param  array<string,int>  $liveSet
     * @return list<string>
     */
    private function findDatabaseOrphans(array $liveSet): array
    {
        $prefixRaw = config('tenancy.database.prefix');
        $suffixRaw = config('tenancy.database.suffix');
        $prefix = is_string($prefixRaw) ? $prefixRaw : '';
        $suffix = is_string($suffixRaw) ? $suffixRaw : '';
        $dir = database_path();
        $orphans = [];

        if ($prefix === '' && $suffix === '') {
            return $orphans;
        }

        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();
            if ($prefix !== '' && ! str_starts_with($name, $prefix)) {
                continue;
            }
            if ($suffix !== '' && ! str_ends_with($name, $suffix)) {
                continue;
            }
            $tenantId = substr($name, strlen($prefix), strlen($name) - strlen($prefix) - strlen($suffix));
            if ($tenantId === '' || isset($liveSet[$tenantId])) {
                continue;
            }
            $orphans[] = $file->getPathname();
        }

        return array_values($orphans);
    }
}
