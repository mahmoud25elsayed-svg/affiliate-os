<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class RestoreDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:restore-database
                            {file : Path to backup archive (.sql, .sql.gz, or .sql.gz.enc)}
                            {--encryption-key= : Custom decryption key if encrypted}
                            {--force : Force restore without interactive confirmation}
                            {--skip-checksum : Skip SHA-256 verification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore and disaster-recover database from compressed, encrypted backup archive';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('AffiliateOS Disaster Recovery & Database Restoration');
        $this->info('====================================================');

        $filePath = (string) $this->argument('file');
        if (! File::exists($filePath)) {
            $this->error("Backup file not found at: {$filePath}");

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('WARNING: Restoring will overwrite existing tables in the database! Do you want to proceed?')) {
                $this->warn('Restoration cancelled by user.');

                return self::SUCCESS;
            }
        }

        $connectionName = config('database.default', 'mysql');
        /** @var array<string, mixed> $dbConfig */
        $dbConfig = config("database.connections.{$connectionName}", []);
        $driver = (string) ($dbConfig['driver'] ?? 'mysql');
        $database = (string) ($dbConfig['database'] ?? '');

        // 1. Verify Checksum
        if (! $this->option('skip-checksum')) {
            $checksumFile = "{$filePath}.sha256";
            if (File::exists($checksumFile)) {
                $this->info('1. Verifying archive SHA-256 integrity checksum...');
                $expectedLine = trim((string) File::get($checksumFile));
                $expectedHash = explode(' ', $expectedLine)[0] ?? '';
                $actualHash = hash_file('sha256', $filePath);

                if (! hash_equals($expectedHash, (string) $actualHash)) {
                    $this->error("Checksum mismatch! Expected: {$expectedHash}, Computed: {$actualHash}");
                    Log::critical('Disaster Recovery Aborted: Backup checksum mismatch!', [
                        'file' => $filePath,
                        'expected' => $expectedHash,
                        'computed' => $actualHash,
                    ]);

                    return self::FAILURE;
                }
                $this->info('   Checksum verified successfully.');
            } else {
                $this->warn('1. Checksum companion file not found; skipping hash verification.');
            }
        }

        $workingFile = $filePath;
        $tempFilesToClean = [];

        try {
            // 2. Decrypt if needed
            if (str_ends_with($workingFile, '.enc')) {
                $this->info('2. Decrypting archive using AES-256-CBC...');
                $key = $this->resolveDecryptionKey();
                $decryptedFile = preg_replace('/\.enc$/', '', $workingFile);
                if ($decryptedFile === $workingFile) {
                    $decryptedFile .= '.dec';
                }

                $this->decryptFile($workingFile, $decryptedFile, $key);
                $tempFilesToClean[] = $decryptedFile;
                $workingFile = $decryptedFile;
                $this->info('   Decryption completed successfully.');
            }

            // 3. Decompress if gzipped
            if (str_ends_with($workingFile, '.gz')) {
                $this->info('3. Decompressing Gzip stream...');
                $sqlFile = preg_replace('/\.gz$/', '', $workingFile);
                if ($sqlFile === $workingFile) {
                    $sqlFile .= '.sql';
                }

                $this->decompressGzip($workingFile, $sqlFile);
                $tempFilesToClean[] = $sqlFile;
                $workingFile = $sqlFile;
                $this->info('   Decompression completed successfully.');
            }

            // 4. Import into Database
            $this->info("4. Importing SQL into [{$driver} : {$database}]...");
            $this->importSql($driver, $dbConfig, $workingFile);

            $this->info('====================================================');
            $this->info('Database Restoration Completed Successfully!');
            $this->info('====================================================');
            Log::info("Database successfully restored from archive: {$filePath}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Restoration failed: '.$e->getMessage());
            Log::critical('Database restoration failure: '.$e->getMessage(), ['exception' => $e]);

            return self::FAILURE;
        } finally {
            foreach ($tempFilesToClean as $tempFile) {
                if (File::exists($tempFile)) {
                    File::delete($tempFile);
                }
            }
        }
    }

    /**
     * Decrypt AES-256-CBC authenticated file.
     */
    protected function decryptFile(string $inputFile, string $outputFile, string $key): void
    {
        $raw = (string) File::get($inputFile);
        $ivLength = 16;
        $hmacLength = 32;

        if (strlen($raw) < ($ivLength + $hmacLength)) {
            throw new \RuntimeException('Corrupted encrypted backup file: payload too short.');
        }

        $iv = substr($raw, 0, $ivLength);
        $storedHmac = substr($raw, $ivLength, $hmacLength);
        $ciphertext = substr($raw, $ivLength + $hmacLength);

        $derivedKey = hash('sha256', $key, true);
        $calculatedHmac = hash_hmac('sha256', $iv.$ciphertext, $derivedKey, true);

        if (! hash_equals($storedHmac, $calculatedHmac)) {
            throw new \RuntimeException('Authentication tag mismatch! Decryption aborted (invalid key or corrupted data).');
        }

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $derivedKey, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('openssl_decrypt failed to recover plaintext.');
        }

        File::put($outputFile, $plaintext);
    }

    /**
     * Decompress gzip file chunk-by-chunk.
     */
    protected function decompressGzip(string $inputFile, string $outputFile): void
    {
        $gzHandle = gzopen($inputFile, 'rb');
        if (! $gzHandle) {
            throw new \RuntimeException("Unable to open Gzip file for reading: {$inputFile}");
        }

        $outHandle = fopen($outputFile, 'wb');
        if (! $outHandle) {
            gzclose($gzHandle);
            throw new \RuntimeException("Unable to open output file for writing: {$outputFile}");
        }

        while (! gzeof($gzHandle)) {
            $chunk = gzread($gzHandle, 65536);
            if ($chunk !== false && strlen($chunk) > 0) {
                fwrite($outHandle, $chunk);
            }
        }

        gzclose($gzHandle);
        fclose($outHandle);
    }

    /**
     * Import raw SQL file into database.
     *
     * @param  array<string, mixed>  $config
     */
    protected function importSql(string $driver, array $config, string $sqlPath): void
    {
        if ($driver === 'sqlite') {
            $sqlitePath = (string) ($config['database'] ?? '');
            if ($sqlitePath === ':memory:') {
                $sqlContent = File::get($sqlPath);
                DB::unprepared($sqlContent);

                return;
            }

            File::copy($sqlPath, $sqlitePath);

            return;
        }

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $socket = (string) ($config['unix_socket'] ?? '');

        $cmd = ['mysql', '--default-character-set=utf8mb4'];

        if ($socket !== '') {
            $cmd[] = "--socket={$socket}";
        } else {
            $cmd[] = "--host={$host}";
            $cmd[] = "--port={$port}";
        }

        $cmd[] = "--user={$username}";
        $cmd[] = $database;

        $processEnv = [];
        if ($password !== '') {
            $processEnv['MYSQL_PWD'] = $password;
        }

        $fileHandle = fopen($sqlPath, 'r');
        if (! $fileHandle) {
            throw new \RuntimeException("Unable to read SQL file: {$sqlPath}");
        }

        $process = new Process($cmd, env: $processEnv, input: $fileHandle);
        $process->setTimeout(600);
        $process->run();
        fclose($fileHandle);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysql restore error: '.$process->getErrorOutput());
        }
    }

    /**
     * Resolve the AES-256 decryption key.
     */
    protected function resolveDecryptionKey(): string
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

        throw new \RuntimeException('No decryption key configured! Provide --encryption-key or set BACKUP_ENCRYPTION_KEY / APP_KEY.');
    }
}

