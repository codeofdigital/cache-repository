<?php

namespace CodeOfDigital\CacheRepository\Commands;

class CriteriaCommand extends BaseCommand
{
    /**
     * The name of the command
     *
     * @var string
     */
    protected $signature = 'make:criteria {criteria}';

    /**
     * The description of command
     *
     * @var string
     */
    protected $description = 'Create a new criteria';

    /**
     * Stub paths
     *
     * @var array|string[]
     */
    protected array $stubs = [
        'criteria' => __DIR__ . '/stubs/criteria.stub'
    ];

    /**
     * Name of criteria
     *
     * @var string
     */
    protected string $criteriaName;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $criteria = $this->argument('criteria');
        $criteriaParts = explode('\\', $criteria);
        $this->criteriaName = $criteriaParts[array_key_last($criteriaParts)];

        $content = $this->fileManager->get($this->stubs['criteria']);

        $replacements = [
            '%namespace%' => "{$this->appNamespace}Criteria\\{$criteria}",
            '%criteriaName%' => $this->criteriaName
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        $fileName = "{$criteria}";
        $fileDirectory = app()->basePath() . "/App/Criteria";
        $filePath = "{$fileDirectory}/{$fileName}.php";

        if (!$this->fileManager->exists($fileDirectory))
            $this->fileManager->makeDirectory($fileDirectory, 0755, true);

        if ($this->laravel->runningInConsole() && $this->fileManager->exists($filePath)) {
            $response = $this->ask("The criteria [{$this->criteriaName}] has already exists. Do you want to overwrite it?", 'Yes');

            if (!$this->isResponsePositive($response)) {
                $this->info("The interface [{$this->criteriaName}] will not be overwritten.");
                return;
            }
        }

        $this->fileManager->put($filePath, $content);

        $this->info("The criteria [{$this->criteriaName}] has been created.");
    }
}