<?php

namespace App\Traits\Model;

use App\Enums\Core\ExtensionsEnum;
use App\Exceptions\ErroredException;
use App\Models\Auth\User;
use App\Models\DMS\Image;
use App\Services\DMS\ImageService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Exceptions\DriverException;
use Intervention\Image\ImageManager;
use RuntimeException;

trait ImageTrait
{
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'ImageId', 'ImageID');
    }

    public function getImage(string $attr = '', bool $placeholder = true, string $ImageRelationFn = 'photo'): string
    {
        if (! method_exists($this, $ImageRelationFn)) {
            throw new RuntimeException('Could not find the image');
        }

        $photo = $this->$ImageRelationFn;
        if ($photo instanceof Image) {
            $service = new ImageService($photo);
            if ($service->isPrevieable()) {
                return $service->preview($attr);
            }
        }

        /* if($placeholder){
             return  '<div '.$attr.'><div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center w-100 h-100"
                                  style="margin: 0 auto;">
                  <span>' . mb_substr($this->getImageName(), 0, 2) . '</span></div></div>';
         }
         return '';*/
        return ($placeholder) ? '<img src="https://placehold.co/200x200?font=roboto&text=No+Image" ' . $attr . '/>' : '';
    }

    abstract protected function getImageName(): string;

    /**
     * @throws ErroredException
     */
    public function setImage(UploadedFile $file, User $actor, string $field = null): static
    {
        if (is_string($field) && ! in_array($field, $this->fillable, true)) {
            throw new RuntimeException('Invalid field');
        }

        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            Log::error('No image driver available');

            throw new ErroredException('No image driver available');
        }

        try {
            $ext = ExtensionsEnum::fromMimeType($file->getMimeType());
            if (! $ext->isImage()) {
                throw new ErroredException('File must be an image');
            }
        } catch (ErroredException) {
            Log::error('Invalid file type uploaded: ' . $file->getMimeType());

            throw new ErroredException('File must be an image');
        }

        try {
            $manager = new ImageManager(new GdDriver());
        } catch (DriverException) {
            try {
                $manager = new ImageManager(new ImagickDriver());
            } catch (DriverException) {
                Log::error('No image driver available');

                throw new ErroredException('No image driver available');
            }
        }

        $content = $manager->read($file)->cover(100, 100)->encodeByMediaType('image/webp', quality: 80)->toString();

        return $this->_setImage(ImageService::createContent($content, $this->primaryKey, $this->{$this->primaryKey}, 'image/webp', $file->getClientOriginalName(), $actor), $field);
    }

    public function setFromContent(string $content, User $actor, string $mimeType, string $fileName, string $field = null): static
    {
        if (is_string($field) && ! in_array($field, $this->fillable, true)) {
            throw new RuntimeException('Invalid field');
        }

        return $this->_setImage(ImageService::createContent($content, $this->primaryKey, $this->{$this->primaryKey}, $mimeType, $fileName, $actor), $field);
    }

    protected function _setImage(ImageService $service, string $field = null): static
    {
        if (is_string($field)) {
            $this->update([
                $field => $service->image->ImageID,
            ]);
        }

        return $this;
    }

    public function setAvatarFromURL(string $url, User $actor, string $field = null): static
    {
        if (is_string($field) && ! in_array($field, $this->fillable, true)) {
            throw new RuntimeException('Invalid field');
        }

        return $this->_setImage(ImageService::createURL($url, $this->primaryKey, $this->{$this->primaryKey}, $actor, ExtensionsEnum::Jpeg->getMimeType()), $field);
    }
}
