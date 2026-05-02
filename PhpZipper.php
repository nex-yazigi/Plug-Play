<?php

class PhpZipper {
    
    /**
     * Compress a file or directory into a ZIP archive, with optional AES-256 encryption.
     *
     * @param string $source The file or directory to compress.
     * @param string $destination The output .zip file path.
     * @param string|null $password Optional password for AES-256 encryption.
     * @throws Exception
     */
    public static function compress($source, $destination, $password = null) {
        if (!extension_loaded('zip')) {
            throw new Exception("The PHP 'zip' extension is not loaded.");
        }

        $zip = new ZipArchive();
        
        // Open the zip file. Overwrite if it already exists.
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Failed to create ZIP file at $destination");
        }

        $source = realpath($source);
        if ($source === false) {
            throw new Exception("Source path does not exist.");
        }

        // Handle Directory Compression
        if (is_dir($source)) {
            $iterator = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $fileReal = $file->getRealPath();
                
                // Get relative path for ZIP to avoid absolute paths in the archive
                $relativePath = substr($fileReal, strlen($source) + 1);
                $relativePath = str_replace('\\', '/', $relativePath); // Windows compatibility

                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else if ($file->isFile()) {
                    $zip->addFile($fileReal, $relativePath);
                    // Encryption must be set per-file after it is added
                    if ($password) {
                        $zip->setEncryptionName($relativePath, ZipArchive::EM_AES_256, $password);
                    }
                }
            }
        } 
        // Handle Single File Compression
        else if (is_file($source)) {
            $basename = basename($source);
            $zip->addFile($source, $basename);
            if ($password) {
                $zip->setEncryptionName($basename, ZipArchive::EM_AES_256, $password);
            }
        }

        $zip->close();
        return true;
    }

    /**
     * Extract a ZIP archive.
     *
     * @param string $source The .zip file path.
     * @param string $destination The output directory path.
     * @param string|null $password Optional password for decryption.
     * @throws Exception
     */
    public static function extract($source, $destination, $password = null) {
        if (!extension_loaded('zip')) {
            throw new Exception("The PHP 'zip' extension is not loaded.");
        }

        $zip = new ZipArchive();
        if ($zip->open($source) !== true) {
            throw new Exception("Failed to open ZIP file at $source");
        }

        if ($password) {
            $zip->setPassword($password);
        }

        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new Exception("Extraction failed. (Is the password correct?)");
        }

        $zip->close();
        return true;
    }
}

// -----------------------------------------------------------------------------
// Command Line Interface (CLI)
// -----------------------------------------------------------------------------

if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    if ($argc < 4) {
        echo "PhpZipper - Command Line Archive Utility\n";
        echo "----------------------------------------\n";
        echo "Usage:\n";
        echo "  php zipper.php compress <source_file_or_dir> <output.zip> [password]\n";
        echo "  php zipper.php extract  <source.zip> <output_dir> [password]\n\n";
        echo "Examples:\n";
        echo "  php zipper.php compress ./my_folder secure.zip mySecretPassword\n";
        echo "  php zipper.php extract secure.zip ./extracted_folder mySecretPassword\n";
        exit(1);
    }

    $command = $argv[1];
    $source = $argv[2];
    $target = $argv[3];
    $password = $argv[4] ?? null; // Password is optional

    try {
        if ($command === 'compress') {
            echo "Compressing '$source' to '$target'...\n";
            if ($password) echo "Applying AES-256 Encryption...\n";
            
            PhpZipper::compress($source, $target, $password);
            echo "Success!\n";
        } elseif ($command === 'extract') {
            echo "Extracting '$source' to '$target'...\n";
            
            // Create target dir if it doesn't exist
            if (!is_dir($target)) {
                mkdir($target, 0777, true);
            }
            
            PhpZipper::extract($source, $target, $password);
            echo "Success!\n";
        } else {
            echo "Error: Unknown command '$command'. Use 'compress' or 'extract'.\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} 
