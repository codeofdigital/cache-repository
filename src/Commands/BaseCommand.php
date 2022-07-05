<?php

namespace CodeOfDigital\CacheRepository\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class BaseCommand extends Command
{
    /**
     * File manager.
     *
     * @var Filesystem
     */
    protected Filesystem $fileManager;

    /**
     * Application namespace
     *
     * @var string
     */
    protected string $appNamespace;

    /**
     * Path for related model
     *
     * @var string
     */
    protected string $model;

    /**
     * Name of model
     *
     * @var string
     */
    protected string $modelName;

    public function __construct()
    {
        parent::__construct();
        $this->fileManager = app('files');
        $this->appNamespace = app()->getNamespace();
    }

    /**
     * Determine if the user input is yes.
     *
     * @param $response
     * @return bool
     */
    public function isResponsePositive($response): bool
    {
        return in_array(strtolower($response), ['y', 'yes']);
    }
}