<?php

class ParsedownExtraTest extends ParsedownTest
{
    protected function initDirs()
    {
        $dirs = parent::initDirs();
        $dirs[] = __DIR__ . '/data/';
        return $dirs;
    }

    function data()
    {
        $data = array();

        foreach ($this->initDirs() as $i => $dir)
        {
            $newData = $this->dataFromDirectory($dir);

            if ($i < 1)
            {
                # Parsedown-Extra has different treatment of HTML
                $newData = array_filter($newData, function ($s) { return strpos($s[0], 'markup') === false; });
                $newData = array_filter($newData, function ($s) { return strpos($s[0], 'html') === false; });
            }

            $data = array_merge($data, $newData);
        }

        return $data;
    }

    function dataFromDirectory($dir)
    {
        $data = array();

        $Folder = new DirectoryIterator($dir);

        foreach ($Folder as $File)
        {
            /** @var $File DirectoryIterator */

            if ( ! $File->isFile())
            {
                continue;
            }

            $filename = $File->getFilename();

            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            if ($extension !== 'md')
            {
                continue;
            }

            $basename = $File->getBasename('.md');

            if (file_exists($dir . $basename . '.html'))
            {
                $data []= array($basename, $dir);
            }
        }

        return $data;
    }
}
