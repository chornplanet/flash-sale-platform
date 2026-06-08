<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

class WindowsFilesystem extends Filesystem
{
    /**
     * Windows security tooling can block PHP's temp-file rename flow while
     * still allowing direct writes. Use a direct locked write for local dev.
     */
    public function replace($path, $content, $mode = null)
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        file_put_contents($path, $content, LOCK_EX);

        if (! is_null($mode)) {
            @chmod($path, $mode);
        }
    }
}
