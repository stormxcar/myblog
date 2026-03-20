<?php

/*
 * CKFinder
 * ========
 * https://ckeditor.com/ckfinder/
 * Copyright (c) 2007-2021, CKSource - Frederico Knabben. All rights reserved.
 *
 * The software, this file and its contents are subject to the CKFinder
 * License. Please read the license.txt file before using, installing, copying,
 * modifying or distribute this file or part of its contents. The contents of
 * this file is part of the Source Code of CKFinder.
 */

namespace CKSource\CKFinder\Filesystem\File;

use CKSource\CKFinder\Backend\Backend;
use CKSource\CKFinder\Cache\CacheManager;
use CKSource\CKFinder\CKFinder;
use CKSource\CKFinder\Config;
use CKSource\CKFinder\Filesystem\Path;

/**
 * The File class.
 *
 * Base class for processed files.
 */
abstract class File
{
    /**
     * Constant used to mark files without extension.
     */
    const NO_EXTENSION = 'NO_EXT';

    /**
     * File name.
     *
     * @var string
     */
    protected $fileName;

    /**
     * CKFinder configuration.
     *
     * @var Config
     */
    protected $config;

    /**
     * @var CKFinder
     */
    protected $app;

    /**
     * Constructor.
     *
     * @param string $fileName
     */
    public function __construct($fileName, CKFinder $app)
    {
        $this->fileName = $fileName;
        $this->config = $app['config'];
        $this->app = $app;
    }

    /**
     * Validates current file name.
     *
     * @return bool `true` if the file name is valid
     */
    public function hasValidFilename()
    {
        return static::isValidName($this->fileName, $this->config->get('disallowUnsafeCharacters'));
    }

    /**
     * Returns current file name.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->fileName;
    }

    /**
     * Returns current file extension.
     *
     * @return string
     */
    public function getExtension()
    {
        return strtolower(pathinfo($this->fileName, PATHINFO_EXTENSION));
    }

    /**
     * Returns a list of current file extensions.
     *
     * For example for a file named `file.foo.bar.baz` it will return an array containing
     * `['foo', 'bar', 'baz']`.
     *
     * @param null $newFileName the file name to check if it is different than the current file name (for example for validation of
     *                          a new file name in edited files)
     *
     * @return array
     */
    public function getExtensions($newFileName = null)
    {
        $fileName = $newFileName ?: $this->fileName;

        if (false === strpos($fileName, '.')) {
            return null;
        }

        $pieces = explode('.', $fileName);

        array_shift($pieces); // Remove file base name

        return array_map('strtolower', $pieces);
    }

    /**
     * Renames the current file by adding a number to the file name.
     *
     * Renaming is done by adding a number in parenthesis provided that the file name does
     * not collide with any other file existing in the target backend/path.
     * For example, if the target backend path contains a file named `foo.txt`
     * and the current file name is `foo.txt`, this method will change the current file
     * name to `foo(1).txt`.
     *
     * @param Backend $backend target backend
     * @param string  $path    target backend-relative path
     *
     * @return bool `true` if file was renamed
     */
    public function autorename(Backend $backend = null, $path = '')
    {
        $filePath = Path::combine($path, $this->fileName);

        if (!$backend->has($filePath)) {
            return false;
        }

        $pieces = explode('.', $this->fileName);
        $basename = array_shift($pieces);
        $extension = implode('.', $pieces);

        $i = 0;
        while (true) {
            ++$i;
            $this->fileName = "{$basename}({$i})".(!empty($extension) ? ".{$extension}" : '');

            $filePath = Path::combine($path, $this->fileName);

            if (!$backend->has($filePath)) {
                break;
            }
        }

        return true;
    }

    /**
     * Check whether `$fileName` is a valid file name. Returns `true` on success.
     *
     * @param string $fileName
     * @param bool   $disallowUnsafeCharacters
     *
     * @return bool `true` if `$fileName` is a valid file name
     */
    public static function isValidName($fileName, $disallowUnsafeCharacters = true)
    {
        if (null === $fileName || !\strlen(trim($fileName)) || '.' === substr($fileName, -1, 1) || false !== strpos($fileName, '..')) {
            return false;
        }

        if (preg_match(',[[:cntrl:]]|[/\\\\:\*\?\"\<\>\|],', $fileName)) {
            return false;
        }

        if ($disallowUnsafeCharacters) {
            if (false !== strpos($fileName, ';')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if the current file has an image extension.
     *
     * @return bool `true` if the file name has an image extension
     */
    public function isImage()
    {
        $imagesExtensions = ['gif', 'jpeg', 'jpg', 'png', 'psd', 'bmp', 'tiff', 'tif',
            'swc', 'iff', 'jpc', 'jp2', 'jpx', 'jb2', 'xbm', 'wbmp', ];

        return \in_array($this->getExtension(), $imagesExtensions, true);
    }

    /**
     * Secures the file name from unsafe characters.
     *
     * @param string $fileName
     * @param bool   $disallowUnsafeCharacters
     * @param mixed  $forceAscii
     *
     * @return string
     */
    public static function secureName($fileName, $disallowUnsafeCharacters = true, $forceAscii = false)
    {
        $fileName = str_replace([':', '*', '?', '|', '/'], '_', $fileName);

        if ($disallowUnsafeCharacters) {
            $fileName = str_replace(';', '_', $fileName);
        }

        if ($forceAscii) {
            $fileName = static::convertToAscii($fileName);
        }

        return $fileName;
    }

    /**
     * Replace accented UTF-8 characters with unaccented ASCII-7 "equivalents".
     * The purpose of this function is to replace characters commonly found in Latin
     * alphabets with something more or less equivalent from the ASCII range. This can
     * be useful for example for converting UTF-8 to something ready for a file name.
     * After the use of this function, you would probably also pass the string
     * through `utf8_strip_non_ascii` to clean out any other non-ASCII characters.
     *
     * For a more complete implementation of transliteration, see the `utf8_to_ascii` package
     * available from the phputf8 project downloads:
     * http://prdownloads.sourceforge.net/phputf8
     *
     * @param string $str
     *
     * @return string Accented chars replaced with ASCII equivalents
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @see http://sourceforge.net/projects/phputf8/
     */
    public static function convertToAscii($str)
    {
        static $utf8LowerAccents = null;
        static $utf8UpperAccents = null;

        if (null === $utf8LowerAccents) {
            $utf8LowerAccents = [
                '�' => 'a', '�' => 'o', 'd' => 'd', '?' => 'f', '�' => 'e', '�' => 's', 'o' => 'o',
                '�' => 'ss', 'a' => 'a', 'r' => 'r', '?' => 't', 'n' => 'n', 'a' => 'a', 'k' => 'k',
                's' => 's', '?' => 'y', 'n' => 'n', 'l' => 'l', 'h' => 'h', '?' => 'p', '�' => 'o',
                '�' => 'u', 'e' => 'e', '�' => 'e', '�' => 'c', '?' => 'w', 'c' => 'c', '�' => 'o',
                '?' => 's', '�' => 'o', 'g' => 'g', 't' => 't', '?' => 's', 'e' => 'e', 'c' => 'c',
                's' => 's', '�' => 'i', 'u' => 'u', 'c' => 'c', 'e' => 'e', 'w' => 'w', '?' => 't',
                'u' => 'u', 'c' => 'c', '�' => 'oe', '�' => 'e', 'y' => 'y', 'a' => 'a', 'l' => 'l',
                'u' => 'u', 'u' => 'u', 's' => 's', 'g' => 'g', 'l' => 'l', '�' => 'f', '�' => 'z',
                '?' => 'w', '?' => 'b', '�' => 'a', '�' => 'i', '�' => 'i', '?' => 'd', 't' => 't',
                'r' => 'r', '�' => 'ae', '�' => 'i', 'r' => 'r', '�' => 'e', '�' => 'ue', '�' => 'o',
                'e' => 'e', '�' => 'n', 'n' => 'n', 'h' => 'h', 'g' => 'g', 'd' => 'd', 'j' => 'j',
                '�' => 'y', 'u' => 'u', 'u' => 'u', 'u' => 'u', 't' => 't', '�' => 'y', 'o' => 'o',
                '�' => 'a', 'l' => 'l', '?' => 'w', 'z' => 'z', 'i' => 'i', '�' => 'a', 'g' => 'g',
                '?' => 'm', 'o' => 'o', 'i' => 'i', '�' => 'u', 'i' => 'i', 'z' => 'z', '�' => 'a',
                '�' => 'u', '�' => 'th', '�' => 'dh', '�' => 'ae', '�' => 'u', 'e' => 'e',
            ];
        }

        if (null === $utf8UpperAccents) {
            $utf8UpperAccents = [
                '�' => 'A', '�' => 'O', 'D' => 'D', '?' => 'F', '�' => 'E', '�' => 'S', 'O' => 'O',
                'A' => 'A', 'R' => 'R', '?' => 'T', 'N' => 'N', 'A' => 'A', 'K' => 'K',
                'S' => 'S', '?' => 'Y', 'N' => 'N', 'L' => 'L', 'H' => 'H', '?' => 'P', '�' => 'O',
                '�' => 'U', 'E' => 'E', '�' => 'E', '�' => 'C', '?' => 'W', 'C' => 'C', '�' => 'O',
                '?' => 'S', '�' => 'O', 'G' => 'G', 'T' => 'T', '?' => 'S', 'E' => 'E', 'C' => 'C',
                'S' => 'S', '�' => 'I', 'U' => 'U', 'C' => 'C', 'E' => 'E', 'W' => 'W', '?' => 'T',
                'U' => 'U', 'C' => 'C', '�' => 'Oe', '�' => 'E', 'Y' => 'Y', 'A' => 'A', 'L' => 'L',
                'U' => 'U', 'U' => 'U', 'S' => 'S', 'G' => 'G', 'L' => 'L', '�' => 'F', '�' => 'Z',
                '?' => 'W', '?' => 'B', '�' => 'A', '�' => 'I', '�' => 'I', '?' => 'D', 'T' => 'T',
                'R' => 'R', '�' => 'Ae', '�' => 'I', 'R' => 'R', '�' => 'E', '�' => 'Ue', '�' => 'O',
                'E' => 'E', '�' => 'N', 'N' => 'N', 'H' => 'H', 'G' => 'G', '�' => 'D', 'J' => 'J',
                '�' => 'Y', 'U' => 'U', 'U' => 'U', 'U' => 'U', 'T' => 'T', '�' => 'Y', 'O' => 'O',
                '�' => 'A', 'L' => 'L', '?' => 'W', 'Z' => 'Z', 'I' => 'I', '�' => 'A', 'G' => 'G',
                '?' => 'M', 'O' => 'O', 'I' => 'I', '�' => 'U', 'I' => 'I', 'Z' => 'Z', '�' => 'A',
                '�' => 'U', '�' => 'Th', '�' => 'Dh', '�' => 'Ae', 'E' => 'E',
            ];
        }

        $str = str_replace(array_keys($utf8LowerAccents), array_values($utf8LowerAccents), $str);

        $str = str_replace(array_keys($utf8UpperAccents), array_values($utf8UpperAccents), $str);

        return $str;
    }

    /**
     * @return CacheManager
     */
    public function getCache()
    {
        return $this->app['cache'];
    }
}
