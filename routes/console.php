<?php

use App\Models\Client;
use App\Models\LocalMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Backups;
use App\Services\Retention;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('cabinet:install', function () {
    $name = $this->ask('Nom du cabinet');
    $email = $this->ask('E-mail administrateur');
    $user = $this->ask('Nom de l’administrateur');
    $password = $this->secret('Mot de passe (12 caractères minimum)');
    $validator = validator(compact('name', 'email', 'user', 'password'), ['name' => 'required|string|max:200', 'email' => 'required|email|unique:users', 'user' => 'required|string|max:100', 'password' => 'required|string|min:12|max:128']);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    DB::transaction(function () use ($name, $email, $user, $password) {
        $t = Tenant::create(['name' => $name, 'email' => $email]);
        User::create(['tenant_id' => $t->id, 'name' => $user, 'email' => $email, 'password' => $password, 'role' => 'admin']);
    });
    $this->info('Cabinet créé. Vous pouvez vous connecter.');
})->purpose('Créer un cabinet et son premier administrateur');
Artisan::command('cabinet:demo', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Démonstration disponible uniquement en local/test.');

        return 1;
    }
    DB::transaction(fn () => $this->call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]));
    $this->info('Comptes : admin@demo.test, patient@demo.test, entreprise@demo.test, conseiller@demo.test. Mot de passe : votre variable DEMO_PASSWORD.');
})->purpose('Ajouter un cabinet de démonstration, sans écraser les données');
Artisan::command('cabinet:retention-review', function () {
    foreach (Tenant::all() as $t) {
        $ids = Client::withoutGlobalScopes()->where('tenant_id', $t->id)->whereNotNull('deleted_at')->get()->filter(fn (Client $client) => app(Retention::class)->eligible($client))->pluck('id');
        $this->line('Cabinet #'.$t->id.' : '.count($ids).' dossier(s) à examiner ; identifiants : '.$ids->join(', '));
    }
    $this->info('Rapport indicatif uniquement. Vérifiez les activités liées et les obligations de conservation avant toute suppression.');
})->purpose('Lister les dossiers à examiner selon le délai du cabinet, sans suppression');

Artisan::command('cabinet:backup', function () {
    $lock = Cache::lock('cabinet-backup', 1800);
    if (! $lock->get()) {
        $this->warn('Une sauvegarde est déjà en cours.');

        return 1;
    }
    try {
        $file = app(Backups::class)->create();
        $this->info('Sauvegarde chiffrée et vérifiée : '.basename($file));
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    } finally {
        $lock->release();
    }
})->purpose('Sauvegarder MySQL, les fichiers et la configuration dans une archive chiffrée');
Artisan::command('cabinet:backup-verify {file}', function () {
    try {
        app(Backups::class)->verify($this->argument('file'));
        $this->info('Archive déchiffrée et empreintes vérifiées.');
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }
});
Artisan::command('cabinet:backup-extract {file} {destination}', function () {
    try {
        app(Backups::class)->extract($this->argument('file'), $this->argument('destination'));
        $this->info('Fichiers extraits dans un nouveau dossier privé. Aucune base existante modifiée.');
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }
});
Schedule::command('cabinet:backup')->dailyAt('02:00')->timezone(config('psycho.backup_timezone'))->withoutOverlapping();
Schedule::call(function () {
    LocalMail::withoutGlobalScopes()->where('expires_at', '<', now())->delete();
    DB::table('password_reset_tokens')->where('created_at', '<', now()->subHour())->delete();
})->daily()->timezone(config('psycho.backup_timezone'))->name('expired-access-messages')->withoutOverlapping();
