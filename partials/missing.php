<?php

declare(strict_types=1);
?>
<section class="page-banner">
    <img class="page-banner-photo" src="<?= asset('assets/images/banner.jpg') ?>" alt="" loading="eager" decoding="async">
    <div class="page-banner-shade"></div>
    <div class="container page-banner-copy">
        <p class="crumbs"><a href="<?= url('/') ?>">Ana Sayfa</a></p>
        <h1>Sayfa bulunamadı</h1>
    </div>
</section>
<div class="page-canvas">
    <div class="container missing">
        <p>Aradığınız sayfa yayında değil ya da taşınmış olabilir.</p>
        <a class="btn btn-gold" href="<?= url('/') ?>">Ana sayfaya dön</a>
    </div>
</div>
