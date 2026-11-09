<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class PortalIcons
{
    // Local outline icons: consistent stroke, no icon font or network dependency.
    public static function portalIcon(string $name): string
    {
        $icons = ['calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2M8 18h2"/>', 'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>', 'candidates' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5.65"/>', 'invitations' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>', 'email-templates' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5M8 18h3"/>', 'mail-management' => '<path d="M3 8h18v12H3zM3 8l3-5h12l3 5M3 14h5l2 3h4l2-3h5"/>', 'email-settings' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7M8 22h8"/>', 'manpower' => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M10 11h5M10 16h5M8 11h.01M8 16h.01"/>', 'interview' => '<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H3l2.3-4A8.5 8.5 0 1 1 21 11.5Z"/><circle cx="12.5" cy="9" r="2"/><path d="M8.5 16a4 4 0 0 1 8 0"/>', 'cif' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 12h8M8 16h8M8 19h5"/>', 'screening' => '<path d="M12 3 4 6v5c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V6Z"/><path d="M7 12h3l1.5-4 2.5 8 1.5-4H18"/>', 'job-offer' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 12h8M8 16h3m2 3 2 2 4-5"/>', 'deployment' => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12a22 22 0 0 0 18 0M12 11v4"/>', 'check' => '<path d="m5 12 4 4L19 6"/>', 'exam' => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m8 12 2 2 4-4M8 17h8"/>', 'refresh' => '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 6.1a8 8 0 0 1 13.3 3M17.9 17.9a8 8 0 0 1-13.3-3"/>', 'hr-staff' => '<circle cx="8" cy="6" r="3"/><path d="M2 19v-3a6 6 0 0 1 12 0v3M15 3a3 3 0 0 1 0 6M17 20v-5m4 5v-9"/>', 'settings' => '<path d="m9 3 .6-1h4.8l.6 1 .4 2 1.8 1 2-.6 1.8 3.2-1.5 1.4v2l1.5 1.4-1.8 3.2-2-.6-1.8 1-.4 2-.6 1H9l-.6-1-.4-2-1.8-1-2 .6-1.8-3.2 1.5-1.4v-2L2.4 8.6l1.8-3.2 2 .6L8 5l.4-2Z"/><circle cx="12" cy="11" r="3"/>', 'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M9 12h12m-4-4 4 4-4 4"/>', 'menu' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>'];
        if (! isset($icons[$name])) {
            throw new \InvalidArgumentException('Unknown portal icon.');
        }

        return '<svg class="portal-icon" data-icon="'.$name.'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$icons[$name].'</svg>';
    }
}
