<?php

namespace RemoteManage;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * This class is a wrapper for the s3cmd tool.
 */
class S3Cmd
{
    function getList()
    {
        $error = false;

        if (!getenv('AWS_ACCESS_KEY_ID')) {
            Log::msg('AWS_ACCESS_KEY_ID environemnt variable is not set.');
            $error = true;
        }
        if (!getenv('AWS_SECRET_ACCESS_KEY')) {
            Log::msg('AWS_SECRET_ACCESS_KEY environemnt variable is not set.');
            $error = true;
        }
        if (! $s3_bucket = getenv('AWS_S3_BUCKET')) {
            Log::msg('AWS_S3_BUCKET environemnt variable is not set.');
            $error = true;
        }
        if (! $s3_region = getenv('AWS_S3_REGION')) {
            Log::msg('AWS_S3_REGION environemnt variable is not set.');
            $error = true;
        }

        if ($error) {
            return false;
        }

        Log::msg("s3_region is $s3_region");

        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => $s3_region,
        ]);

        try {
            $result = $s3->listObjectsV2([
                'Bucket' => $s3_bucket
            ]);
        }
        catch(S3Exception $e) {
            Log::msg('S3 Exception!');
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
}
