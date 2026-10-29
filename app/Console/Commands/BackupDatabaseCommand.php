<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-database
                            {--destination= : Custom destination directory for backup file}
                            {--encrypt : Encrypt backup file using AES-256-CBC}
                            {--encryption-key= : Custom encryption key (defaults to APP_KEY)}
                            {--disk= : Storage disk to push backup archive to (e.g. s3)}
                            {--retention-days=14 : Number of days to retain backups}
                            {--skip-storage : Skip archiving tenant public storage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform automated, compressed and optionally encrypted database backup and storage snapshot';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('AffiliateOS Production Backup & Disaster Recovery');
        $this->info('====================================================');

        $timestamp = now()->format('Ymd_His');
        $connectionName = config('database.default', 'mysql');
        /** @var array<string, mixed> $dbConfig */
        $dbConfig = config("database.connections.{$connectionName}", []);

        $destinationDir = (string) ($this->option('destination') ?: storage_path('app/backups'));
        if (! File::isDirectory($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true);
        }

        $driver = (string) ($dbConfig['driver'] ?? 'mysql');
        $dbName = (string) ($dbConfig['database'] ?? 'affiliate_os');
        // Keep the name filesystem-safe (e.g. sqlite ":memory:" or names with path separators).
        $dbBaseName = trim((string) preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename($dbName)), '_') ?: 'database';

        $sqlFile = "{$destinationDir}/backup_{$dbBaseName}_{$timestamp}.sql";
        $gzFile = "{$sqlFile}.gz";

        $this->info("1. Exporting database [{$driver} : {$dbBaseName}]...");

        try {
            $this->dumpDatabase($driver, $dbConfig, $sqlFile);
        } catch (Throwable $e) {
            $this->error('Database dump failed: '.$e->getMessage());
            Log::critical('Backup failed during database dump: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! File::exists($sqlFile) || File::size($sqlFile) === 0) {
            $this->error('Generated SQL dump is empty or missing.');

            return self::FAILURE;
        }

        $rawSizeBytes = File::size($sqlFile);
        $this->info('   SQL export completed successfully ('.round($rawSizeBytes / 1024 / 1024, 2).' MB).');

        // 2. Gzip compression (streaming to avoid memory exhaustion on large dumps - Fix #20)
        $this->info('2. Compressing SQL dump via Gzip stream...');
        $srcHandle = fopen($sqlFile, 'rb');
        if (! $srcHandle) {
            $this->error('Failed to open SQL file for compression.');

            return self::FAILURE;
        }

        $gzHandle = gzopen($gzFile, 'wb9');
        if (! $gzHandle) {
            fclose($srcHandle);
            $this->error('Failed to create Gzip destination file.');

            return self::FAILURE;
        }

        while (! feof($srcHandle)) {
            $chunk = fread($srcHandle, 65536);
            if ($chunk !== false && strlen($chunk) > 0) {
                gzwrite($gzHandle, $chunk);
            }
        }

        fclose($srcHandle);
        gzclose($gzHandle);
        File::delete($sqlFile); // Remove plain SQL file

        $finalArchiveFile = $gzFile;

        // 3. Optional AES-256-CBC Encryption
        $shouldEncrypt = (bool) $this->option('encrypt') || config('app.env') === 'production';
        if ($shouldEncrypt) {
            $this->info('3. Encrypting archive using AES-256-CBC...');
            $encFile = "{$gzFile}.enc";
            $key = $this->resolveEncryptionKey();

            try {
                $this->encryptFile($gzFile, $encFile, $key);
                File::delete($gzFile);
                $finalArchiveFile = $encFile;
                $this->info('   Encryption completed successfully.');
            } catch (Throwable $e) {
                $this->error('Encryption failed: '.$e->getMessage());
                Log::critical('Backup encryption failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        // 4. Generate SHA-256 Checksum
        $this->info('4. Calculating SHA-256 checksum for integrity verification...');
        $checksum = hash_file('sha256', $finalArchiveFile);
        $checksumFile = "{$finalArchiveFile}.sha256";
        File::put($checksumFile, "{$checksum}  ".basename($finalArchiveFile)."\n");
        $this->info("   SHA-256: {$checksum}");

        // 5. Tenant Storage Snapshot
        if (! $this->option('skip-storage')) {
            $this->info('5. Archiving tenant storage uploads...');
            $this->archiveStorage($destinationDir, $timestamp);
        }

        // 6. Push to external disk if configured
        $remoteDisk = $this->option('disk');
        if (is_string($remoteDisk) && $remoteDisk !== '') {
            $this->info("6. Uploading backup archive to external disk [{$remoteDisk}]...");

            try {
                $disk = Storage::disk($remoteDisk);
                $remoteArchiveName = 'backups/'.basename($finalArchiveFile);
                $remoteChecksumName = 'backups/'.basename($checksumFile);

                $stream = fopen($finalArchiveFile, 'r');
                if (is_resource($stream)) {
                    $disk->put($remoteArchiveName, $stream);
                    fclose($stream);
                }
                $disk->put($remoteChecksumName, (string) File::get($checksumFile));
                $this->info('   External upload completed successfully.');
            } catch (Throwable $e) {
                $this->warn('Remote upload warning: '.$e->getMessage());
                Log::warning('Backup remote upload failed: '.$e->getMessage());
            }
        }

        // 7. Retention cleanup
        $retentionDays = max(1, (int) $this->option('retention-days'));
        $this->info("7. Pruning local backups older than {$retentionDays} days...");
        $this->pruneOldBackups($destinationDir, $retentionDays);

        $this->info('====================================================');
        $this->info('Backup Completed Successfully: '.basename($finalArchiveFile));
        $this->info('====================================================');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function dumpDatabase(string $driver, array $config, string $outputSqlPath): void
    {
        if ($driver === 'sqlite') {
            $this->dumpSqlite($config, $outputSqlPath);

            return;
        }

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $socket = (string) ($config['unix_socket'] ?? '');

        $cmd = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
        ];

        if ($socket !== '') {
            $cmd[] = "--socket={$socket}";
        } else {
            $cmd[] = "--host={$host}";
            $cmd[] = "--port={$port}";
        }

        $cmd[] = "--user={$username}";
        $cmd[] = $database;

        // Fix #20: Pass password via process environment variable MYSQL_PWD instead of CLI argument
        // This avoids leaking credentials in process lists (ps aux) and eliminates mysqldump insecure password warnings
        $processEnv = [];
        if ($password !== '') {
            $processEnv['MYSQL_PWD'] = $password;
        }

        $process = new Process($cmd, env: $processEnv);
        $process->setTimeout(300);

        // Run mysqldump and write output to destination
        $fileHandle = fopen($outputSqlPath, 'w');
        if (! $fileHandle) {
            throw new \RuntimeException("Unable to open output file for writing: {$outputSqlPath}");
        }

        $process->run(function ($type, $buffer) use ($fileHandle) {
            if ($type === Process::OUT) {
                fwrite($fileHandle, $buffer);
            }
        });

        fclose($fileHandle);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysqldump error: '.$process->getErrorOutput());
        }
    }

    /**
     * Fallback dumper for SQLite environments (e.g. testing and dev).
     *
     * @param  array<string, mixed>  $config
     */
    protected function dumpSqlite(array $config, string $outputSqlPath): void
    {
        $sqlitePath = (string) ($config['database'] ?? '');

        if ($sqlitePath === ':memory:' || ! File::exists($sqlitePath)) {
            // Memory or non-file SQLite: dump via PDO schema & tables
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $out = '-- SQLite in-memory dump generated at '.now()->toIso8601String()."\n";
            $out .= "PRAGMA foreign_keys=OFF;\n";

            foreach ($tables as $table) {
                /** @var object{name: string} $table */
                $tableName = $table->name;
                $createRow = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$tableName]);
                if ($createRow && isset($createRow->sql)) {
                    // Drop first so the dump can be replayed onto a database that already has the schema.
                    $out .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                    $out .= $createRow->sql.";\n";
                }

                $rows = DB::table($tableName)->get();
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $cols = array_map(fn ($k) => "`{$k}`", array_keys($data));
                    $vals = array_map(function ($v) {
                        if ($v === null) {
                            return 'NULL';
                        }

                        // SQLite escapes quotes by doubling them; backslashes are literal.
                        return "'".str_replace("'", "''", (string) $v)."'";
                    }, array_values($data));

                    $out .= "INSERT INTO `{$tableName}` (".implode(',', $cols).') VALUES ('.implode(',', $vals).");\n";
                }
            }

            $out .= "PRAGMA foreign_keys=ON;\n";
            File::put($outputSqlPath, $out);

            return;
        }

        // Copy sqlite file as raw dump
        File::copy($sqlitePath, $outputSqlPath);
    }

    /**
     * Encrypt a file using AES-256-CBC with an authentication HMAC.
     */
    protected function encryptFile(string $inputFile, string $outputFile, string $key): void
    {
        $cipher = 'aes-256-cbc';
        $ivLength = openssl_cipher_iv_length($cipher);
        if ($ivLength <= 0) {
            throw new \RuntimeException('Failed to determine IV length for AES-256-CBC.');
        }

        /** @var int<1, max> $safeIvLength */
        $safeIvLength = max(1, $ivLength);
        $iv = random_bytes($safeIvLength);
        $data = (string) File::get($inputFile);

        // Derive 32-byte key
        $derivedKey = hash('sha256', $key, true);

        $ciphertext = openssl_encrypt($data, $cipher, $derivedKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('openssl_encrypt failed during backup encryption.');
        }

        // Compute HMAC-SHA256 for authenticated encryption (EtM: Encrypt-then-MAC)
        $hmac = hash_hmac('sha256', $iv.$ciphertext, $derivedKey, true);

        // Output format: [IV (16 bytes)] [HMAC (32 bytes)] [CIPHERTEXT]
        File::put($outputFile, $iv.$hmac.$ciphertext);
    }

    /**
     * Archive storage/app/public directory.
     */
    protected function archiveStorage(string $destinationDir, string $timestamp): void
    {
        $storagePublic = storage_path('app/public');
        if (! File::isDirectory($storagePublic)) {
            return;
        }

        $storageArchive = "{$destinationDir}/storage_public_{$timestamp}.tar.gz";

        if (class_exists(\PharData::class)) {
            try {
                $tarPath = "{$destinationDir}/storage_public_{$timestamp}.tar";
                if (File::exists($tarPath)) {
                    File::delete($tarPath);
                }

                $phar = new \PharData($tarPath);
                $phar->buildFromDirectory($storagePublic);
                $phar->compress(\Phar::GZ);
                unset($phar);

                if (File::exists($tarPath)) {
                    File::delete($tarPath);
                }

                $this->info('   Storage directory archived successfully.');
            } catch (Throwable $e) {
                $this->warn('   Storage tar warning (PharData): '.$e->getMessage());
            }
        }
    }

    /**
     * Prune old backup files exceeding retention threshold.
     */
    protected function pruneOldBackups(string $directory, int $retentionDays): void
    {
        $threshold = now()->subDays($retentionDays)->timestamp;
        $files = File::files($directory);
        $prunedCount = 0;

        foreach ($files as $file) {
            if ($file->getMTime() < $threshold) {
                File::delete($file->getPathname());
                $prunedCount++;
            }
        }

        if ($prunedCount > 0) {
            $this->info("   Pruned {$prunedCount} outdated backup files.");
        }
    }

    /**
     * Resolve the AES-256 encryption key.
     */
    protected function resolveEncryptionKey(): string
    {
        $customKey = $this->option('encryption-key');
        if (is_string($customKey) && $customKey !== '') {
            return $customKey;
        }

        $envKey = getenv('BACKUP_ENCRYPTION_KEY');
        if (is_string($envKey) && $envKey !== '') {
            return $envKey;
        }

        /** @var string $appKey */
        $appKey = config('app.key', '');
        if ($appKey !== '') {
            return $appKey;
        }

        throw new \RuntimeException('No encryption key configured! Set BACKUP_ENCRYPTION_KEY or APP_KEY.');
    }
}
