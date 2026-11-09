<?php
declare(strict_types=1);




$portals = [
    [
        'name'=>'HR Recruitment', 'audience'=>'For the HR team', 'icon'=>'candidates',
        'href'=>'../admin/', 'style'=>'hr',
        'description'=>'Manage candidates and keep recruitment moving.',
        'features'=>['Candidates and hiring', 'Manpower requests', 'Hiring reports'],
    ],
    [
        'name'=>'MRF Requestor', 'audience'=>'For manpower requestors', 'icon'=>'manpower',
        'href'=>'../mrf-requestor/', 'style'=>'requestor',
        'description'=>'Request the people your team needs.',
        'features'=>['Submit manpower requests', 'Track request progress', 'View HR updates'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="description" content="Choose your APEC Homes portal for HR recruitment or manpower requests.">
 <title>Recruitment Portal | APEC Homes</title>
 <?php \App\Services\Recruitment\Branding::renderCifBrandHead(); ?>
 <link rel="stylesheet" href="<?= htmlspecialchars(\App\Services\Recruitment\Assets::cifAssetUrl('css/portal.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
 <a class="skip-link" href="#portal-choices">Skip to portal choices</a>
 <header class="site-header">
  <div class="header-content">
   <?php \App\Services\Recruitment\Branding::renderCifBrandLogo('portal-company-logo'); ?>
   <span class="header-caption">Recruitment Management</span>
  </div>
 </header>
 <main class="portal-main">
  <section class="portal-welcome" aria-labelledby="portal-title">
   <p class="welcome-eyebrow">Welcome to APEC Homes</p>
   <h1 id="portal-title">Recruitment Portal</h1>
   <p class="welcome-description">One place for our people and hiring needs.<br>Choose your portal to sign in.</p>
   <div class="welcome-pattern" aria-hidden="true"><span></span><span></span><span></span></div>
  </section>
  <nav class="portal-choices" id="portal-choices" aria-label="Choose your portal" tabindex="-1">
   <?php foreach ($portals as $portal): ?>
   <a class="portal-card portal-card--<?= $portal['style'] ?>" href="<?= $portal['href'] ?>" aria-labelledby="portal-<?= $portal['style'] ?>-title">
    <span class="portal-card-icon"><?= \App\Services\Recruitment\PortalIcons::portalIcon($portal['icon']) ?></span>
    <p class="portal-audience"><?= $portal['audience'] ?></p>
    <h2 id="portal-<?= $portal['style'] ?>-title"><?= $portal['name'] ?></h2>
    <p class="portal-description"><?= $portal['description'] ?></p>
    <ul class="portal-features">
     <?php foreach ($portal['features'] as $feature): ?><li><?= \App\Services\Recruitment\PortalIcons::portalIcon('check') ?><span><?= $feature ?></span></li><?php endforeach; ?>
    </ul>
    <span class="portal-card-action">Open portal <span aria-hidden="true">&rarr;</span></span>
   </a>
   <?php endforeach; ?>
  </nav>
  <p class="portal-sign-in-note">Sign in with the account assigned to you.</p>
 </main>
 <footer class="site-footer"><span>APEC Homes</span><span>Building Lives</span></footer>
</body>
</html>
