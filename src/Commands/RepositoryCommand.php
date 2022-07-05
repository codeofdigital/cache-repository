<?php

namespace CodeOfDigital\CacheRepository\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Pluralizer;

class RepositoryCommand extends BaseCommand
{
    /**
     * The name of the command
     *
     * @var string
     */
    protected $signature = 'make:repository {model} {--cache : Whether to use caching in your repository}';

    /**
     * The description of command
     *
     * @var string
     */
    protected $description = 'Create a new repository';

    /**
     * Stub paths
     *
     * @var array|string[]
     */
    protected array $stubs = [
        'interface' => __DIR__ . '/stubs/repository-interface.stub',
        'repository' => __DIR__ . '/stubs/repository.stub'
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->checkModel();
        list($interface, $interfaceName) = $this->createInterface();
        $this->createRepository($interface, $interfaceName);
    }

    protected function checkModel()
    {
        $model = $this->appNamespace.$this->getSingularName($this->argument('model'));

        $this->model = str_replace('/', '\\', $model);

        if ($this->laravel->runningInConsole()) {
            if (!class_exists($this->model)) {
                $response = $this->ask("Model [{$this->model}] does not exist. Would you like to create it?", 'Yes');

                if ($this->isResponsePositive($response)) {
                    Artisan::call('make:model', [
                        'name' => $this->model
                    ]);

                    $this->info("Model [{$this->model}] has been successfully created.");
                } else {
                    $this->info("Model [{$this->model}] will be skipped.");
                }
            }
        }

        $modelInfo = explode('\\', $this->model);
        $this->modelName = $modelInfo[array_key_last($modelInfo)];
    }

    /**
     * Create a new repository interface
     *
     * @return string[]|void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function createInterface()
    {
        $content = $this->fileManager->get($this->stubs['interface']);

        $replacements = [
            '{{ namespace }}' => "{$this->appNamespace}\Repository\{$this->modelName}",
            '{{ model }}' => $this->modelName
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        $fileName = "{$this->modelName}RepositoryInterface";
        $fileDirectory = app()->basePath() . "/App/Repository/{$this->modelName}";
        $filePath = "{$fileDirectory}{$fileName}.php";

        if (!$this->fileManager->exists($fileDirectory))
            $this->fileManager->makeDirectory($fileDirectory, 0755, true);

        if ($this->laravel->runningInConsole() && $this->fileManager->exists($fileDirectory)) {
            $response = $this->ask("The interface [{$fileName}] has already exists. Do you want to overwrite it?", 'Yes');

            if (!$this->isResponsePositive($response)) {
                $this->line("The interface [{$fileName}] will not be overwritten.");
                return;
            }
        }

        $this->fileManager->put($filePath, $content);

        $this->info("The interface [{$fileName}] has been created");

        return ["{$this->appNamespace}\Repository\{$this->modelName}\{$fileName}", $fileName];
    }

    /**
     * Create a new repository
     *
     * @param string $interface
     * @param string $fileName
     * @return void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function createRepository(string $interface, string $fileName)
    {
        $content = $this->fileManager->get($this->stubs['repository']);

        $replacements = [
            '{{ interfaceNamespace }}' => "{$this->appNamespace}{$interface}",
            '{{ interface }}' => $fileName,
            '{{ model }}' => $this->model,
            '{{ modelName }}' => $this->modelName,
            '{{ namespace }}' => "{$this->appNamespace}\Repository\{$this->modelName}"
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        $fileName = "{$this->modelName}Repository";
        $fileDirectory = app()->basePath() . "/App/Repository/{$this->modelName}";
        $filePath = "{$fileDirectory}{$fileName}.php";

        if (!$this->fileManager->exists($fileDirectory))
            $this->fileManager->makeDirectory($fileDirectory, 0755, true);

        if ($this->laravel->runningInConsole() && $this->fileManager->exists($filePath)) {
            $response = $this->ask("The repository [{$fileName}] already exists. Do you want to overwrite it?", 'Yes');

            if (!$this->isResponsePositive($response)) {
                $this->info("The repository [{$fileName}] will not be overwritten.");
                return;
            }
        }

        $this->fileManager->put($filePath, $content);

        $this->info("The repository [{$filePath}] has been created.");
    }


    public function getSingularName($model): string
    {
        return empty($model) ? '' : ucwords(Pluralizer::singular($model));
    }
}