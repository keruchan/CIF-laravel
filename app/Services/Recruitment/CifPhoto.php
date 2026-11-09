<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CifPhoto
{
    public static function cifPhotoSource(mixed $value): string
    {
        return is_string($value) && preg_match('/^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+\/=]+$/D', $value) ? $value : '';
    }

    public static function renderCifPhoto(string $attributes, mixed $value = ''): void
    {
        $photo = CifPhoto::cifPhotoSource($value);
        $escape = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        echo '<div class="cif-photo" data-photo-widget><input type="hidden"'.$attributes.' value="'.$escape($photo).'" data-photo-value>';
        echo '<div class="cif-photo-layout"><figure class="photo-guide"><img src="'.$escape(Assets::cifAssetUrl('assets/candidate-photo-example.jpg')).'" alt="Example framing for an upper-body candidate photo"><figcaption><strong>Photo guide</strong></figcaption></figure><div class="photo-upload-area"><img class="photo-preview" data-photo-preview alt="Candidate upper-body ID picture"'.($photo !== '' ? ' src="'.$escape($photo).'"' : ' hidden').'><div class="photo-action-row"><div class="photo-controls"><button type="button" data-photo-upload>Upload picture</button><button type="button" data-photo-camera>Use phone camera</button><button type="button" data-photo-remove'.($photo === '' ? ' hidden disabled' : '').'>Remove</button></div></div><p class="photo-help">Upload a clear JPEG, PNG or WebP photo. On supported phones, “Use phone camera” opens the camera.</p><p class="photo-framing-help">For best results, center your face and include your head and shoulders.</p>';
        echo '<input type="file" accept="image/jpeg,image/png,image/webp" data-photo-file hidden aria-label="Upload an upper-body ID picture"><input type="file" accept="image/jpeg,image/png,image/webp" capture="user" data-photo-capture hidden aria-label="Take an upper-body ID picture">';
        echo '<p data-photo-error role="alert" hidden></p></div></div></div>';
    }
}
