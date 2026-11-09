<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class SessionLoading
{
    public static function renderCifSessionLoading(): void
    {
        ?>
    <div id="session-loading" class="session-loading" role="status" aria-live="polite">
     <div class="session-loading-logo"><img src="<?php
        echo htmlspecialchars(Assets::cifAssetUrl('assets/apec-homes-logo.png'), ENT_QUOTES, 'UTF-8');
        ?>" alt="APEC Homes — Building Lives" width="1000" height="230" fetchpriority="high" decoding="sync"></div>
     <span class="session-loading-progress" aria-hidden="true"><span></span></span>
     <p class="session-loading-caption">Loading…</p>
    </div>
    <?php
    }
}
