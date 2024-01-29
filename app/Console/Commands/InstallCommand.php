<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'app:install
                        {--db-host=localhost : Database Host}
                        {--db-port=3306 : Port for the database}
                        {--db-database= : Name for the database}
                        {--db-username=root : Username for accessing the database}
                        {--db-password= : Password for accessing the database}
                        ';

    protected $description = 'Installation Initial du système';

    public function handle()
    {
        if ($this->missingRequiredOptions()) {
            $this->error('Missing required options');
            $this->line('please run');
            $this->line('php artisan app:install --help');
            $this->line('to see the command usage.');
            return 0;
        }
        $this->alert('Application is installing...');
        static::copyEnvExampleToEnv();
        $this->generateAppKey();
        $this->updateEnvVariablesFromOptions();
        $this->info('Env file created successfully.');
        $this->info('Runnning migrations and seeders...');
        if (!static::runMigrationsWithSeeders()) {
            $this->error('Your database credentials are wrong!');
            return 0;
        }

        $this->alert('Application is installed successfully.');
        $this->installSubsidiary();
        return 1;
    }

    public function missingRequiredOptions(): bool
    {
        return !$this->option('db-database');
    }

    private function updateEnv($data)
    {
        $env = file_get_contents(base_path('.env'));
        $env = explode("\n", $env);
        foreach ($data as $dataKey => $dataValue) {
            $alreadyExistInEnv = false;
            foreach ($env as $envKey => $envValue) {
                $entry = explode('=', $envValue, 2);
                // Check if exists or not in env file
                if ($entry[0] == $dataKey) {
                    $env[$envKey] = $dataKey . '=' . $dataValue;
                    $alreadyExistInEnv = true;
                } else {
                    $env[$envKey] = $envValue;
                }
            }
            // add the variable if not exists in env
            if (!$alreadyExistInEnv) {
                $env[] = $dataKey . '=' . $dataValue;
            }
        }
        $env = implode("\n", $env);
        file_put_contents(base_path('.env'), $env);
        return true;
    }

    public static function copyEnvExampleToEnv()
    {
        if (!is_file(base_path('.env')) && is_file(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
        }
    }

    public static function generateAppKey()
    {
        Artisan::call('key:generate');
    }

    public static function runMigrationsWithSeeders()
    {
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public function updateEnvVariablesFromOptions()
    {
        $this->updateEnv([
            'DB_HOST' => $this->option('db-host'),
            'DB_PORT' => $this->option('db-port'),
            'DB_DATABASE' => $this->option('db-database'),
            'DB_USERNAME' => $this->option('db-username'),
            'DB_PASSWORD' => $this->option('db-password'),
        ]);
        $conn = config('database.default', 'mysql');
        $dbConfig = Config::get("database.connections.$conn");

        $dbConfig['host'] = $this->option('db-host');
        $dbConfig['port'] = $this->option('db-port');
        $dbConfig['database'] = $this->option('db-database');
        $dbConfig['username'] = $this->option('db-username');
        $dbConfig['password'] = $this->option('db-password');
        Config::set("database.connections.$conn", $dbConfig);
        DB::purge($conn);
        DB::reconnect($conn);
    }

    private function installSubsidiary()
    {
        if(!file_exists(base_path('.github'))) {
            mkdir(base_path('.github'));
            mkdir(base_path('.github/workflows'));
        }

        if($this->confirm('Voulez-vous utiliser "Close Issue" ?')) {
            $this->createFileCloseIssue();
        }

        if($this->confirm("Voulez-vous utiliser 'Label' ?")) {
            $this->createFileLabeled();
        }

        $this->createDependabotAutoMerge();
        $this->createFixPhpStyle();

        if($this->confirm("Voulez-vous utiliser 'PhpStan' ?")) {
            spin(function () {
                Process::run('composer require --dev phpstan/phpstan');
                Process::run('composer require --dev nunomaduro/larastan');
                Process::run('php artisan vendor:publish --provider="NunoMaduro\Larastan\LarastanServiceProvider" --tag=config');
                $this->createPhpStan();
            }, "Installation de PhpStan...");
        }


        if($this->confirm("Voulez-vous utiliser 'PR Update' ?")) {
            $this->createPrUpdate();
        }

        if($this->confirm("Voulez-vous utiliser 'Release' ?")) {
            $this->createRelease();
        }

        if($this->confirm("Voulez-vous utiliser 'Test' ?")) {
            $this->createTest();
        }

        if($this->confirm("Voulez-vous utiliser 'Update Changelog' ?")) {
            $this->createUpChangelog();
        }

        $this->info("Installation des dépendances supplémentaires...");
        $this->info("Installation de Log Viewer...");

        Process::run('composer require arcanedev/log-viewer');
        Process::run('php artisan log-viewer:publish');

        $this->info("Installation de Github API");
        Process::run('composer require knplabs/github-api');

        $this->info("Installation de Livewire");
        Process::run('composer require livewire/livewire');
        Process::run('composer require jantinnerezo/livewire-alert');

        Process::run('git add .');
        Process::run('git commit -m "Init System"');
        Process::run('git push origin master');

        $this->alert("Installation des packages subsidiaire Terminer");
    }

    private function createFileCloseIssue()
    {
        $content = file_get_contents('https://github.com/vortechstudio-packager/github-workflow/raw/main/github/workflows/close_issue.yml');
        file_put_contents(base_path('.github/workflows/close-issue.yml'), $content, FILE_APPEND);
        $this->info('File close-issue.yml created successfully.');
    }

    private function createFileLabeled()
    {
        $content_work = file_get_contents('https://github.com/vortechstudio-packager/github-workflow/raw/main/github/workflows/label.yml');
        file_put_contents(base_path('.github/workflows/label.yml'), $content_work, FILE_APPEND);
        $content_pint = file_get_contents('https://github.com/vortechstudio-packager/github-workflow/raw/main/github/labeler.yml');
        file_put_contents(base_path('.github/labeler.yml'), $content_pint, FILE_APPEND);
    }

    private function createDependabotAutoMerge()
    {
        $content_work = file_get_contents('https://github.com/vortechstudio-packager/github-workflow/raw/main/github/workflows/dependabot-auto-merge.yml');
        file_put_contents(base_path('.github/workflows/dependabot-auto-merge.yml'), $content_work, FILE_APPEND);
        $this->info('File dependabot-auto-merge.yml created successfully.');

        $content_pint = file_get_contents('https://github.com/vortechstudio-packager/github-workflow/raw/main/github/dependabot.yml');
        file_put_contents(base_path('.github/dependabot.yml'), $content_pint, FILE_APPEND);
        $this->info('File dependabot.yml created successfully.');
    }

    private function createFixPhpStyle()
    {
        $content_work = file_get_contents('https://github.com/vortechstudio-packager/github-workflow/raw/main/github/workflows/fix-php-code-style.yml');
        file_put_contents(base_path('.github/workflows/fix-php-code-style.yml'), $content_work, FILE_APPEND);
        $this->info('File fix-php-code-style.yml created successfully.');
    }

    private function createPhpStan()
    {
        $content_file_dist = file_get_contents('https://raw.githubusercontent.com/vortechstudio-packager/github-workflow/main/phpstan.neon.dist');
        file_put_contents(base_path('phpstan.dist.neon'), $content_file_dist, FILE_APPEND);
        $this->info('File phpstan.dist.neon created successfully.');

        $content_file_base = file_get_contents('https://raw.githubusercontent.com/vortechstudio-packager/github-workflow/main/phpstan-baseline.neon');
        file_put_contents(base_path('phpstan-baseline.neon'), $content_file_base, FILE_APPEND);
        $this->info('File phpstan-baseline.neon created successfully.');

        $content_work = file_get_contents('https://github.com/vortechstudio-packager/github-workflow/raw/main/github/workflows/phpstan.yml');
        file_put_contents(base_path('.github/workflows/phpstan.yml'), $content_work, FILE_APPEND);
        $this->info('File phpstan.yml created successfully.');
    }

    private function createPrUpdate()
    {
        $content_work = file_get_contents('https://raw.githubusercontent.com/vortechstudio-packager/github-workflow/main/github/workflows/pr_update.yml');
        file_put_contents(base_path('.github/workflows/pr-update.yml'), $content_work, FILE_APPEND);
        $this->info('File pr-update.yml created successfully.');
    }

    private function createRelease()
    {
        $content_work = file_get_contents('https://raw.githubusercontent.com/vortechstudio-packager/github-workflow/main/github/workflows/release.yml');
        file_put_contents(base_path('.github/workflows/release.yml'), $content_work, FILE_APPEND);
        $this->info('File release.yml created successfully.');

        $content_semantic = file_get_contents('https://raw.githubusercontent.com/vortechstudio-packager/github-workflow/main/.releaserc');
        file_put_contents(base_path('.releaserc'), $content_semantic, FILE_APPEND);
        $this->info('File .releaserc created successfully.');
    }

    private function createTest()
    {
        $content_work = file_get_contents('https://raw.githubusercontent.com/vortechstudio-packager/github-workflow/main/github/workflows/test.yml');
        file_put_contents(base_path('.github/workflows/test.yml'), $content_work, FILE_APPEND);
        $this->info('File test.yml created successfully.');
    }

    private function createUpChangelog()
    {
        $content_work = file_get_contents('https://github.com/vortechstudio-packager/github-workflow/raw/main/github/workflows/update_changelog.yml');
        file_put_contents(base_path('.github/workflows/update_changelog.yml'), $content_work, FILE_APPEND);
        $this->info('File update_changelog.yml created successfully.');

        file_put_contents(base_path('CHANGELOG.md'), '', FILE_APPEND);
    }
}
