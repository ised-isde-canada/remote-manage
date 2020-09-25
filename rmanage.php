<?php

$operation = $_REQUEST['op'] ?? '';
$json = [];
$result = ''; // Pre-formed JSON result

switch ($operation) {
    case 'backup':
        getS3Credentials();
        $job = startJob('backup');
        $json = [
            'status' => 'job started',
            'job' => $job
        ];
        break;

    case 'restore':
        getS3Credentials();
        $job = startJob('restore');
        $json = [
            'status' => 'job started',
            'job' => $job
        ];
        break;

    case 's3list':
        $result = `/usr/bin/php /opt/app-root/src/vendor/ised/remote-manage/manage.php --s3list`;
        break;

    case 'query':
        $job = $_REQUEST['job'];
        $json = ['result' => file("/tmp/backup_$job.log")];
        break;
}

function getS3Credentials()
{
    foreach (['aws_access_key_id', 'aws_secret_access_key', 'aws_s3_bucket', 'aws_s3_region'] as $var) {
        if (isset($_POST[$var])) {
            putenv(strtoupper($var) . '=' . $_POST[$var]);
        }
    }
}

function startJob($operation)
{
    $job = getmypid();

    `/usr/bin/php /opt/app-root/src/vendor/ised/remote-manage/manage.php --$operation --verbose > /tmp/backup_$job.log &`;

    return $job;
}

header('Content-type: application/json');
if ($result) {
    echo $result;
} else {
    echo json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
}
