<?php

namespace RemoteManage;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * This class is a wrapper for the s3cmd tool.
 */
class S3Cmd
{
    private $s3_bucket = '';        // Name of S3 bucket containing backup files
    private $s3_region = '';        // Region of S3 bucket
    private $s3 = null;             // S3 client

    public function __construct()
    {
        $error = false;

        if (!getenv('AWS_ACCESS_KEY_ID')) {
            Log::msg('AWS_ACCESS_KEY_ID environment variable is not set.');
            $error = true;
        }
        if (!getenv('AWS_SECRET_ACCESS_KEY')) {
            Log::msg('AWS_SECRET_ACCESS_KEY environment variable is not set.');
            $error = true;
        }
        if (! $this->s3_bucket = getenv('AWS_S3_BUCKET')) {
            Log::msg('AWS_S3_BUCKET environment variable is not set.');
            $error = true;
        }
        if (! $this->s3_region = getenv('AWS_S3_REGION')) {
            Log::msg('AWS_S3_REGION environment variable is not set.');
            $error = true;
        }
        Log::msg("s3_bucket is $s3_bucket");
        Log::msg("s3_region is $s3_region");

        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => $s3_region,
        ]);

        return !$error;
    }

    function getList()
    {
        try {
            $result = $this->s3->listObjectsV2([
                'Bucket' => $this->s3_bucket
            ]);
        }
        catch(S3Exception $e) {
            Log::msg('S3 Exception on listObjectsV2!');
            return false;
        }

        for ($n = 0; $n <sizeof($result['Contents']); $n++) {
            Log::msg($result['Contents'][$n]['Key']);
//             $result['Contents'][$n]['Key']
//             $result['Contents'][$n]['Size']
//             $result['Contents'][$n]['LastModified']
        }

        return true;
    }

    function copy($backupFile) {
        try {
            $result = $this->s3->putObject([
                'Bucket' => $this->s3_bucket,
                'Key' => $backupFile,
            ]);
        }
        catch(S3Exception $e) {
            Log::msg('S3 Exception on putObject!');
            return false;
        }

        return true;
    }

}
