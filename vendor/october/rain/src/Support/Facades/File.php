<?php namespace October\Rain\Support\Facades;

use Illuminate\Support\Facades\File as FileBase;

/**
 * @method static bool isDirectoryEmpty(string $directory)
 * @method static string sizeToString(int $bytes)
 * @method static string localToPublic(string $path)
 * @method static string isLocalPath(string $path, bool $realpath)
 * @method static string fromClass(mixed $className)
 * @method static string|bool existsInsensitive(string $path)
 * @method static string symbolizePath(string $path, mixed $default)
 * @method static bool isPathSymbol(string $path)
 *
 * @see \October\Rain\Filesystem\Filesystem
 */
class File extends FileBase
{
    /**
     * Get the registered name of the component.
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'files';
    }
}
