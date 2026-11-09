<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class Branding
{
    public static function renderCifBrandHead(): void
    {
        ?>
    <link rel="icon" type="image/svg+xml" sizes="any" href="<?php
        echo htmlspecialchars(Assets::cifAssetUrl('assets/apec-favicon.svg'), ENT_QUOTES, 'UTF-8');
        ?>">
    <link rel="stylesheet" href="<?php
        echo htmlspecialchars(Assets::cifAssetUrl('css/branding.css'), ENT_QUOTES, 'UTF-8');
        ?>">
    <?php
    }

    public static function renderCifBrandLogo(string $class = ''): void
    {
        ?><img class="brand-logo <?php
        echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        ?>" src="<?php
        echo htmlspecialchars(Assets::cifAssetUrl('assets/apec-homes-logo.png'), ENT_QUOTES, 'UTF-8');
        ?>" width="1000" height="230" alt="APEC Homes — Building Lives"><?php
    }
}
