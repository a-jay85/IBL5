<?php
// Copy to github-dispatch.config.php on the production box. Never commit the copy.
// token: fine-grained PAT, single repo a-jay85/IBL5, permission Contents: read and write
//        (repository_dispatch needs it). Empty token = dispatch disabled; the hourly
//        schedule in .github/workflows/sim-recap.yml still drains the queue.
return [
    'token'      => '',
    'repo'       => 'a-jay85/IBL5',
    'event_type' => 'sim-recap',
];
