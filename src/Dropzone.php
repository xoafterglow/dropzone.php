<?php

namespace Dilab;

use League\Flysystem\FileExistsException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class Dropzone
{
    const DS = DIRECTORY_SEPARATOR;

    /**
     * @var  Filesystem
     */
    private $filesystem;

    private $name;

    private $metaOption = [
        'dzuuid' => 'dzuuid',
        'dzchunkindex' => 'dzchunkindex',
        'dztotalfilesize' => 'dztotalfilesize',
        'dzchunksize' => 'dzchunksize',
        'dztotalchunkcount' => 'dztotalchunkcount',
        'dzchunkbyteoffset' => 'dzchunkbyteoffset'
    ];

    private $tmpDir = 'tmp';

    private $uploadDir = 'upload';

    private $root;

    /**
     * Dropzone constructor.
     * @param Filesystem $filesystem
     */
    public function __construct($root)
    {
        $this->name = time();

        $this->root = $root;
        
        $adapter = new LocalFilesystemAdapter(
        // Determine root directory
             $root
        );

        $this->filesystem = new Filesystem($adapter);
    }

    public function upload($stream, $meta)
    {
        if ($this->isAlreadyUploaded($meta)) {
            return $this;
        }

        $this->uploadToTmp($stream, $meta);

        if (!$this->isUploadCompleted($meta)) {
            return $this;
        }

        $this->assemble($meta);

        return $this;
    }

    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param $meta
     * @return boolean
     */
    private function isAlreadyUploaded($meta)
    {
        return $this->filesystem->fileExists($this->tmpFilePath($meta));
    }

    private function uploadToTmp($stream, $meta)
    {
        try {

            $this->filesystem->writeStream($this->tmpFilePath($meta), $stream);

        } catch (FileExistsException $exception) {

            return;
        }
    }

    /**
     * @param $meta
     * @return boolean
     */
    private function isUploadCompleted($meta)
    {
        return intval($meta[$this->metaOption['dzchunkindex']]) +1 == intval($meta[$this->metaOption['dztotalchunkcount']]);
    }

    private function assemble($meta)
    {
        $contents = $this->filesystem->listContents($this->tmpDirPath($meta), true);

        foreach ($contents as $object) {

            $stream = $this->filesystem->readStream($object['path']);

            try {

                file_put_contents($this->root . self::DS . $this->uploadFilePath($meta), $stream,
                    FILE_APPEND | LOCK_EX);

            } catch (FileExistsException $exception) {

                var_dump($exception->getMessage());

                return;

            } finally {

                fclose($stream);

            }

        }

    }


    private function tmpFilePath($meta)
    {
        return $this->tmpDirPath($meta) . self::DS .
            $meta[$this->metaOption['dzchunkindex']];
    }

    private function tmpDirPath($meta)
    {
        return $this->tmpDir . self::DS .
            $meta[$this->metaOption['dzuuid']];
    }

    private function uploadFilePath($meta)
    {
        $uploadFolder = $this->uploadDir . self::DS . $meta[$this->metaOption['dzuuid']];

        if (!$this->filesystem->directoryExists($uploadFolder)) {
            $this->filesystem->createDirectory($uploadFolder);
        };

        return $uploadFolder . self::DS . $this->name;
    }

}
