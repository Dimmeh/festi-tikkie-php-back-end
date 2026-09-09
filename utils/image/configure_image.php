<?php

function configureImage(array $file, string $folder_name = "profile-images"){
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mimeType = $finfo->file($file["tmp_name"]);

  $allowedMimeTypes = [
      "image/jpeg",
      "image/png",
      "image/webp"
  ];

  if (!in_array($mimeType, $allowedMimeTypes, true)) {
      throw new RuntimeException(
          "Alleen JPG-, PNG- en WebP-afbeeldingen zijn toegestaan."
      );
  }
  $image = match ($mimeType) {
    "image/jpeg" => imagecreatefromjpeg($file["tmp_name"]),
    "image/png" => imagecreatefrompng($file["tmp_name"]),
    "image/webp" => imagecreatefromwebp($file["tmp_name"]),
    default => false
};

if ($image === false) {
    throw new RuntimeException("De afbeelding kon niet worden verwerkt.");
}
    $targetDirectory = "/uploads/festi-tikkie/".$folder_name."/";
    $uploadDirectory = dirname(__DIR__, 2) . $targetDirectory;

  if (!is_dir($uploadDirectory)) {
      mkdir($uploadDirectory, 0755, true);
  }

  $fileName = bin2hex(random_bytes(16)) . ".webp";
  $absolutePath = $uploadDirectory . $fileName;

  if (!imagewebp($image, $absolutePath, 80)) {
      $image = null;
      throw new RuntimeException("De afbeelding kon niet worden opgeslagen.");
  }

  $image = null;
  return $targetDirectory . $fileName;
}