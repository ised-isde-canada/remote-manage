<?php

namespace RemoteManage;

/**
 * Implements the remote management server.
 */
class RemoteManageServer
{
    public function __construct()
    {
    }

    /**
     * Get the S3 credentials from the POST.
     *
     * If S3 credentials are provided as POST variables then set then as
     * environment variables, which will pass through to the manage script.
     * We don't check for missing or invalid credentials at this stage,
     * because they may already be set on the host.
     */
    public function getS3Credentials()
    {
        foreach (
            [
                'aws_access_key_id',
                'aws_secret_access_key',
                'aws_s3_bucket','aws_s3_region'
            ] as $var
        ) {
            if (isset($_POST[$var])) {
                putenv(strtoupper($var) . '=' . $_POST[$var]);
            }
        }
    }

    /**
     * Get a JSON result from a remote manage command.
     *
     * Parse the result from the remote-manage command, separate into messages
     * and data and return a JSON structure.
     *
     * @param string $result
     *
     * @return mixed[]
     */
    public function getJSONResult($result)
    {
        $result = trim($result);
        if ($result[0] == '[' or $result[0] == '{') {
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
}
