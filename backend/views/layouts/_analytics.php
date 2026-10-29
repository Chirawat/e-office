<?php

declare(strict_types=1);

use yii\helpers\Html;

$measurementId = trim((string) (Yii::$app->params['ga4MeasurementId'] ?? 'G-1PZFJ3EKTZ'));
if ($measurementId === '') {
    $measurementId = 'G-1PZFJ3EKTZ';
}
if ($measurementId === '' || !preg_match('/^G-[A-Z0-9]+$/', $measurementId)) {
    return;
}

$encodedMeasurementId = Html::encode($measurementId);
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $encodedMeasurementId ?>"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?= $encodedMeasurementId ?>');
</script>
