<?php

namespace SilverStripe\Core\Manifest;

use InvalidArgumentException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * Helper for mapping module resources to paths / urls
 */
class ModuleResourceLoader implements TemplateGlobalProvider
{
    use Injectable;

    /**
     * Convert a file of the form "vendor/package:resource" into a BASE_PATH-relative file
     * For other files, return original value
     *
     * @param string $resource
     *
     * @return string
     */
    public function resolvePath($resource)
    {
        // Skip blank resources
        if (empty($resource)) {
            return null;
        }
        $resourceObj = $this->resolveResource($resource);
        if ($resourceObj instanceof ModuleResource) {
            return $resourceObj->getRelativePath();
        }
        return $resource;
    }

    /**
     * Resolves resource specifier to the given url.
     *
     * @param string $resource
     *
     * @return string
     */
    public function resolveURL($resource)
    {
        // Skip blank resources
        if (empty($resource)) {
            return null;
        }

        // Resolve resource to reference
        $resource = $this->resolveResource($resource);

        // Resolve resource to url
        /** @var ResourceURLGenerator $generator */
        $generator = Injector::inst()->get(ResourceURLGenerator::class);
        return $generator->urlForResource($resource);
    }

    /**
     * Template wrapper for resolvePath
     *
     * @param string $resource
     *
     * @return string
     */
    public static function resourcePath($resource)
    {
        return static::singleton()->resolvePath($resource);
    }

    /**
     * Template wrapper for resolveURL
     *
     * @param string $resource
     *
     * @return string
     */
    public static function resourceURL($resource)
    {
        return static::singleton()->resolveURL($resource);
    }

    public static function get_template_global_variables()
    {
        return [
            'resourcePath',
            'resourceURL'
        ];
    }

    /**
     * Return module resource for the given path, if specified as one.
     * Returns the original resource otherwise.
     *
     * @param string $resource
     *
     * @return ModuleResource|string The resource, or input string if not a module resource
     */
    public function resolveResource($resource)
    {
        // String of the form vendor/package:resource. Excludes "http://bla" as that's an absolute URL
        if (!preg_match('#^ *(?<module>[^/: ]+/[^/: ]+) *: *(?<resource>[^ ]*)$#', $resource, $matches)) {
            return $resource;
        }
        $module = $matches['module'];
        $resource = $matches['resource'];
        $moduleObj = ModuleLoader::getModule($module);
        if (!$moduleObj) {
            $append = '';
            if (!$this->hasModuleComposerConfig($module)) {
                $append = ", the composer.json file appears to be missing.";
            }
            throw new InvalidArgumentException("Can't find module '$module'$append");
        }
        $resourceObj = $moduleObj->getResource($resource);

        return $resourceObj;
    }

    /**
     * Checks to see if the provided module ("vendor/package") has a composer.json file.
     *
     * @param $module
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function hasModuleComposerConfig($module)
    {
        list($vendor, $package) = explode('/', $module);

        if (!$vendor || !$package) {
            throw new \Exception("$module is not in the format of vendor/package");
        }

        $vendorPath = Controller::join_links(Director::baseFolder(), 'vendor', $vendor, $package, 'composer.json');
        $rootPath = Controller::join_links(Director::baseFolder(), $package, 'composer.json');

        if (!is_dir(dirname($vendorPath)) && !is_dir(dirname($rootPath))) {
            return false;
        }

        if (!file_exists($vendorPath) && !file_exists($rootPath)) {
            return false;
        }

        return true;
    }
}
