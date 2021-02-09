<?php

namespace RemoteManage;

use Aws\S3\S3Client;
use Aws\AwsClientInterface;
use Aws\S3\Exception\S3Exception;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;


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
        // Check to make sure we have S3 credentials available
        $this->error = !self::checkCredentials();

        if (!$this->error) {
            $this->s3_bucket = getenv('AWS_S3_BUCKET');
            $this->s3_region = getenv('AWS_S3_REGION');
            Log::msg('S3 bucket is: ' . $this->s3_bucket . '.' . $this->s3_region);

            $this->s3 = new S3Client([
                'version' => 'latest',
                'region'  => $this->s3_region
            ]);
        }
    }

    /**
     * Check to ensure that all AWS credentials are set as environment variables.
     */
    public static function checkCredentials()
    {
        static $success = -99;
        if ($success == -99) {
            $success = true;
            foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_S3_BUCKET', 'AWS_S3_REGION'] as $evar) {
                if (empty(getenv($evar))) {
                    Log::error("$evar missing.");
                    $success = false;
                }
            }
        }
        return $success;
    }

    public function getList($filter = '')
    {
        if (!self::checkCredentials()) {
            return false;
        }

        $files = [];
        $params = ['Bucket' => $this->s3_bucket];
        do {
            try {
                $data = $this->s3->listObjectsV2($params);
            }
            catch (S3Exception $e) {
                Log::error($e->getMessage());
                Log::msg('S3 Exception on listObjectsV2!');
                return false;
            }

            for ($n = 0; $n <sizeof($data['Contents']); $n++) {
                if (empty($filter) || stripos($data['Contents'][$n]['Key'], $filter) !== false) {
                    $files[] = [
                        'filename' => $data['Contents'][$n]['Key'],
                        'size' => $data['Contents'][$n]['Size'],
                        'modified' => $data['Contents'][$n]['LastModified']
                    ];
                }
            }
            $params['ContinuationToken'] = $data['NextContinuationToken'];
        } while ($data['IsTruncated']);

        Log::data('files', $files);
        return true;
    }

    public function copy($filename, $path)
    {
        if (!self::checkCredentials()) {
            return false;
        }

        $uploader = new MultipartUploader($this->s3, $path, [
            'bucket' => $this->s3_bucket,
            'key'    => $filename
        ]);
        try {
            $result = $uploader->upload();
        } catch (MultipartUploadException $e) {
            Log::error($e->getMessage());
            Log::error('S3Exception on multipart upload!');
            return false;
        }

        return true;
    }

    public function getFile($filename, $path)
    {
        if (!self::checkCredentials()) {
            return false;
        }

        // Check if file exists.
        if (!$this->s3->doesObjectExist($this->s3_bucket, $filename)) {
            Log::error('S3 file not found: ' . $filename);
            return false;
        }

        // If file exists, download it.
        try {
            $result = $this->s3->getObject([
                'Bucket' => $this->s3_bucket,
                'Key'    => $filename,
                'SaveAs' => $path,
            ]);
        }
        catch(S3Exception $e) {
            Log::error($e->getMessage());
            Log::error('S3 failed to retrieve file.');
            return false;
        }

        return true;
    }
}
