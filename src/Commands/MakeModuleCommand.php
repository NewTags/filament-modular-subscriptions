<?php

namespace HoceineEl\FilamentModularSubscriptions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module {name : The name of the module}';

    protected $description = 'Create a new module for Filament Modular Subscriptions';

    protected $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function handle()
    {
        $name = $this->argument('name');
        $className = Str::studly($name) . 'Module';
        $namespace = $this->laravel->getNamespace() . 'Modules';
        $path = app_path('Modules/' . $className . '.php');

        if ($this->files->exists($path)) {
            $this->error('Module already exists!');
            return false;
        }

        $this->makeDirectory($path);

        $stub = $this->files->get(__DIR__ . '/../../stubs/Module.stub');

        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ name }}', '{{ label_key }}'],
            [$namespace, $className, $name, Str::snake($name) . '_module'],
            $stub
        );

        $this->files->put($path, $stub);

        $this->info('Module created successfully.');

        $this->info('Don\'t forget to register your new module in the config/filament-modular-subscriptions.php file.');

        return true;
    }

    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }

        return $path;
    }
}
