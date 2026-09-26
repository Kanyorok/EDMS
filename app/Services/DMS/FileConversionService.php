<?php

namespace App\Services\DMS;

use App\Enums\Core\ExtensionsEnum;
use App\Exceptions\ErroredException;
use App\Helpers\SystemHelper;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentVersion;
use RuntimeException;
use Storage;

class FileConversionService
{
    protected ExtensionsEnum $extension;

    public function __construct(protected Document $document)
    {
        $ex = $this->document->ext();
        if (! $ex instanceof ExtensionsEnum) {
            throw new RuntimeException('Invalid file extension');
        }
        $this->extension = $ex;
    }

    /**
     * Returns a path to the converted PDF file
     * @throws ErroredException
     */
    public function convertToPDF(?DocumentVersion $version = null): ?string
    {
        $service = new DocumentService($this->document);
        $targetExtension = ExtensionsEnum::Pdf;
        $extension = $version instanceof DocumentVersion ? $service->typeOf($version) : $this->extension;
        if ($extension === $targetExtension) {
            return $service->getTempPath($version);
        }

        if (self::isInstalled()) {
            return $this->_convertToPDf($version);
        }

        return null;
    }

    public static function isInstalled(): bool
    {
        if (shell_exec("command -v soffice") === null) {
            SystemHelper::notifyAdmin('libreoffice is not found. Please install libreoffice package.');

            return false;
        }

        return true;
    }

    /**
     * Returns a path to the converted PDF file
     * @throws ErroredException
     */
    private function _convertToPDf(?DocumentVersion $version = null): ?string
    {
        $fileToConvert = (new DocumentService($this->document))->getTempPath($version);
        if ($fileToConvert === null || ! file_exists($fileToConvert)) {
            return null;
        }
        $path = Storage::disk('temp')->path('');
        shell_exec('soffice --headless --convert-to pdf --outdir ' . escapeshellarg($path) . ' ' . escapeshellarg($fileToConvert));

        $info = pathinfo($fileToConvert);
        $convertedFile = $path . $info['filename'] . '.pdf';
        if (! file_exists($convertedFile)) {
            return null;
        }

        return $convertedFile;
    }
}
