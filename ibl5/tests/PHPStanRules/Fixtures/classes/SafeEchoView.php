<?php

declare(strict_types=1);

$userName = 'alice';

?>
<html>
<body>
    <?= HtmlSanitizer::e($userName) ?>
</body>
</html>
