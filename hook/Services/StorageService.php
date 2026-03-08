<?php

namespace TLC\Hook\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use TLC\Core\Logger;

class StorageService
{
    private S3Client $client;
    private string $bucket;
    private ?string $kmsKeyId;

    public function __construct()
    {
        $region = $_ENV['OVH_S3_REGION'] ?? 'gra';
        $endpoint = $_ENV['OVH_S3_ENDPOINT'] ?? 'https://s3.gra.io.cloud.ovh.net';
        $key = $_ENV['OVH_S3_ACCESS_KEY'] ?? '';
        $secret = $_ENV['OVH_S3_SECRET_KEY'] ?? '';
        $this->bucket = $_ENV['OVH_S3_BUCKET'] ?? '';
        $this->kmsKeyId = $_ENV['OVH_KMS_KEY_ID'] ?? null;

        $this->client = new S3Client([
            'version' => 'latest',
            'region'  => $region,
            'endpoint' => $endpoint,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
            'use_path_style_endpoint' => true,
        ]);
    }

    /**
     * Uploads a file to the S3 bucket using KMS encryption.
     *
     * @param string $key The destination path in the bucket
     * @param string $sourceFile The path to the file to upload
     * @param string $mimeType The MIME type of the file
     * @return string The S3 URI
     */
    public function uploadFile(string $key, string $sourceFile, string $mimeType): string
    {
        if (empty($this->kmsKeyId)) {
            throw new \Exception("FIPS Compliance Error: S3 uploads require a KMS Key for encryption.");
        }

        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'SourceFile' => $sourceFile,
                'ContentType' => $mimeType,
                'ServerSideEncryption' => 'aws:kms',
                'SSEKMSKeyId' => $this->kmsKeyId,
            ];

            $this->client->putObject($params);

            return "s3://{$this->bucket}/{$key}";
        } catch (AwsException $e) {
            Logger::error("Failed to upload file to OVH S3: " . $e->getMessage());
            throw new \Exception("Failed to upload file: " . $e->getMessage());
        }
    }

    /**
     * Generates a presigned URL for secure, temporary access to a file.
     *
     * @param string $s3Uri The S3 URI of the object
     * @param string $expires The expiration time (e.g., '+20 minutes')
     * @return string The presigned URL
     */
    public function getPresignedUrl(string $s3Uri, string $expires = '+20 minutes'): string
    {
        try {
            // Parse s3://bucket/key format
            if (preg_match('/^s3:\/\/([^\/]+)\/(.+)$/', $s3Uri, $matches)) {
                $bucket = $matches[1];
                $key = $matches[2];
            } else {
                throw new \InvalidArgumentException("Invalid S3 URI format: {$s3Uri}");
            }

            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            $request = $this->client->createPresignedRequest($cmd, $expires);
            return (string) $request->getUri();
        } catch (AwsException $e) {
            Logger::error("Failed to generate presigned URL: " . $e->getMessage());
            throw new \Exception("Failed to generate presigned URL: " . $e->getMessage());
        }
    }
}
