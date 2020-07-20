<?php

namespace RemoteManage;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * This class is a wrapper for the s3cmd tool.
 */
class S3Cmd
{
    function put()
    {
        putenv("AWS_ACCESS_KEY_ID=$s3_access_key");
        putenv("AWS_SECRET_ACCESS_KEY=$s3_secret_key");

        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => 'ca-central-1',
        ]);

        try {
            $result = $s3->listObjectsV2([
                'Bucket' => $s3_host_bucket,
            ]);
        }
        catch(S3Exception $e) {
        }

        for ($n = 0; $n <sizeof($result['Contents']); $n++) {
//             $result['Contents'][$n]['Key']
//             $result['Contents'][$n]['Size']
//             $result['Contents'][$n]['LastModified']
        }
    }
}
