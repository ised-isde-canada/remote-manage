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
    private $error = false;         // Error flag

    public function __construct()
    {
        if (!getenv('AWS_ACCESS_KEY_ID')) {
            $this->error = true;
            Log::msg('AWS_ACCESS_KEY_ID environment variable is not set.');
        }
        if (!getenv('AWS_SECRET_ACCESS_KEY')) {
            $this->error = true;
            Log::msg('AWS_SECRET_ACCESS_KEY environment variable is not set.');
        }
        if (! $this->s3_bucket = getenv('AWS_S3_BUCKET')) {
            $this->error = true;
            Log::msg('AWS_S3_BUCKET environment variable is not set.');
        }
        if (! $this->s3_region = getenv('AWS_S3_REGION')) {
            $this->error = true;
            Log::msg('AWS_S3_REGION environment variable is not set.');
        }
        Log::msg("s3_bucket is $this->s3_bucket");
        Log::msg("s3_region is $this->s3_region");

        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => $this->s3_region,
        ]);
    }

    public function getList()
    {
        if(!$this->error) {
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
        } else {
            Log::msg('Unable to execute getList() - error flagged on S3Cmd::__construct');
            return false;
        }

        return true;
    }

    public function copy($filename, $body) 
    {
        if(!$this->error) {
            try {
                $result = $this->s3->putObject([
                    'Bucket' => $this->s3_bucket,
                    'Key'    => $filename,
                    'Body'   => $body,
                    'ACL'    => 'private',
                ]);
            }
            catch(S3Exception $e) {
                Log::msg('S3Exception on putObject!');
                return false;
            }
        } else {
            Log::msg('Unable to execute copy() - error flagged on S3Cmd::__construct');
            return false;           
        }
        return true;
    }

    public function getFile($filename, $path)
    {
        if(!$this->error) {
            try {
                $result = $this->s3->getObject([
                    'Bucket' => $this->s3_bucket,
                    'Key'    => $filename,
                    'SaveAs' => $path,
                ]);
            }
            catch(S3Exception $e) {
                Log::msg('S3Exception on getObject!');
                return false;
            }
        } else {
            Log::msg('Unable to execute getFile() - error flagged on S3Cmd::__construct');
            return false;
        }
        return true;
    }
}
