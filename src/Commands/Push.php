<?php

namespace Ageras\LaravelOneSky\Commands;

use Ageras\LaravelOneSky\Exceptions\UnexpectedErrorWhileUploading;

class Push extends BaseCommand
{
    protected $formatArray = [
        'json' => 'HIERARCHICAL_JSON',
        'yml' => 'YAML',
        'txt' => 'TXT',
        'xml' => 'XML',
        'php' => 'PHP_SHORT_ARRAY',
    ];

    protected $signature = 'onesky:push {--project=}';

    protected $description = 'Push the language files to OneSky';

    public function handle()
    {
        $locale = $this->baseLocale();
        $translationsPath = $this->translationsPath() . DIRECTORY_SEPARATOR . $locale;

        $files = $this->scanDir($translationsPath);

        $files = array_map(function ($fileName) use (&$locale, &$translationsPath) {
            return $translationsPath . DIRECTORY_SEPARATOR . $fileName;
        }, $files);

        $this->uploadFiles(
            $this->client(),
            $this->project(),
            $locale,
            $files
        );

        $this->info('Files were uploaded successfully!');
    }

    /**
     * @param \OneSky\Api\Client $client
     * @param $project
     * @param $locale
     * @param array $files
     */
    public function uploadFiles($client, $project, $locale, array $files)
    {
        $data = $this->prepareUploadData($project, $locale, $files);

        foreach ($data as $d) {
            $client->files('upload', $d);
        }
    }

    public function uploadFile($client, $data)
    {
        $jsonResponse = $client->files('upload', $data);
        $jsonData = json_decode($jsonResponse, true);
        $responseStatus = $jsonData['meta']['status'];

        if ($responseStatus !== 201) {
            throw new UnexpectedErrorWhileUploading(
                'Upload response status: ' . $responseStatus
            );
        }
    }

    public function prepareUploadData($project, $locale, array $files)
    {
        $data = [];
        foreach ($files as $file) {
            $data[] = [
                'project_id'  => $project,
                'file'        => $file,
                'file_format' => $this->getFileFormat($file),
                'locale'      => $locale,
            ];
        }

        return $data;
    }

    public function getFileFormat($file)
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if (array_key_exists($extension, $this->formatArray)) {
            return $this->formatArray[$extension];
        } else {
            throw new UnexpectedErrorWhileUploading(
                'Unkown file format: ' . $file
            );
        }
    }
}
