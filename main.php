<?php
class video_encoder{
    public static function encode_video(string $inPath, string $outPath, array $options = array()):bool{

        if(isset($options['preset'])){
            if(is_string($options['preset'])){
                $presetOptions = self::loadPreset($options['preset']);
                if(is_array($presetOptions)){
                    $options = array_merge($presetOptions, $options);
                }
            }
        }
        
        $options = self::parseOptions($options);

        if(is_file($inPath)){
            $outPathFolder = files::getfileDir($outPath);
            $outPathFileExtension = "." . files::getFileExtension($outPath);
            $outPathFileName = str_replace($outPathFileExtension,"",files::getFileName($outPath));

            files::ensureFolder($outPathFolder);

            $command = '"' . e_ffmpeg::path('ffmpeg') . '" ';

            if($options['realTime']){
                $command .= '-re ';
            }

            $command .= '-i "' . $inPath . '" ';

            if(is_string($options['customArgs']) && !empty($options['customArgs'])){
                $command .= $options['customArgs'] . " ";
            }
            else{
                if($options['NVENC'] === true){
                    $command .= '-c:v h264_nvenc ';
                }
    
                if(is_int($options['qualityLoss'])){
                    $qLoss = intval($options['qualityLoss']);
                    if($qLoss < 1 || $qLoss > 51){
                        $qLoss = 24;
                    }
                    if($options['NVENC'] === true){
                        $command .= '-cq:v ';
                    }
                    else{
                        $command .= '-crf ';
                    }
                    $command .= $qLoss . ' ';
                }
    
                if(is_int($options['colorBitDepth'])){
                    $colorDepth = intval($options['colorBitDepth']);
                    $colorDepth = math::getClosest($colorDepth,array(8,10));
                    if($colorDepth === 10){
                        $command .= '-pix_fmt yuv420p10le ';
                    }
                    else{
                        $command .= '-pix_fmt yuv420p ';
                    }
                }
    
                $command .= '-vf "';
    
                if(is_int($options['framesPerSecond'])){
                    $fps = round(floatval($options['framesPerSecond']),3);
                    if($fps < 1 || $fps > 1000){
                        $fps = 29.97;
                    }
                    $command .= 'fps=' . $fps . ',';
                }
    
                $width = intval($options['outputResolutionWidth']);
                $height = intval($options['outputResolutionHeight']);
                if($width < 10 || $width > 8192){
                    $width = 1920;
                }
                if($height < 10|| $height > 4320){
                    $height = 1080;
                }
                $command .= 'scale='. $width .':'. $height .':force_original_aspect_ratio=decrease';
                $command .= ',pad='. $width .':'. $height .':(ow-iw)/2:(oh-ih)/2';
    
                if($options['saturation'] !== false){
                    $command .= ',eq=saturation=' . $options['saturation'];
                }
    
                $command .= ',setsar=1" ';
    
                if(is_int($options['outputVideoBitrate'])){
                    $command .= '-b:v ' . $options['outputVideoBitrate'] . 'k ';
                }
    
                if(is_int($options['outputAudioBitrate'])){
                    $command .= '-b:a ' . $options['outputAudioBitrate'] . 'k ';
                }
                if(is_int($options['outputAudioSampleRate'])){
                    $command .= '-ar ' . $options['outputAudioSampleRate'] . ' ';
                }
    
                if(is_string($options['format'])){
                    $command .= '-f "' . $options['format'] . '" ';
                }
    
                if(is_int($options['cpuThreads'])){
                    $threads = intval($options['cpuThreads']);
                    if($threads < 1){
                        $threads = 1;
                    }
                    $command .= '-threads ' . $threads . ' ';
                }
            }

            $millistamp = time::millistamp();
            $tempOutName = $outPathFolder . '\\' . $outPathFileName . "_tmp" . $millistamp . $outPathFileExtension;
            $command .= '"' . $tempOutName . '" -y';

            //mklog("general","FileEncode: Encoding " . $inPath . " (" . filesize($inPath) / (1024**3) . " GB)",false);
            exec($command);

            sleep(2);

            $jsonPath = $tempOutName . '.json';
            exec('"' . e_ffmpeg::path('ffprobe') . '" -v quiet -print_format json -show_format -show_streams "' . $tempOutName . '">"' . $jsonPath . '"');
            $jsonData = json::readFile($jsonPath);
            
            if(isset($jsonData['streams'][0]['codec_type'])){
                $i = 0;
                redo:
                if(is_file($outPath)){
                    $i++;
                    $outPath = $outPathFolder . "\\" . $outPathFileName . " " . $i . $outPathFileExtension;
                    if($i < 10){
                        goto redo;
                    }
                    else{
                        goto skiprename;
                    }
                }

                rename($tempOutName, $outPath);
                skiprename:
                unlink($jsonPath);
                return true;
            }
            else{
                if(is_file($tempOutName)){
                    sleep(2);
                    mklog("warning","Failed to encode " . $inPath,false);
                    unlink($tempOutName);
                }
            }
        }

        return false;
    }
    public static function encode_folder(string $sourceFolder, string $destinationFolder, bool $recursive = false, $jobId = false, array $videoTypes = array("mp4","mov","mkv","avi"), array $encodeOptions = array(), string $outFileExtension = "mp4", bool $deleteSourceAfter = false, bool $useConductor = false):bool{
        $return = false;
        $saturationModified = false;
        if(is_dir($sourceFolder)){
            $jobFolder = self::makeJobFolderString($jobId);
            files::ensureFolder($jobFolder);
            $jobFiles = glob($jobFolder . '\\*.json');
            $doneFiles = array();
            foreach($jobFiles as $jobFile){
                $doneFiles[] = json::readFile($jobFile)['format']['filename'];
            }
            $files = array();
            if($recursive){
                $files = files::globRecursive($sourceFolder . "\\", "*.*");
            }
            else{
                $files = glob($sourceFolder . "\\*");
            }
            foreach($files as $file){

                if(is_file("temp/video_encoder/stop")){
                    mklog("general","FolderEncode: Stop file found, stopping",false);
                    return $return;
                }

                if(self::isVideo($file,$videoTypes)){
                    foreach($doneFiles as $doneFile){
                        if($file === $doneFile){
                            mklog("general","FolderEncode: Skipping " . $file,false);
                            goto end;
                        }
                    }

                    $videoInfo = self::getVideoInfo($file);
                    if(!isset($videoInfo['format']['filename']) || !isset($videoInfo['format']['bit_rate']) || !isset($videoInfo['streams'])){
                        unlink($videoInfo['infoFile']);
                        mklog("general","FolderEncode: " . $file . " Is broken or unreadable",false);
                        goto end;
                    }

                    $bitrate = round($videoInfo['format']['bit_rate']/1024);
                    if($bitrate < 1000){
                        mklog("general","FolderEncode: Skipping " . $file . " as it has a low bitrate (" . $bitrate . " kb)",false);
                        goto end;
                    }

                    $vStream = 0;
                    foreach($videoInfo["streams"] as $stream){
                        if($stream["codec_type"] == "video"){$vStream = $stream["index"]; break;}
                    }

                    $fps = eval("return " . $videoInfo['streams'][$vStream]['avg_frame_rate'] . ";");//Avg frame rate e.g: 30/1
                    $expectedFps = array(8,10,15,20,23.976,24,25,29.97,30,48,50,59.94,60,75,90,100,120,140,160,180,200,240);
                    $encodeOptions['framesPerSecond'] = math::getClosest($fps,$expectedFps);
                    if(round($fps) !== round($encodeOptions['framesPerSecond'])){
                        mklog("warning","FolderEncode: Fps conversion (" . $fps . " => " . $encodeOptions['framesPerSecond'] . ") for " . $file,false);
                        sleep(1);
                        echo "Applying new FPS value\n";
                    }
                    
                    if(self::isCinelikeD($file,$videoInfo)){
                        if(!isset($encodeOptions['saturation'])){
                            $saturationModified = true;
                            $encodeOptions['saturation'] = 1.4;
                        }
                    }

                    $someNumber = time::millistamp();
                    $i = 0;
                    $fileExtenstion = files::getFileExtension($file);
                    $tempPath = str_replace($fileExtenstion,$someNumber . "_TEMP." . $outFileExtension,$file);
                    $outPath = str_replace($sourceFolder,$destinationFolder,$tempPath);

                    encode:

                    $i++;

                    if($useConductor){
                        $functionString = "video_encoder::encode_video(";
                        $functionString .= '"' . files::validatePath($file,false) . '",';
                        $functionString .= '"' . files::validatePath($outPath,false) . '",';
                        $functionString .= data_types::array_to_eval_string($encodeOptions) . ");";
                        $functionString = str_ireplace("\\","\\\\",$functionString);

                        $finishFunctionString = "video_encoder::afterFolderEncode(";
                        $finishFunctionString .= '$jobData["return"],';
                        $finishFunctionString .= '"' . files::validatePath($file,false) . '",';
                        $finishFunctionString .= '"' . files::validatePath($outPath,false) . '",';
                        if($deleteSourceAfter){
                            $finishFunctionString .= 'true,';
                        }
                        else{
                            $finishFunctionString .= 'false,';
                        }
                        $finishFunctionString .= $someNumber . ',';
                        $finishFunctionString .= '"' . files::validatePath($jobFolder,false) . '");';
                        $finishFunctionString = str_ireplace("\\","\\\\",$finishFunctionString);

                        $conductorJob = conductor_client::addJob("127.0.0.1",$functionString,52000,$finishFunctionString);
                        if(is_string($conductorJob)){
                            mklog('general','FolderEncode: Added job ' . $conductorJob . ' to conductor 127.0.0.1:52000',false);
                            $return = true;
                        }
                        else{
                            mklog('warning','FolderEncode: Failed to add job to conductor 127.0.0.1:52000',false);
                        }
                    }
                    else{
                        if(self::encode_video($file,$outPath,$encodeOptions)){

                            $fileSize = filesize($file);
                            $outPathSize = filesize($outPath);
                            $gigabyte = 1024**3;

                            $percentage = round(($outPathSize/$fileSize)*100,1);
                            $percentages[] = $percentage;
                            if(count($percentages) > 20){
                                array_shift($percentages);
                            }
                            $averagePercentage = round(math::average($percentages),1);

                            if($outPathSize >= $fileSize){
                                mklog("general","FolderEncode: Unable to compress (" . $percentage . "% of original size, avg:" . $averagePercentage . "%) " . $file,false);
                                unlink($outPath);
                                goto end;
                            }
                            else{
                                mklog("general","FolderEncode: Compressed file (" . round($fileSize/$gigabyte,1) . " GB => " . round($outPathSize/$gigabyte,1) . " GB) (" . $percentage . "% of original size, avg:" . $averagePercentage . "%) " . $file,false);
                            }

                            if($deleteSourceAfter){
                                unlink($file);
                            }

                            $finalOutPath = str_replace($someNumber . "_TEMP.","",$outPath);
                            rename($outPath,$finalOutPath);

                            $return = true;

                            $doneFileEntry['format']['filename'] = $finalOutPath;
                            json::writeFile($jobFolder . "\\" . time::millistamp() . ".json",$doneFileEntry);

                            sleep(4);
                        }
                        else{
                            if($i < 3){
                                mklog("general","FolderEncode: Retrying encode for " . $file,false);
                                sleep(5);
                                goto encode;
                            }
                            else{
                                mklog("warning","FolderEncode: Unable to complete encode for " . $file,false);
                            }
                        }
                    }

                    unset($encodeOptions['framesPerSecond']);

                    if($saturationModified){
                        unset($encodeOptions['saturation']);
                        $saturationModified = false;
                    }
                }
                end:
            }
        }
        return $return;
    }
    public static function afterFolderEncode($encodeSuccess,$file,$outPath,$deleteSourceAfter,$someNumber,$jobFolder):bool{
        if($encodeSuccess){

            $fileSize = filesize($file);
            $outPathSize = filesize($outPath);
            $gigabyte = 1024**3;

            $percentage = round(($outPathSize/$fileSize)*100,1);
            $GLOBALS['videoEncoderAveragePercentages'][] = $percentage;
            if(count($GLOBALS['videoEncoderAveragePercentages']) > 20){
                array_shift($GLOBALS['videoEncoderAveragePercentages']);
            }
            $averagePercentage = round(math::average($GLOBALS['videoEncoderAveragePercentages']),1);

            if($outPathSize >= $fileSize){
                mklog("general","FolderEncode: Unable to compress (" . $percentage . "% of original size, avg:" . $averagePercentage . "%) " . $file,false);
                unlink($outPath);
                return false;
            }
            else{
                mklog("general","FolderEncode: Compressed file (" . round($fileSize/$gigabyte,1) . " GB => " . round($outPathSize/$gigabyte,1) . " GB) (" . $percentage . "% of original size, avg:" . $averagePercentage . "%) " . $file,false);
            }

            if($deleteSourceAfter){
                unlink($file);
            }

            $finalOutPath = str_replace($someNumber . "_TEMP.","",$outPath);
            rename($outPath,$finalOutPath);

            $doneFileEntry['format']['filename'] = $finalOutPath;
            json::writeFile($jobFolder . "\\" . time::millistamp() . ".json",$doneFileEntry);

            return true;
        }
        else{
            mklog("warning","FolderEncode: Unable to complete encode for " . $file,false);
            return false;
        }
    }
    public static function getFolderLength(string $sourceFolder, bool $recursive = false, array $videoTypes = array("mp4","mov","mkv","avi")):int|false{
        $return = false;
        if(is_dir($sourceFolder)){
            $jobId = (string) time::stamp();
            $jobFolder = getcwd() . '\\temp\\video_encoder\\folder_jobs\\' . $jobId;
            files::ensureFolder($jobFolder);

            $length = 0;

            echo "Searching for files...\n";
            $files = array();
            if($recursive){
                $files = files::globRecursive($sourceFolder . "\\", "*.*");
            }
            else{
                $files = glob($sourceFolder . "\\*.*");
            }
            foreach($files as $file){

                if(is_file("temp/video_encoder/stop")){
                    mklog("general","FolderEncode: Stop file found, stopping",false);
                    return false;
                }

                if(is_file($file)){
                    $isVideo = false;
                    $fileExtenstion = strtolower(files::getFileExtension($file));
                    foreach($videoTypes as $videoType){
                        if($fileExtenstion === strtolower($videoType)){
                            $isVideo = true;
                        }
                    }
                    if($isVideo){
                        $jsonPath = $jobFolder . "\\" . str_replace(array("\\","/",":","@","*"),"_",$file) . ".json";
                        $ffprobePath = e_ffmpeg::path("ffprobe");

                        echo "Scanning file " . $file . "\n";

                        exec('"' . $ffprobePath . '" -v quiet -print_format json -show_format -show_streams "' . $file . '">"' . $jsonPath . '"');

                        $videoInfo = json::readFile($jsonPath);
                        if(!isset($videoInfo['format']['filename']) || !isset($videoInfo['format']['duration'])){
                            unlink($jsonPath);
                            mklog("general","FolderEncode: " . $file . " Is broken or unreadable",false);
                            goto end;
                        }

                        $length += round($videoInfo['format']['duration']);

                        echo "Length: " . floor($length / 3600) . gmdate(":i:s", $length % 3600) . " \n";

                        $return = intval($length);
                        
                    }
                }
                end:
            }
        }
        return $return;
    }
    public static function isVideo(string $path, array $videoTypes = array("mp4","mov","mkv","avi")):bool{
        if(!is_file($path)){
            return false;
        }
        $fileExtenstion = strtolower(files::getFileExtension($path));
        foreach($videoTypes as $videoType){
            if($fileExtenstion === strtolower($videoType)){
                return true;
            }
        }
        return false;
    }
    private static function makeJobFolderString(string|bool $jobId=false):string{
        if(!is_string($jobId)){
            $jobId = (string) time::stamp();
        }
        return getcwd() . '\\temp\\video_encoder\\folder_jobs\\' . $jobId;
    }
    public static function getVideoInfo(string $path, string|bool $jobFolder=false):array{
        if(!is_string($jobFolder)){
            $jobFolder = self::makeJobFolderString();
        }

        files::ensureFolder($jobFolder);

        $jsonPath = $jobFolder . "\\" . str_replace(array("\\","/",":","@","*"),"_",$path) . ".json";
        $ffprobePath = e_ffmpeg::path("ffprobe");

        exec('"' . $ffprobePath . '" -v quiet -print_format json -show_format -show_streams ' . files::validatePath($path,true) . '>' . files::validatePath($jsonPath,true));

        $result = json::readFile($jsonPath);

        if(!is_array($result)){
            $result = array();
        }

        $result['infoFile'] = $jsonPath;

        return $result;
    }
    private static function isCinelikeD(string $path, array|bool $videoInfo=false):bool{
        if($videoInfo === false){
            $videoInfo = self::getVideoInfo($path);
        }
        $fileExtenstion = files::getFileExtension($path);
        if($fileExtenstion === "MOV"){
            if(isset($videoInfo['format']['tags']['com.panasonic.Semi-Pro.metadata.xml'])){
                $xmldata = data_types::xmlStringToArray($videoInfo['format']['tags']['com.panasonic.Semi-Pro.metadata.xml']);
                if(isset($xmldata['UserArea']['AcquisitionMetadata']['CameraUnitMetadata']['Gamma']['CaptureGamma'])){
                    if($xmldata['UserArea']['AcquisitionMetadata']['CameraUnitMetadata']['Gamma']['CaptureGamma'] === "CINELIKE_D"){
                        return true;
                    }
                }
            }
        }
        return false;
    }
    public static function init():void{
        $defaultSettings = array(
            "presetsPath" => "videoencoder/presets",
        );
        foreach($defaultSettings as $dsName => $dsValue){
            settings::set($dsName,$dsValue,false);
        }

        if(!self::doesPresetExist("default")){
            self::createPreset("default",array(),false);
        }
    }
    public static function doesPresetExist(string $name):bool{
        $path = self::presetPath($name);
        if(!is_string($path)){
            return false;
        }
        return is_file($path);
    }
    public static function loadPreset(string $name):array|false{
        $path = self::presetPath($name);
        if(!is_string($path)){
            return false;
        }
        return json::readFile($path,false);
    }
    public static function createPreset(string $name, array $options = array(), $overwrite=true):bool{
        $options = self::parseOptions($options);
        $path = self::presetPath($name);
        if(!is_string($path)){
            return false;
        }
        return json::writeFile($path, $options, $overwrite);
    }
    private static function presetPath(string $name):string|false{
        if(preg_match('/^[a-zA-Z0-9\s_-]+$/', $name) !== 1){
            return false;
        }
        $path = settings::read("presetsPath");
        if(!is_string($path)){
            return false;
        }
        return $path . '/' . $name . '.json';
    }
    private static function parseOptions(array $options = array()):array|false{
        $defaultOptions = array(
            "outputResolutionWidth" => 1920,
            "outputResolutionHeight" => 1080,
            "outputVideoBitrate" => false,
            "outputAudioBitrate" => false,
            "outputAudioSampleRate" => false,
            "saturation"=> false,
            "framesPerSecond" => false,
            "colorBitDepth" => 8,
            "NVENC" => false,
            "cpuThreads" => false,
            "qualityLoss" => 23,
            "format" => false,
            "realTime" => false,
            "customArgs" => false
        );
        $outOptions = $defaultOptions;
        foreach($defaultOptions as $defaultOption => $defaultOptionValue){
            if(isset($options[$defaultOption])){
                $outOptions[$defaultOption] = $options[$defaultOption];
            }
        }
        
        return $outOptions;
    }
}

//changed skip low bitrate in encodefolder to 1000

//added customArgs option in encode_video which overrides ffmpeg options