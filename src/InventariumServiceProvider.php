<?php

namespace Jaulz\Inventarium;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Fluent;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\Commands\InstallCommand;

class InventariumServiceProvider extends PackageServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        $this->extendBlueprint();
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('inventarium')
            ->hasConfigFile('inventarium')
            ->publishesServiceProvider('InventariumServiceProvider')
            ->hasMigration('2013_01_09_141532_create_inventarium_extension')
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishMigrations()
                    ->publishConfigFile()
                    ->askToRunMigrations()
                    ->copyAndRegisterServiceProviderInApp()
                    ->askToStarRepoOnGitHub('jaulz/inventarium');
            });
    }

    public function extendBlueprint()
    {
      Blueprint::macro('inventarium', function (
        string $sourceName = 'title',
        string $weight = 'A',
        ?string $languageName = null,
        ?string $schema = null
      ) {
        /** @var \Illuminate\Database\Schema\Blueprint $this */
        $prefix = $this->prefix;
        $tableName = $this->table;
        $schema = config('inventarium.schema') ?? 'public';
  
        $command = $this->addCommand(
          'inventarium',
          compact(
            'schema',
            'prefix',
            'tableName',
            'sourceName',
            'weight',
            'languageName'
          )
        );
      });
  
      PostgresGrammar::macro('compileInventarium', function (
        Blueprint $blueprint,
        Fluent $command
      ) {
        /** @var \Illuminate\Database\Schema\Grammars\PostgresGrammar $this */
        $schema = $command->schema;
        $prefix = $command->prefix;
        $tableName = $command->tableName;
        $sourceName = $command->sourceName;
        $weight = $command->weight;
        $languageName = $command->languageName;
  
        return [
          sprintf(
            <<<SQL
    SELECT inventarium.create(%s, %s, %s, %s, %s);
  SQL
            ,
            $this->quoteString($schema),
            $this->quoteString($prefix . $tableName),
            $this->quoteString($sourceName),
            $this->quoteString($weight),
            $this->quoteString($languageName)
          ),
        ];
      });
    }
}