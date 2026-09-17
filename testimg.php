<?php

/**
 * ImageProcessor Class
 * Handles conversion to WebP and generates specific sizes for CampMart.
 */
class ImageProcessor {
    
    private $imageSizes = [
        'thumb'  => ['width' => 100],
        'medium' => ['width' => 800]
    ];

    private $quality = 80;

    public function __construct($quality = 80) {
        $this->quality = $quality;
        
        if (!extension_loaded('gd')) {
            throw new Exception("GD library is not installed.");
        }
    }

    public function processImage($sourcePath, $outputDir, $baseName) {
        if (!file_exists($sourcePath)) {
            throw new Exception("Source file does not exist.");
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $results = [];
        $sourceImage = $this->createImageResource($sourcePath);
        
        if (!$sourceImage) {
            throw new Exception("Unsupported image format.");
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        foreach ($this->imageSizes as $key => $dimensions) {
            $targetWidth = $dimensions['width'];
            // Maintain aspect ratio: (original height / original width) * new width
            $targetHeight = round(($origHeight / $origWidth) * $targetWidth);

            $processedImage = $this->resizeImage($sourceImage, $origWidth, $origHeight, $targetWidth, $targetHeight);
            
            // Naming convention logic
            $fileName = ($key === 'thumb') ? "small_" . $baseName . ".webp" : $baseName . ".webp";
            $destination = rtrim($outputDir, '/') . '/' . $fileName;

            if (imagewebp($processedImage, $destination, $this->quality)) {
                $results[$key] = $destination;
            }

            imagedestroy($processedImage);
        }

        imagedestroy($sourceImage);
        return $results;
    }

    private function createImageResource($path) {
        $info = getimagesize($path);
        if (!$info) return null;
        
        switch ($info['mime']) {
            case 'image/jpeg': return imagecreatefromjpeg($path);
            case 'image/png':
                $img = imagecreatefrompng($path);
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                return $img;
            case 'image/webp': return imagecreatefromwebp($path);
            default: return null;
        }
    }

    private function resizeImage($source, $srcW, $srcH, $dstW, $dstH) {
        $targetImage = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
        imagefilledrectangle($targetImage, 0, 0, $dstW, $dstH, $transparent);

        imagecopyresampled($targetImage, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        return $targetImage;
    }
}

// --- IMPLEMENTATION LOGIC ---
$message = "";
$allGeneratedImages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_images'])) {
    try {
        $files = $_FILES['product_images'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $uploadDirectory = 'uploadsx'; // Ensure this folder exists and is writable
        $processor = new ImageProcessor(85); // 85% quality
        $processedCount = 0;
        $errorCount = 0;

        // Handle single file or multiple files
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < $fileCount; $i++) {
            try {
                // Get individual file details
                $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $fileTmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
                $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                // Skip if there's an error with this file
                if ($fileError !== UPLOAD_ERR_OK) {
                    $errorCount++;
                    continue;
                }

                // Validate file type
                if (!in_array($fileType, $allowedTypes)) {
                    $errorCount++;
                    continue;
                }

                // Generate unique ID and process
                $uniqueId = time() . '_' . bin2hex(random_bytes(4));
                $baseFileName = "product_" . $uniqueId;
                $generatedImages = $processor->processImage($fileTmp, $uploadDirectory, $baseFileName);
                
                $allGeneratedImages[] = [
                    'original' => $fileName,
                    'images' => $generatedImages
                ];
                $processedCount++;
            } catch (Exception $e) {
                $errorCount++;
            }
        }

        if ($processedCount > 0) {
            $message = "Successfully processed $processedCount image(s).";
            if ($errorCount > 0) {
                $message .= " ($errorCount file(s) skipped due to errors)";
            }
        } else {
            $message = "Error: No valid images were processed.";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampMart Image Uploader</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; line-height: 1.6; background: #f4f4f9; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .result { margin-top: 20px; padding: 10px; border-left: 4px solid #28a745; background: #e9f7ef; }
        .error { border-left: 4px solid #dc3545; background: #fbeae9; }
        .preview-box { display: flex; gap: 10px; margin-top: 10px; }
        img { border: 1px solid #ddd; border-radius: 4px; }
        input[type="file"] { margin: 10px 0; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

<div class="card">
    <h2>CampMart Image Processor</h2>
    <p>Upload one or multiple product images to generate WebP versions (Thumb & Medium).</p>

    <?php if ($message): ?>
        <div class="result <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <label for="images">Select Images (JPG, PNG, or WebP):</label><br>
        <input type="file" name="product_images[]" id="images" accept="image/jpeg,image/png,image/webp" multiple required>
        <br>
        <button type="submit">Upload and Convert</button>
    </form>

    <?php if (!empty($allGeneratedImages)): ?>
        <h3>Generated Files (<?php echo count($allGeneratedImages); ?> image<?php echo count($allGeneratedImages) > 1 ? 's' : ''; ?>):</h3>
        <?php foreach ($allGeneratedImages as $index => $imageSet): ?>
            <div style="margin-bottom: 30px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                <h4>Image <?php echo $index + 1; ?>: <?php echo htmlspecialchars($imageSet['original']); ?></h4>
                <div class="preview-box">
                    <div>
                        <small>Thumbnail (100px)</small><br>
                        <img src="<?php echo $imageSet['images']['thumb']; ?>" alt="Thumb">
                    </div>
                    <div>
                        <small>Medium (800px)</small><br>
                        <img src="<?php echo $imageSet['images']['medium']; ?>" width="200" alt="Medium">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <p>Stored in: <code>/uploadsx/</code></p>
    <?php endif; ?>
</div>

</body>
</html>