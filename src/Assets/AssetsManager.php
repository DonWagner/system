<?php

namespace Nova\Assets;

use Nova\Foundation\Application;

use JShrink\Minifier as JShrink;


class AssetsManager
{
    /**
     * The Nova Config Repository instance
     *
     * @var \Nova\Config\Repository
     */
    protected $config;

    /**
     * The Nova Filesystem instance.
     *
     * @var \Nova\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The assets cache base URI
     *
     * @var string
     */
    protected $baseUri;

    /**
     * The assets cache base directory
     *
     * @var string
     */
    protected $basePath;

    /**
     * @var array Asset templates
     */
    protected static $templates = array(
        'js'  => '<script src="%s" type="text/javascript"></script>',
        'css' => '<link href="%s" rel="stylesheet" type="text/css">'
    );


    /**
     * Create a new Assets Manager instance.
     *
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->config = $app['config'];

        $this->files = $app['files'];

        //
        $this->baseUri = $this->config->get('assets.cache.baseUri', 'assets/cache');

        //
        $basePath = str_replace('/', DS, $this->baseUri);

        $this->basePath = PUBLICDIR .$basePath .DS;
    }

    /**
     * Load js scripts.
     *
     * @param string|array $files The paths to resource files.
     * @param bool         $fetch Wheter or not will be returned the result.
     */
    public function js($data, $fetch = false, $cached = true)
    {
        $type = 'js';

        // Process the given data.
        $files = $this->processFiles($data, $type, $cached);

        return $this->resource($files, $type, $fetch);
    }

    /**
     * Load css scripts.
     *
     * @param string|array $files The paths to resource files.
     * @param bool         $fetch Wheter or not will be returned the result.
     */
    public function css($data, $fetch = false, $cached = true)
    {
        $type = 'css';

        // Process the given data.
        $files = $this->processFiles($data, $type, $cached);

        return $this->resource($files, $type, $fetch);
    }

    protected function processFiles($files, $type, $cached)
    {
        $cacheActive = $this->config->get('assets.cache.active', false);

        $cacheActive = $cacheActive ? $cached : false;

        // Adjust the files parameter to array.
        $files = is_array($files) ? $files : array($files);

        // Filter the non empty entries from the files array.
        $files = array_filter($files, function($value)
        {
            return ! empty($value);
        });

        if (! $cacheActive || empty($files)) {
            // No further processing required.
            return $files;
        }

        // Split the files on local and remote ones.
        list ($result, $files) = $this->parseFiles($files);

        if (! empty($files)) {
            // Create a unique name for the cache file.
            $name = sha1(serialize($files));

            // Update the processed cache file.
            $this->updateCacheFile($name, $type, $files);

            // Push the cache file URI to the result.
            $uri = $this->baseUri .'/' .$type .'/' .$name .'.' .$type;

            array_push($result, site_url($uri));
        }

        return $result;
    }

    protected function parseFiles(array $files)
    {
        $local  = array();
        $remote = array();

        //
        $siteUrl = $this->config['app.url'];

        foreach ($files as $file) {
            if (starts_with($file, $siteUrl)) {
                array_push($local, $file);
            } else {
                array_push($remote, $file);
            }
        }

        return array($remote, $local);
    }

    protected function updateCacheFile($name, $type, array $files)
    {
        $path = $this->getFileName($name, $type);

        if (! $this->validCacheFile($path)) {
            $content = '';

            foreach ($files as $file) {
                $content .= file_get_contents($file);
            }

            // Minify the collected content.
            if ($type == 'css') {
                $content = $this->compress($content);
            } else if ($type == 'js') {
                $content = JShrink::minify($content);
            }

            // Save the content to cache file.
            $this->files->put($path, $content);
        }
    }

    protected function validCacheFile($path)
    {
        if (! is_readable($path)) {
            return false;
        }

        $lifeTime = $this->config->get('assets.cache.lifeTime', 1440);

        // The life time is specified on minutes; transform in seconds.
        $lifeTime *= 60;

        // Calculate the expiration's timestamp.
        $timestamp = time() - $lifeTime;

        return (filemtime($path) > $timestamp);
    }

    /**
     * Common templates for assets.
     *
     * @param string|array $files
     * @param string       $mode
     * @param bool         $fetch
     */
    protected function resource(array $files, $type, $fetch)
    {
        $result = '';

        // Adjust the files parameter.
        $files = is_array($files) ? $files : array($files);

        // Prepare the current template.
        $template = sprintf("%s\n", static::$templates[$type]);

        foreach ($files as $file) {
            if (empty($file)) continue;

            // Append the processed resource string to the result.
            $result .= sprintf($template, $file);
        }

        if ($fetch) {
            // Return the resulted string, with no output.
            return $result;
        }

        // Output the resulted string (and return null).
        echo $result;
    }

    protected function compress($buffer)
    {
        // Remove comments.
        $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);

        // Remove tabs, spaces, newlines, etc.
        $buffer = str_replace(array("\r\n","\r","\n","\t",'  ','    ','     '), '', $buffer);

        // Remove other spaces before/after ';'.
        $buffer = preg_replace(array('(( )+{)','({( )+)'), '{', $buffer);
        $buffer = preg_replace(array('(( )+})','(}( )+)','(;( )*})'), '}', $buffer);
        $buffer = preg_replace(array('(;( )+)','(( )+;)'), ';', $buffer);

        return $buffer;
    }

    protected function getFileName($name, $type)
    {
        return $this->basePath .$type .DS .$name .'.' .$type;
    }

}
