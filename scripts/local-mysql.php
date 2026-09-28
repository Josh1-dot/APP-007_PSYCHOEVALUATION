<?php

use App\Models\Assessment;
use App\Models\ClinicalNote;
use App\Models\Interpretation;
use App\Models\Message;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Local migration helper. No secrets are written to stdout.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function inventory(): array
{
    $tables = DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
    $inventory = [];
    foreach ($tables as $row) {
        $table = array_values((array) $row)[0];
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new RuntimeException('Nom de table non pris en charge.');
        }
        $keys = DB::select("SHOW INDEX FROM `$table` WHERE Key_name = 'PRIMARY'");
        usort($keys, fn ($a, $b) => $a->Seq_in_index <=> $b->Seq_in_index);
        if (! $keys) {
            throw new RuntimeException('Une table sans clé primaire exige un transfert manuel.');
        }
        $order = implode(', ', array_map(fn ($key) => '`'.str_replace('`', '``', $key->Column_name).'`', $keys));
        $hash = hash_init('sha256');
        $count = 0;
        foreach (DB::cursor("SELECT * FROM `$table` ORDER BY $order") as $record) {
            hash_update($hash, json_encode((array) $record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
            $count++;
        }
        $inventory[$table] = ['rows' => $count, 'sha256' => hash_final($hash)];
    }
    ksort($inventory);

    return $inventory;
}

try {
    $mode = $argv[1] ?? '';
    $directory = $argv[2] ?? '';
    if ($mode === 'connection') {
        $c = DB::connection()->getConfig();
        echo json_encode(['driver' => $c['driver'], 'socket' => $c['unix_socket'] ?? '', 'database' => $c['database']], JSON_THROW_ON_ERROR).PHP_EOL;
    } elseif ($mode === 'snapshot') {
        if (! is_dir($directory)) {
            throw new RuntimeException('Dossier de sauvegarde absent.');
        }
        $c = DB::connection()->getConfig();
        if ($c['driver'] !== 'mysql') {
            throw new RuntimeException('La source doit être MySQL.');
        }
        $quote = fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
        $config = "[client]\nuser=".$quote($c['username'])."\npassword=".$quote($c['password'])."\n";
        if (! empty($c['unix_socket'])) {
            $config .= "protocol=socket\nsocket=".$quote($c['unix_socket'])."\n";
        } else {
            $config .= 'host='.$quote($c['host'])."\nport=".(int) $c['port']."\n";
        }
        file_put_contents($directory.'/source.cnf', $config);
        chmod($directory.'/source.cnf', 0600);
        file_put_contents($directory.'/source-database.txt', $c['database']);
        file_put_contents($directory.'/inventory.json', json_encode(inventory(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo "Inventaire de la source enregistré.\n";
    } elseif ($mode === 'verify') {
        $expected = json_decode(file_get_contents($directory.'/inventory.json'), true, flags: JSON_THROW_ON_ERROR);
        if (inventory() !== $expected) {
            throw new RuntimeException('Le contenu importé diffère de la source. La configuration ne doit pas être basculée.');
        }
        echo 'Import vérifié : '.count($expected)." tables identiques, empreintes et nombres de lignes concordants.\n";
    } elseif ($mode === 'decrypt') {
        // Validate that the unchanged APP_KEY still decrypts clinical content.
        foreach ([
            Assessment::class => ['answers', 'results'],
            Interpretation::class => ['draft', 'published_content', 'input_snapshot'],
            ClinicalNote::class => ['body'],
            Message::class => ['body'],
        ] as $model => $fields) {
            foreach ($model::withoutGlobalScopes()->cursor() as $record) {
                foreach ($fields as $field) {
                    $value = $record->getAttribute($field);
                }
            }
        }
        echo "Déchiffrement des données applicatives vérifié.\n";
    } else {
        throw new RuntimeException('Commande attendue : connection, snapshot, verify ou decrypt.');
    }
} catch (Throwable $exception) {
    // Database exceptions can contain passwords in SQL. Do not print their text.
    if ($exception instanceof QueryException || $exception instanceof PDOException) {
        fwrite(STDERR, "Connexion ou requête MySQL impossible. Vérifiez le service et les accès.\n");
    } else {
        fwrite(STDERR, $exception->getMessage()."\n");
    }
    exit(1);
}
