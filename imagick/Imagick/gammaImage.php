<?php
$imagick = new Imagick(realpath("../images/TestImage.jpg"));

$imagick->gammaImage(2.0);

header("Content-Type: image/jpg");
echo $imagick->getImageBlob();