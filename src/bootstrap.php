<?php


namespace {

    //yolo - We use a global to allow us to do a hack to make all the code examples
//appear to use the standard 'header' function, but also capture the content type 
//of the image
    $imageType = null;
    $imageCache = true;

    function renderImageHTMLElement() {

    }
    

    //color rgb(23, 24, 41) doesn't show popup
    
    function exceptionHandler(Exception $ex) {
        //TODO - need to ob_end_clean as many times as required because 
        //otherwise partial content gets sent to the client.

        if (headers_sent() == false) {
            header("HTTP/1.0 500 Internal Server Error", true, 500);
        }
        else {
            //Exception after headers sent
        }
        echo "Exception " . get_class($ex) . ': ' . $ex->getMessage();

        foreach ($ex->getTrace() as $tracePart) {

            if (isset($tracePart['file']) && isset($tracePart['line'])) {
                echo $tracePart['file'] . " " . $tracePart['line'] . "<br/>";
            }
            else if (isset($tracePart["function"])) {
                echo $tracePart["function"] . "<br/>";
            }
            else {
                var_dump($tracePart);
            }
        }

        //TODO - format this
        //var_dump($ex->getTrace());
    }


    function errorHandler($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return true;
        }

        switch ($errno) {
            case E_CORE_ERROR:
            case E_ERROR:
            {
                echo "<b>Fatality</b> [$errno] $errstr on line $errline in file $errfile <br />\n";
                break;
            }

            default:
                {
                echo "<b>errorHandler</b> [$errno] $errstr<br />\n";

                return false;
                }
        }

        /* Don't execute PHP internal error handler */

        return true;
    }


    function fatalErrorShutdownHandler() {
        $last_error = error_get_last();

        if (!$last_error) {
            return false;
        }

        switch ($last_error['type']) {
            case (E_ERROR):
            case (E_PARSE):
            {
                // fatal error
                header("HTTP/1.0 500 Bugger bugger bugger", true, 500);
                var_dump($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
                exit(0);
            }

            case(E_CORE_WARNING):
            {
                //TODO - report errors properly.
                errorHandler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
                break;
            }

            default:
                {
                header("HTTP/1.0 500 Unknown fatal error", true, 500);
                var_dump($last_error);
                break;
                }
        }

        return false;
    }


    function getImageCacheFilename($category, $example, $params) {
        $filename = "../var/cache/imageCache/" . $category . '/' . $example;
        if (!empty($params)) {
            $filename .= '_' . md5(json_encode($params));
        }

        return $filename;
    }


    function createAndCacheFile(\Auryn\Provider $injector, $functionFullname, $filename) {

        global $imageType;
        ob_start();

        $injector->execute($functionFullname);

        if ($imageType == null) {
            ob_end_clean();
            throw new \Exception("imageType not set, can't cache image correctly.");
        }

        
        
        $image = ob_get_contents();
        @mkdir(dirname($filename), 0755, true);
        //TODO - is this atomic?
        $fullFilename = $filename . "." . strtolower($imageType);
        
        file_put_contents($fullFilename, $image);
        ob_end_clean();

        return new \ImagickDemo\Response\FileResponse($fullFilename, "image/" . $imageType);
    }


/**
 * @return \Auryn\Provider
 */
function bootstrapInjector() {

    require '../../imagick-demos.conf.php';
    
    $injector = new Auryn\Provider();
    $jigConfig = new Intahwebz\Jig\JigConfig(
        "../templates/",
        "../var/compile/",
        'tpl',
        \Intahwebz\Jig\JigRender::COMPILE_CHECK_EXISTS
    );

    $injector->share($jigConfig);

    $injector->defineParam('imageBaseURL', null);
    $injector->defineParam('customImageBaseURL', null);
    $injector->alias('ImagickDemo\Control', 'ImagickDemo\Control\NullControl');
    $injector->alias('ImagickDemo\Navigation\Nav', 'ImagickDemo\Navigation\NullNav');
    $injector->alias('Intahwebz\Request', 'Intahwebz\Routing\HTTPRequest');
    $injector->alias('ImagickDemo\Example', 'ImagickDemo\NullExample');
    //$injector->alias('ImagickDemo\Banners\Banner', 'ImagickDemo\Banners\PHPStormBanner');
    $injector->alias('ImagickDemo\Banners\Banner', 'ImagickDemo\Banners\NullBanner');
    $injector->share('ImagickDemo\Control');
    $injector->share('ImagickDemo\Example');
    $injector->share('ImagickDemo\Navigation\Nav');

    $injector->define('ImagickDemo\DocHelper', [
        ':category' => null,
        ':example' => null
    ]);

    
    $injector->define(
        'Stats\Librato',
        [
            ':libratoKey' => $libratoKey,
            ':libratoUsername' => $libratorUsername
        ]
    );

    $injector->define(
         'Stats\AsyncStats',
         [ ':statsSourceName' => $statsSourceName]
    );

    
    $injector->define(
         '\Stats\SimpleStats',
         [ ':statsSourceName' => $statsSourceName]
    );

    $redisParameters = array(
        'connection_timeout' => 30,
        'read_write_timeout' => 30,
    );

    $redisOptions = [];

    //This next line annoys phpstorm
    $injector->define(
             'Predis\Client',
                 array(
                     ':parameters' => $redisParameters,
                     ':options' => $redisOptions,
                 )
    );

    $injector->share('Predis\Client');
    $injector->share($injector);


//    $params = [
//        'image' => 'Lorikeet',
//        'filterType' => '31', 
//        'width' => '200', 
//        'height' => '200',
//        'blur' => '1',
//        'bestFit' => '1',
//        'cropZoom' => '1',
//        'task' => '0',
//    ];

    $injector->define(
         'Intahwebz\Routing\HTTPRequest',
         array(
             ':server' => $_SERVER,
             ':get' => $_GET,
             ':post' => $_POST,
             ':files' => $_FILES,
             ':cookie' => $_COOKIE
         )
    );

    $injector->defineParam('imageCachePath', "../var/cache/imageCache/");
    $injector->share($injector); //yolo - use injector as service locator

    return $injector;
}



function delegateAllTheThings(\Auryn\Provider $injector, $controlClass) {
    $params = ['a', 'adaptiveOffset', 'alpha', 'amount', 'amplitude', 'angle', 'b', 'backgroundColor', 'bestFit', 'blackPoint', 'blueShift', 'blur', 'brightness', 'canvasType', 'channel', 'clusterThreshold', 'color', 'colorElement', 'colorMatrix', 'colorSpace',  'contrast',  'contrastType', 'cropZoom', 'distortionExample', 'dither', 'endAngle', 'endX', 'endY', 'evaluateType', 'fillColor', 'filterType', 'firstTerm', 'fillModifiedColor', 'fourthTerm', 'fuzz', 'g', 'gamma', 'gradientStartColor', 'gradientEndColor', 'grayOnly', 'height', 'highThreshold', 'hue', 'image', 'imagePath', 'innerBevel', 'inverse', 'layerMethodType', 'length', 'lowThreshold',  'meanOffset', 'noiseType', 'numberColors', 'opacity', 'orientationType', 'originX', 'originY', 'outerBevel', 'paintType', 'r', 'raise', 'radius', 'reduceNoise', 'rollX', 'rollY', 'roundX', 'roundY', 'saturation', 'secondTerm', 'sepia', 'shearX', 'shearY', 'sigma', 'skew', 'smoothThreshold', 'solarizeThreshold', 'startAngle', 'startX', 'startY', 'statisticType', 'strokeColor', 'swirl', 'targetColor', 'textDecoration', 'textUnderColor', 'thirdTerm', 'threshold', 'thresholdAngle', 'thresholdColor', 'translateX', 'translateY', 'treeDepth', 'unsharpThreshold', 'virtualPixelType', 'whitePoint', 'x', 'y', 'w20', 'width', 'h20', 'sharpening', 'midpoint', 'sigmoidalContrast',];


    foreach ($params as $param) {
        $paramGet = 'get'.ucfirst($param);
        $injector->delegateParam(
             $param,
             [$controlClass, $paramGet]
        );
    }
}



//TODO - yuck
function setupExampleInjection(\Auryn\Provider $injector, $category, $example) {

    $injector->alias(\ImagickDemo\Navigation\Nav::class, \ImagickDemo\Navigation\CategoryNav::class);
    $injector->define(\ImagickDemo\Navigation\CategoryNav::class, [
        ':category' => $category,
        ':example' => $example
    ]);

    $categoryNav = $injector->make(\ImagickDemo\Navigation\CategoryNav::class);

    $exampleDefinition = $categoryNav->getExampleDefinition($category, $example);
    $function = $exampleDefinition[0];
    $controlClass = $exampleDefinition[1];

    if (array_key_exists('defaultParams', $exampleDefinition) == true) {
        foreach($exampleDefinition['defaultParams'] as $name => $value) {
            $defaultName = 'default'.ucfirst($name);
            $injector->defineParam($defaultName, $value);
        }
    }

    $injector->defineParam('imageBaseURL', '/image/'.$category.'/'.$example);
    $injector->defineParam('customImageBaseURL', '/customImage/'.$category.'/'.$example);
    $injector->defineParam('activeCategory', $category);
    $injector->defineParam('activeExample', $example);

    $injector->alias(\ImagickDemo\Control::class, $controlClass);
    $injector->share($controlClass);

    $injector->define(\ImagickDemo\DocHelper::class, [
        ':category' => $category,
        ':example' => $example
    ]);

    delegateAllTheThings($injector, $controlClass);
    $injector->alias(\ImagickDemo\Example::class, sprintf('ImagickDemo\%s\%s', $category, $function));

    return $function;
}


}

namespace ImagickDemo {


    /**
     * Hack the header function to allow us to capture the image type,
     * while still having clean example code.
     *
     * @param $string
     * @param bool $replace
     * @param null $http_response_code
     */
    function header($string, $replace = true, $http_response_code = null) {
        global $imageType;
        global $imageCache;

        if (stripos($string, "Content-Type: image/") === 0) {
            $imageType = substr($string, strlen("Content-Type: image/"));
        }

        if ($imageCache == false) {
            \header($string, $replace, $http_response_code);
        }
    }
    

    function analyzeImage(\Imagick $imagick, $graphWidth = 255, $graphHeight = 127) {

        $sampleHeight = 20;
        $border = 2;

        $imagick->transposeImage();
        $imagick->scaleImage($graphWidth, $sampleHeight);

        $imageIterator = new \ImagickPixelIterator($imagick);

        $luminosityArray = [];

        foreach ($imageIterator as $row => $pixels) { /* Loop trough pixel rows */
            foreach ($pixels as $column => $pixel) { /* Loop through the pixels in the row (columns) */
                /** @var $pixel \ImagickPixel */

                if (false) {
                    $color = $pixel->getColor();
                    $luminosityArray[] = $color['r'];
                }
                else {
                    $hsl = $pixel->getHSL();
                    $luminosityArray[] = ($hsl['luminosity']);
                }
            }
            $imageIterator->syncIterator(); /* Sync the iterator, this is important to do on each iteration */
            break;
        }

        $draw = new \ImagickDraw();


        $strokeColor = new \ImagickPixel('red');
        $fillColor = new \ImagickPixel('red');
        $draw->setStrokeColor($strokeColor);
        $draw->setFillColor($fillColor);
        $draw->setStrokeWidth(0);
        $draw->setFontSize(72);
        $draw->setStrokeAntiAlias(true);
        $previous = false;

        $x = 0;

        foreach ($luminosityArray as $luminosity) {
            $pos = ($graphHeight - 1) - ($luminosity * ($graphHeight - 1));

            if ($previous !== false) {
                //printf ( "%d, %d, %d, %d <br/>\n" , $x - 1, $previous, $x, $pos);
                $draw->line($x - 1, $previous, $x, $pos);
            }
            $x += 1;
            $previous = $pos;
        }

        $plot = new \Imagick();
        $plot->newImage($graphWidth, $graphHeight, 'white');
        $plot->drawImage($draw);

        $outputImage = new \Imagick();
        $outputImage->newImage($graphWidth, $graphHeight + $sampleHeight, 'white');
        $outputImage->compositeimage($plot, \Imagick::COMPOSITE_ATOP, 0, 0);

        $outputImage->compositeimage($imagick, \Imagick::COMPOSITE_ATOP, 0, $graphHeight);
        $outputImage->borderimage('black', $border, $border);

        $outputImage->setImageFormat("png");
        header("Content-Type: image/png");
        echo $outputImage;
    }


}
