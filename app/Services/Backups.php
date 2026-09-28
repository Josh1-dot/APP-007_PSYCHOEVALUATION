<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class Backups
{
    public function directory(): string
    {
        return storage_path('app/private/backups');
    }

    private function key(): string
    {
        $path = $this->directory().'/.recovery-key';
        File::ensureDirectoryExists($this->directory(), 0700);
        if (! is_file($path)) {
            $handle = fopen($path, 'x');
            if ($handle) {
                chmod($path, 0600);
                fwrite($handle, base64_encode(random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)));
                fclose($handle);
            }
        }
        $key = base64_decode(trim(file_get_contents($path)), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('Clé de sauvegarde invalide.');
        }

return $key;
    }

    public function encrypt(string $input, string $output, string $key): void
    {
        [$state,$header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $source = fopen($input, 'rb');
        $target = fopen($output, 'xb');
        chmod($output, 0600);
        try {
            fwrite($target, 'PSYBACK1'.$header);
            while (! feof($source)) {
                $chunk = fread($source, 1048576);
                $tag = feof($source) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : 0;
                $encrypted = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                fwrite($target, pack('N', strlen($encrypted)).$encrypted);
            }
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    public function decrypt(string $input, string $output, string $key): void
    {
        $source = fopen($input, 'rb');
        $target = fopen($output, 'xb');
        chmod($output, 0600);
        try {
            if (fread($source, 8) !== 'PSYBACK1') {
                throw new RuntimeException('Format de sauvegarde invalide.');
            }
            $header = fread($source, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $final = false;
            while (! feof($source)) {
                $length = fread($source, 4);
                if ($length === '') {
                    break;
                }if (strlen($length) !== 4) {
                    throw new RuntimeException('Sauvegarde tronquée.');
                }$size = unpack('N', $length)[1];
                if ($size < 17 || $size > 1048593) {
                    throw new RuntimeException('Bloc de sauvegarde invalide.');
                }$cipher = '';
                while (strlen($cipher) < $size && ! feof($source)) {
                    $cipher .= fread($source, $size - strlen($cipher));
                }$result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new RuntimeException('Sauvegarde altérée ou mauvaise clé.');
                }[$plain,$tag] = $result;
                fwrite($target, $plain);
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $final = true;
                    if (fread($source, 1) !== '') {
                        throw new RuntimeException('Contenu inattendu après la sauvegarde.');
                    }break;
                }
            }
            if (! $final) {
                throw new RuntimeException('Sauvegarde incomplète.');
            }
        } catch (\Throwable $e) {
            fclose($target);
            fclose($source);
            @unlink($output);
            throw $e;
        }
        fclose($source);
        fclose($target);
    }

    public function create(): string
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('La sauvegarde opérationnelle nécessite MySQL.');
        }
        $key = $this->key();
        $work = $this->directory().'/.work-'.Str::uuid();
        File::makeDirectory($work, 0700, true);
        $name = 'psycho-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.psyenc';
        $destination = $this->directory().'/'.$name;
        try {
            $config = DB::connection()->getConfig();
            $quote = fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
            $cnf = "[client]\nuser=".$quote($config['username'])."\npassword=".$quote($config['password'])."\n";
            $cnf .= ! empty($config['unix_socket']) ? "protocol=socket\nsocket=".$quote($config['unix_socket'])."\n" : 'host='.$quote($config['host'])."\nport=".(int) $config['port']."\n";
            file_put_contents($work.'/mysql.cnf', $cnf);
            chmod($work.'/mysql.cnf', 0600);
            $dump = fopen($work.'/database.sql', 'xb');
            chmod($work.'/database.sql', 0600);
            try {
                $process = new Process(['mysqldump', '--defaults-extra-file='.$work.'/mysql.cnf', '--single-transaction', '--hex-blob', '--no-tablespaces', '--set-gtid-purged=OFF', $config['database']]);
                $process->setTimeout(600);
                $process->run(function ($type, $buffer) use ($dump) {
                    if ($type === Process::OUT) {
                        fwrite($dump, $buffer);
                    }
                });
                if (! $process->isSuccessful()) {
                    throw new RuntimeException('Échec de la sauvegarde MySQL. Vérifiez les accès et mysqldump.');
                }
            } finally {
                fclose($dump);
            }
            copy(base_path('.env'), $work.'/application.env');
            chmod($work.'/application.env', 0600);
            $files = File::allFiles(storage_path('app/private'));
            $digests = [];
            foreach ($files as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(storage_path('app/private')) + 1));
                if (str_starts_with($relative, 'backups/') || str_starts_with($relative, 'migration-backups/')) {
                    continue;
                }$digests[$relative] = hash_file('sha256', $file->getPathname());
            }
            file_put_contents($work.'/manifest.json', json_encode(['created_at' => now()->toIso8601String(), 'database_sha256' => hash_file('sha256', $work.'/database.sql'), 'environment_sha256' => hash_file('sha256', $work.'/application.env'), 'files' => $digests], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $tar = new Process(['tar', '-czf', $work.'/archive.tar.gz', '-C', $work, 'database.sql', 'application.env', 'manifest.json', '-C', storage_path('app'), '--exclude=private/backups', '--exclude=private/migration-backups', 'private']);
            $tar->setTimeout(600);
            $tar->mustRun();
            $this->encrypt($work.'/archive.tar.gz', $destination, $key);
            $this->verify($destination);
            file_put_contents($this->directory().'/last-success.json', json_encode(['name' => $name, 'created_at' => now()->toIso8601String(), 'size' => filesize($destination), 'verified' => true], JSON_THROW_ON_ERROR));
            foreach (glob($this->directory().'/psycho-*.psyenc') as $old) {
                if (filemtime($old) < now()->subDays(30)->timestamp) {
                    unlink($old);
                }
            }

            return $destination;
        } catch (\Throwable $exception) {
            @unlink($destination);
            throw $exception;
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function verify(string $path): array
    {
        $real = realpath($path);
        if (! $real || dirname($real) !== realpath($this->directory()) || ! str_ends_with($real, '.psyenc')) {
            throw new RuntimeException('Choisissez une archive du dossier de sauvegardes.');
        }
        $work = $this->directory().'/.verify-'.Str::uuid();
        File::makeDirectory($work, 0700, true);
        try {
            $this->decrypt($real, $work.'/archive.tar.gz', $this->key());
            $list = new Process(['tar', '-tzf', $work.'/archive.tar.gz']);
            $list->mustRun();
            foreach (explode("\n", trim($list->getOutput())) as $entry) {
                if (str_starts_with($entry, '/') || in_array('..', explode('/', $entry))) {
                    throw new RuntimeException('Chemin dangereux dans l’archive.');
                }
            }
            $extract = new Process(['tar', '-xzf', $work.'/archive.tar.gz', '-C', $work, '--no-same-owner', '--no-same-permissions']);
            $extract->mustRun();
            $manifest = json_decode(file_get_contents($work.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            if (! hash_equals($manifest['database_sha256'], hash_file('sha256', $work.'/database.sql')) || ! hash_equals($manifest['environment_sha256'], hash_file('sha256', $work.'/application.env'))) {
                throw new RuntimeException('Empreinte de la base ou de la configuration incorrecte.');
            }
            foreach ($manifest['files'] as $name => $hash) {
                if (! is_file($work.'/private/'.$name) || ! hash_equals($hash, hash_file('sha256', $work.'/private/'.$name))) {
                    throw new RuntimeException('Un fichier privé ne correspond pas au manifeste.');
                }
            }

            return $manifest;
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function extract(string $path, string $destination): void
    {
        $this->verify($path);
        if (file_exists($destination)) {
            throw new RuntimeException('Le dossier de restauration doit être nouveau.');
        }File::makeDirectory($destination,0700,true);
        $archive = $destination.'/archive.tar.gz';
        $this->decrypt($path,$archive,$this->key());
        (new Process(['tar', '-xzf', $archive, '-C', $destination, '--no-same-owner', '--no-same-permissions']))->mustRun();
        unlink($archive);
    }
}
