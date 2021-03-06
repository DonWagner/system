<?php

namespace Nova\Module\Console;

use Nova\Console\Command;
use Nova\Filesystem\Filesystem;
use Nova\Module\ModuleManager;
use Nova\Support\Str;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;


class MakeModuleCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'make:module';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Application Module';

    /**
     * Module folders to be created.
     *
     * @var array
     */
    protected $moduleFolders = array(
        'Assets/',
        'Config/',
        'Assets/css/',
        'Assets/images/',
        'Assets/js/',
        'Controllers/',
        'Language/',
        'Models/',
        'Policies/',
        'Providers/',
        'Views/',
    );

    /**
     * Module files to be created.
     *
     * @var array
     */
    protected $moduleFiles = array(
        'Config/Config.php',
        'Providers/AuthServiceProvider.php',
        'Providers/EventServiceProvider.php',
        'Providers/ModuleServiceProvider.php',
        'Providers/RouteServiceProvider.php',
        'Bootstrap.php',
        'Events.php',
        'Filters.php',
        'Routes.php',
    );

    /**
     * Module stubs used to populate defined files.
     *
     * @var array
     */
    protected $moduleStubs = array(
        'config',
        'auth-service-provider',
        'event-service-provider',
        'module-service-provider',
        'route-service-provider',
        'bootstrap',
        'events',
        'filters',
        'routes',
    );

    /**
     * The modules instance.
     *
     * @var \Nova\Module\ModuleManager
     */
    protected $module;

    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * Array to store the configuration details.
     *
     * @var array
     */
    protected $container = array();

    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
     * @param \>Nova\Module\ModuleManager    $module
     */
    public function __construct(Filesystem $files, ModuleManager $module)
    {
        parent::__construct();

        $this->files  = $files;
        $this->module = $module;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $slug = $this->argument('slug');

        if (Str::length($slug) > 3) {
            $slug = Str::snake($slug);
        } else {
            $slug = Str::lower($slug);
        }

        $this->container['slug'] = $slug;

        //
        $name = (Str::length($slug) > 3) ? Str::studly($slug) : Str::upper($slug);

        $this->container['name']      = $name;
        $this->container['namespace'] = $name;

        if ($this->option('quick')) {
            return $this->generate();
        }

        $this->stepOne();
    }


    /**
     * Step 1: Configure module manifest.
     *
     * @return mixed
     */
    private function stepOne()
    {
        $this->container['name'] = $this->ask('Please enter the name of the module:', $this->container['name']);
        $this->container['slug'] = $this->ask('Please enter the slug for the module:', $this->container['slug']);

        $this->comment('You have provided the following manifest information:');

        $this->comment('Name:        '.$this->container['name']);
        $this->comment('Slug:        '.$this->container['slug']);

        if ($this->confirm('Do you wish to continue?')) {
            $this->generate();
        } else {
            return $this->stepOne();
        }

        return true;
    }

    /**
     * Generate the module.
     */
    protected function generate()
    {
        $steps = array(
            'Generating folders...'      => 'generateFolders',
            'Generating files...'        => 'generateFiles',
            'Generating .gitkeep...'     => 'generateGitkeep',
            'Optimizing module cache...' => 'optimizeModules',
        );

        $progress = new ProgressBar($this->output, count($steps));

        $progress->start();

        foreach ($steps as $message => $function) {
            $progress->setMessage($message);

            $this->$function();

            $progress->advance();
        }

        $progress->finish();

        $this->info("\nModule generated successfully.");
    }

    /**
     * Generate defined module folders.
     */
    protected function generateFolders()
    {
        $slug = $this->container['slug'];

        //
        $path = $this->module->getPath();

        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path);
        }

        $path = $this->getModulePath($slug, true);

        $this->files->makeDirectory($path, 0755, true, true);

        //
        $modulePath = $this->getModulePath($slug);

        // Generate the Module directories.
        foreach ($this->moduleFolders as $folder) {
            $path = $modulePath .$folder;

            $this->files->makeDirectory($path);
        }

        // Generate the Language inner directories.
        $languageFolders = $this->getLanguagePaths($slug);

        foreach ($languageFolders as $folder) {
            $path = $modulePath .$folder;

            $this->files->makeDirectory($path);
        }
    }

    /**
     * Generate defined module files.
     */
    protected function generateFiles()
    {
        foreach ($this->moduleFiles as $key => $file) {
            $file = $this->formatContent($file);

            $this->files->put($this->getDestinationFile($file), $this->getStubContent($key));
        }

        // Generate the Language files
        $slug = $this->container['slug'];

        $modulePath = $this->getModulePath($slug);

        $content ='<?php

return array (
);';

        $languageFolders = $this->getLanguagePaths($slug);

        foreach ($languageFolders as $folder) {
            $path = $modulePath .$folder .DS .'messages.php';

            $this->files->put($path, $content);
        }
    }

    /**
     * Generate .gitkeep files within generated folders.
     */
    protected function generateGitkeep()
    {
        $slug = $this->container['slug'];

        $modulePath = $this->getModulePath($slug);

        foreach ($this->moduleFolders as $folder) {
            $path = $modulePath .$folder;

            //
            $files = $this->files->glob($path .'/*');

            if(! empty($files)) continue;

            $gitkeep = $path .'/.gitkeep';

            $this->files->put($gitkeep, '');
        }
    }

    /**
     * Reset module cache of enabled and disabled modules.
     */
    protected function optimizeModules()
    {
        return $this->callSilent('module:optimize');
    }

    /**
     * Get the path to the module.
     *
     * @param string $slug
     *
     * @return string
     */
    protected function getModulePath($slug = null, $allowNotExists = false)
    {
        if ($slug) {
            return $this->module->getModulePath($slug, $allowNotExists);
        }

        return $this->module->getPath();
    }

    protected function getLanguagePaths($slug)
    {
        $paths = array();

        $languages = $this->nova['config']['languages'];

        foreach (array_keys($languages) as $code) {
            $paths[] = 'Language' .DS .strtoupper($code);
        }

        return $paths;
    }

    /**
     * Get destination file.
     *
     * @param string $file
     *
     * @return string
     */
    protected function getDestinationFile($file)
    {
        return $this->getModulePath($this->container['slug']) .$this->formatContent($file);
    }

    /**
     * Get stub content by key.
     *
     * @param int $key
     *
     * @return string
     */
    protected function getStubContent($key)
    {
        $stub = $this->moduleStubs[$key];

        $path = __DIR__ .DS .'stubs' .DS .$stub .'.stub';

        $content = $this->files->get($path);

        return $this->formatContent($content);
    }

    /**
     * Replace placeholder text with correct values.
     *
     * @return string
     */
    protected function formatContent($content)
    {
        $searches = array(
            '{{slug}}',
            '{{name}}',
            '{{namespace}}',
            '{{path}}'
        );

        $replaces = array(
            $this->container['slug'],
            $this->container['name'],
            $this->container['namespace'],
            $this->module->getNamespace(),
        );

        return str_replace($searches, $replaces, $content);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('slug', InputArgument::REQUIRED, 'The slug of the Module.'),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('--quick', '-Q', InputOption::VALUE_REQUIRED, 'Skip the make:module Wizard and use default values'),
        );
    }
}
