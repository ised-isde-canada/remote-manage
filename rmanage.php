<?php

// Get the main operation
$operation = $_REQUEST['operation'] ?? '';

// Get the options and assemble into an array
$options = [];

if (isset($_REQUEST['verbose']) && $_REQUEST['verbose'] == 'true') {
    $options[] = '--verbose --log-stderr';
}

if (isset($_REQUEST['format'])) {
    $options[] = '--format=' . $_REQUEST['format'];
}

$job = (isset($_REQUEST['job']) && $_REQUEST['job'] == 'true') ? getmypid() : null;

$json = [];

// Assemble the basic command to run
$cmd = 'php ' . dirname(__FILE__) . '/manage.php';

if ($options) {
    $cmd .= ' ' . join(' ', $options);
}

switch ($operation) {
    case 'backup':
        getS3Credentials();
        if ($job) {
            `$cmd backup > /tmp/rmanage_$job.log &`;
            $json = ['status' => 'ok', 'job' => $job];
        } else {
            $json = getJSONResult(`$cmd backup`);
        }
        break;

    case 'restore':
        if (empty($_REQUEST['s3file'])) {
            $json = ['result' => 'error', 'message' => 'Missing s3file'];
            break;
        }
        getS3Credentials();
        $s3file = $_REQUEST['s3file'];
        if ($job) {
            `$cmd restore $s3file > /tmp/rmanage_$job.log &`;
            $json = ['status' => 'ok', 'job' => $job];
        } else {
            $json = getJSONResult(`$cmd restore $s3file`);
        }
        break;

    case 'query':
        $job = $_REQUEST['job'];
        $json = @file_get_contents("/tmp/rmanage_$job.log");
        if (empty($json)) {
            $json = ['result' => 'error', 'message' => 'Log file is missing.'];
        } else {
            $json = getJSONResult($json);
        }
        break;

    case 'maint':
        $mode = $_REQUEST['mode'] ?? '';
        $json = getJSONResult(`$cmd maint $mode`);
        break;

    case 's3list':
        getS3Credentials();
        $json = getJSONResult(`$cmd s3list`);
        break;

    case 'pmlist':
        $json = getJSONResult(`$cmd pmlist`);
        break;

    case 'space':
        $json = getJSONResult(`$cmd space`);
        break;

    default:
        $json = ['status' => 'error', 'message' => "Unknown operation $operation"];
}

/**
 * If S3 credentials are provided as POST variables then set then as environment variables, which will pass through
 * to the manage script.
 * We don't check for missing or invalid credentials at this stage, because they may already be set on the host.
 */
function getS3Credentials()
{
    foreach (['aws_access_key_id', 'aws_secret_access_key', 'aws_s3_bucket', 'aws_s3_region'] as $var) {
        if (isset($_POST[$var])) {
            putenv(strtoupper($var) . '=' . $_POST[$var]);
        }
    }
}

/**
 * Parse the result from the remote-manage command, separate into messages and data and return a JSON structure.
 * @param string $result
 * @return mixed[]
 */
function getJSONResult($result)
{
    $result = trim($result);
    if ($result[0] == '[' OR $result[0] == '{') {
        return json_decode($result);
    }
    $messages = [];
    $jsonData = '';
    $inJson = false;
    foreach (explode("\n", $result) as $rec) {
        if ($inJson) {
            $jsonData .= "$rec\n";
        } else {
            if ($rec == 'DATA:') {
                $inJson = true;
                continue;
            }
            $messages[] = $rec;
        }
    }
    $json = $jsonData ? json_decode($jsonData) : (object) [];
    if ($messages) {
        $json->messages = $messages;
    }
    return $json;
}

// Exit with a JSON result

header('Content-type: application/json');
echo json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL;
