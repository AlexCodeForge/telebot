<?php

namespace App\Services;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use VercelBlobPhp\Client;
use VercelBlobPhp\CommonCreateBlobOptions;

class VercelBlobFilesystemAdapter implements FilesystemAdapter
{
    protected Client $client;
    protected array $blobs = [];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->client->head($this->buildUrl($path));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        // Vercel Blob doesn't have directory concept
        return false;
    }

        public function write(string $path, string $contents, Config $config): void
    {
        try {
            $options = new CommonCreateBlobOptions(
                addRandomSuffix: false,
                contentType: $this->guessContentType($path),
            );

            $result = $this->client->put($path, $contents, $options);

            // Store the blob info for later reference
            $this->blobs[$path] = $result;

            // Store URL in config for retrieval
            $config->set('vercel_blob_url', $result->url);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Get the last uploaded file's URL
     */
    public function getLastUploadUrl(): ?string
    {
        if (empty($this->blobs)) {
            return null;
        }

        $lastBlob = end($this->blobs);
        return $lastBlob->url ?? null;
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        try {
            $url = $this->buildUrl($path);
            $response = file_get_contents($url);

            if ($response === false) {
                throw new \Exception('Failed to read file');
            }

            return $response;
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        $contents = $this->read($path);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $url = $this->buildUrl($path);
            $this->client->del([$url]);
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        // Vercel Blob doesn't have directory concept
        throw new \Exception('Directories are not supported in Vercel Blob');
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Vercel Blob doesn't have directory concept
        throw UnableToCreateDirectory::atLocation($path, 'Directories are not supported in Vercel Blob');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Vercel Blob files are always public
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, null, $this->guessContentType($path));
    }

    public function lastModified(string $path): FileAttributes
    {
        // Not supported by Vercel Blob
        return new FileAttributes($path, null, null, time());
    }

    public function fileSize(string $path): FileAttributes
    {
        // Not easily available without fetching the file
        return new FileAttributes($path, null, null, null, null);
    }

        public function listContents(string $path, bool $deep): iterable
    {
        try {
            $result = $this->client->list();

            foreach ($result->blobs as $blob) {
                if (str_starts_with($blob->pathname, $path)) {
                    $lastModified = $blob->uploadedAt instanceof \DateTime
                        ? $blob->uploadedAt->getTimestamp()
                        : null;
                    yield new FileAttributes($blob->pathname, $blob->size, 'public', $lastModified);
                }
            }
        } catch (\Exception $e) {
            // Return empty iterator on error
            return [];
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $sourceUrl = $this->buildUrl($source);
            $this->client->copy($sourceUrl, $destination);
            $this->delete($source);
        } catch (\Exception $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $sourceUrl = $this->buildUrl($source);
            $this->client->copy($sourceUrl, $destination);
        } catch (\Exception $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function publicUrl(string $path, Config $config): string
    {
        return $this->buildUrl($path);
    }

    protected function buildUrl(string $path): string
    {
        // Check if we have stored blob info for this path
        if (isset($this->blobs[$path])) {
            return $this->blobs[$path]->url;
        }

        // Get configurable base URL from settings, fallback to hardcoded for backward compatibility
        $baseUrl = \App\Models\Setting::get('vercel_blob_base_url');

        if (empty($baseUrl)) {
            // Fallback to legacy hardcoded URL for existing deployments
            $baseUrl = "https://blob.vercel-storage.com";
        }

        // Remove trailing slash if present
        $baseUrl = rtrim($baseUrl, '/');

        return "{$baseUrl}/{$path}";
    }

    protected function guessContentType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
