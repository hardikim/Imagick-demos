<?php
$imagick = new Imagick(realpath("../images/TestImage.jpg"));





$imagick->adaptiveSharpenImage(2, 20);


header("Content-Type: image/jpg");
echo $imagick->getImageBlob();