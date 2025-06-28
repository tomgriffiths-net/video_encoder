<?php
class video_encoder{
    public static function init():void{
        $defaultSettings = array(
            "presetsPath" => "videoencoder/presets",
            "secondTry"   => true,
            "allowCinelikeDSaturationModification" => false
        );
        foreach($defaultSettings as $dsName => $dsValue){
            settings::set($dsName,$dsValue,false);
        }

        if(!self::doesPresetExist("default")){
            self::createPreset("default",[],false);
        }
    }
    public static function encode_video(string $inPath, string $outPath, array $options=[]):bool{
        if(!is_file($inPath)){
            mklog(2,'Input file does not exist');
            return false;
        }

        if(is_file($outPath)){
            mklog(2,'Output file already exists');
            return false;
        }

        $options = self::loadOptions($options);

        $secondTry = false;
        secondtry:

        $outPathFolder = files::getfileDir($outPath);
        files::ensureFolder($outPathFolder);
        
        $outPathFileExtension = "." . files::getFileExtension($outPath);
        $outPathFileName = str_replace($outPathFileExtension,"",files::getFileName($outPath));

        if(is_string($options['customArgs'])){
            if(!self::customArgsAllow2pass($options['customArgs'])){
                echo "Skipping 2pass\n";
                sleep(2);
                $options['2pass'] = false;
            }
        }

        $command = '"' . e_ffmpeg::path('ffmpeg') . '" ';
        $extra = '';

        if($options['realTime']){
            if(!$options['2pass']){
                $command .= '-re ';
            }
            else{
                mklog(2,'Unable to use realTime due to 2pass being enabled');
            }
        }

        $command .= '-i "' . $inPath . '" ';

        if(is_string($options['customArgs']) && !empty($options['customArgs'])){
            $cutoff = false;
            if($options['2pass']){
                foreach([' -c:a ', ' -b:a ', ' -f '] as $audioThing){
                    $newcutoff = strpos($options['customArgs'], $audioThing);
                    if(is_int($newcutoff)){
                        if(is_int($cutoff)){
                            if($newcutoff < $cutoff){
                                $cutoff = $newcutoff;
                            }
                        }
                        else{
                            $cutoff = $newcutoff;
                        }
                    }
                }
            }

            if(is_int($cutoff)){
                $command .= substr($options['customArgs'], 0, $cutoff) . ' ';
                $extra .= substr($options['customArgs'], $cutoff) . ' ';
            }
            else{
                $command .= $options['customArgs'] . " ";
            }
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

            if(is_int($options['framesPerSecond']) || is_float($options['framesPerSecond'])){
                $fps = round($options['framesPerSecond'],3);
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
                if($options['2pass']){
                    $extra .= '-b:a ' . $options['outputAudioBitrate'] . 'k ';
                }
                else{
                    $command .= '-b:a ' . $options['outputAudioBitrate'] . 'k ';
                }
            }
            if(is_int($options['outputAudioSampleRate'])){
                if($options['2pass']){
                    $extra .= '-ar ' . $options['outputAudioSampleRate'] . ' ';
                }
                else{
                    $command .= '-ar ' . $options['outputAudioSampleRate'] . ' ';
                }
            }

            if(is_string($options['format'])){
                if($options['2pass']){
                    $extra .= '-f "' . $options['format'] . '" ';
                }
                else{
                    $command .= '-f "' . $options['format'] . '" ';
                }
            }
        }

        if(is_int($options['cpuThreads'])){
            $threads = intval($options['cpuThreads']);
            if($threads < 0){
                $threads = 1;
            }
            $command .= '-threads ' . $threads . ' ';
        }

        $tempOutName = $outPathFolder . '\\' . $outPathFileName . "_tmp" . time::millistamp() . $outPathFileExtension;
        if($options['2pass']){
            $extra .= '"' . $tempOutName . '" -y';
        }
        else{
            $command .= '"' . $tempOutName . '" -y';
        }

        if($options['commandIntoFile']){
            if(!$options['2pass']){
                $commandFile = fopen('video_encoder-commandIntoFile.txt',"a");
                if(!$commandFile){
                    mklog("warning","Failed to open file: video_encoder-commandIntoFile.txt",false);
                    return false;
                }
                if(!fwrite($commandFile, $command . "\n")){
                    mklog("warning","Failed to append to file: video_encoder-commandIntoFile.txt",false);
                    return false;
                }
                fclose($commandFile);
                return true;
            }
            else{
                mklog(2,'Unable to use commandIntoFile with 2pass enabled');
            }
            return false;
        }

        if($options['2pass']){
            $passLogDir = getcwd() . '\\temp\\video_encoder\\2passlogs';
            files::ensureFolder($passLogDir);
            $passLogFile = $passLogDir . '\\' . time::millistamp();

            echo "Running first pass scan...\n";
            $pass1result = shell_exec($command . '-pass 1 -passlogfile "' . $passLogFile . '" -an -loglevel error -f null NUL 2>&1');
            sleep(2);

            if(!is_file($passLogFile . '-0.log') || !filesize($passLogFile . '-0.log') || $pass1result !== null){
                mklog(2,'Failed to scan file ' . $inPath);

                if(!$secondTry && settings::read('secondTry') === true){
                    $secondTry = true;
                    mklog("general","Trying again to scan " . $inPath . " in 20 seconds...",false);
                    sleep(20);
                    goto secondtry;
                }

                return false;
            }

            echo "Running second pass\n";

            $command .= '-pass 2 -passlogfile "' . $passLogFile . '" ' . $extra;

            sleep(2);
        }

        if($options['livePreview']){
            self::preview($command, $options['livePreviewWidth'], $options['livePreviewHeight']);
        }
        else{
            exec($command);
        }

        sleep(3);

        if(!self::isMedia($tempOutName)){
            mklog(2,"Failed to encode " . $inPath);

            if(is_file($tempOutName)){
                if(!unlink($tempOutName)){
                    mklog(2,"Failed to delete failed encode file " . $tempOutName);
                }
            }

            if(!$secondTry && settings::read('secondTry') === true){
                $secondTry = true;
                mklog(1,"Trying again to encode " . $inPath);
                goto secondtry;
            }

            return false;
        }

        if(!rename($tempOutName, $outPath)){
            mklog(2,'Failed to rename output file');
            return false;
        }

        return true;
    }
    public static function encode_folder(string $sourceFolder, string $destinationFolder, bool $recursive=false, bool|string $jobId=false, array $videoTypes=["mp4","mov","mkv","avi"], array $encodeOptions=[], string $outFileExtension="mp4", bool $deleteSourceAfter=false, bool $useConductor=false):bool{
        $return = false;
        $saturationModified = false;
        if(is_dir($sourceFolder)){

            $jobFolder = self::makeJobFolderString($jobId);
            files::ensureFolder($jobFolder);

            $doneFiles = json::readFile($jobFolder . '\\encoded.json', true, []);
            if(!is_array($doneFiles) || !array_is_list($doneFiles)){
                mklog(2,'FolderEncode: Failed to read encoded files list');
                $doneFiles = [];
            }

            $files = ($recursive ? files::globRecursive($sourceFolder . "\\", "*.*") : glob($sourceFolder . "\\*"));

            foreach($files as $file){

                if(is_file("temp/video_encoder/stop")){
                    mklog(1,"FolderEncode: Stop file found, stopping");
                    return $return;
                }

                if(self::isVideo($file,$videoTypes)){
                    if(in_array(strtolower($file), $doneFiles)){
                        mklog(1,"FolderEncode: Skipping " . $file);
                        continue;
                    }

                    $videoInfo = self::getVideoInfo($file);
                    if(!isset($videoInfo['format']['filename']) || !isset($videoInfo['format']['bit_rate']) || !isset($videoInfo['streams'])){
                        mklog(2,"FolderEncode: " . $file . " Is broken or unreadable");
                        continue;
                    }
                    
                    if(settings::read('allowCinelikeDSaturationModification')){
                        if(self::isCinelikeD($file,$videoInfo)){
                            if(!isset($encodeOptions['saturation'])){
                                $saturationModified = true;
                                $encodeOptions['saturation'] = 1.4;
                            }
                        }
                    }

                    $someNumber = time::millistamp();
                    $fileExtenstion = files::getFileExtension($file);
                    $tempPath = str_replace($fileExtenstion,$someNumber . "_TEMP." . $outFileExtension,$file);
                    $outPath = str_replace($sourceFolder,$destinationFolder,$tempPath);

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
                        $finishFunctionString .= ($deleteSourceAfter ? 'true' : 'false') . ',';
                        $finishFunctionString .= $someNumber . ',';
                        $finishFunctionString .= '"' . files::validatePath($jobFolder,false) . '");';
                        $finishFunctionString = str_ireplace("\\","\\\\",$finishFunctionString);

                        $conductorJob = conductor_client::addJob("127.0.0.1",$functionString,52000,$finishFunctionString);
                        if(is_string($conductorJob)){
                            mklog(1,'FolderEncode: Added job ' . $conductorJob . ' to conductor 127.0.0.1:52000');
                            $return = true;
                        }
                        else{
                            mklog(2,'FolderEncode: Failed to add job to conductor 127.0.0.1:52000');
                        }
                    }
                    else{
                        $encodeSuccess = self::encode_video($file, $outPath, $encodeOptions);

                        if(self::afterFolderEncode($encodeSuccess, $file, $outPath, $deleteSourceAfter, $someNumber, $jobFolder)){
                            $return = true;
                        }
                    }

                    if($saturationModified){
                        unset($encodeOptions['saturation']);
                        $saturationModified = false;
                    }
                }
            }
        }
        return $return;
    }
    public static function afterFolderEncode($encodeSuccess,$file,$outPath,$deleteSourceAfter,$someNumber,$jobFolder):bool{

        if(!is_string($file)){
            mklog(2,"FolderEncode: Unable to finalize encode (typeError) for an unknown file");
            return false;
        }

        if(!is_bool($encodeSuccess) || !is_string($outPath) || !is_bool($deleteSourceAfter) || (!is_string($someNumber) && !is_int($someNumber)) || !is_string($jobFolder)){
            mklog(2,"FolderEncode: Unable to finalize encode (typeError) for " . $file);
            return false;
        }

        if(!$encodeSuccess){
            mklog(2,"FolderEncode: Unable to complete encode for " . $file);
            return false;
        }

        $fileSize = filesize($file);
        $outPathSize = filesize($outPath);
        $gigabyte = 1024**3;

        $percentage = round(($outPathSize/$fileSize)*100,1);
        $GLOBALS['videoEncoderAveragePercentages'][] = $percentage;
        if(count($GLOBALS['videoEncoderAveragePercentages']) > 20){
            array_shift($GLOBALS['videoEncoderAveragePercentages']);
        }
        $averagePercentage = round(math::average($GLOBALS['videoEncoderAveragePercentages']),1);

        mklog(1,"FolderEncode: Encoded file (" . round($fileSize/$gigabyte,1) . " GB => " . round($outPathSize/$gigabyte,1) . " GB) (" . $percentage . "% of original size, avg:" . $averagePercentage . "%) " . $file);

        if($deleteSourceAfter){
            if(!unlink($file)){
                mklog(2,"FolderEncode: Unable to remove source " . $file);
            }
        }

        $finalOutPath = str_replace($someNumber . "_TEMP.","",$outPath);
        if(!rename($outPath,$finalOutPath)){
            mklog(2,"FolderEncode: Unable to rename temporary file to " . $file);
            return false;
        }

        $encodedList = $jobFolder . "\\encoded.json";
        $currentList = json::readFile($encodedList,true,[]);
        if(!is_array($currentList)){
            mklog(2,'FolderEncode: Failed to load encoded files list, resetting list');
            $currentList = [];
        }
        $currentList[] = strtolower($file);
        if(!json::writeFile($encodedList, $currentList, true)){
            mklog(2,'FolderEncode: Failed to save encoded files list');
        }

        return true;
    }
    public static function getFolderLength(string $sourceFolder, bool $recursive=false, array $videoTypes=["mp4","mov","mkv","avi"]):int|false{
        $return = false;
        if(is_dir($sourceFolder)){
            $length = 0;

            echo "Searching for files...\n";
            $files = ($recursive ? files::globRecursive($sourceFolder . "\\", "*.*") : glob($sourceFolder . "\\*"));

            foreach($files as $file){
                if(is_file($file)){
                    if(self::isVideo($file, $videoTypes)){

                        echo "Scanning file " . $file . "\n";
                        $videoInfo = self::getVideoInfo($file);
                        
                        if(!is_array($videoInfo) || !isset($videoInfo['format']['duration'])){
                            mklog(2, $file . " Is broken or unreadable");
                            continue;
                        }

                        $length += round($videoInfo['format']['duration']);

                        echo "Length: " . floor($length / 3600) . gmdate(":i:s", $length % 3600) . " \n";

                        $return = intval($length);
                        
                    }
                }
            }
        }
        return $return;
    }
    public static function isVideo(string $path, array $videoTypes=["mp4","mov","mkv","avi"]):bool{
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
    public static function isMedia(string $path):bool{
        $info = self::getVideoInfo($path);
        return (is_array($info) && isset($info['format']['duration']));
    }
    public static function getVideoInfo(string $path):array|bool{
        return self::ffprobeJson($path, '-show_format -show_streams');
    }
    public static function getVideoKeyframeInfo(string $path):array|bool{
        return self::ffprobeJson($path, '-select_streams v:0 -show_entries frame=pkt_pts_time,pict_type -show_frames -skip_frame nokey');
    }
    public static function getInfoCodec(array $videoInfo, string $codecType="video"):string|false{
        if(!isset($videoInfo['streams']) || !is_array($videoInfo['streams'])){
            return false;
        }

        foreach($videoInfo['streams'] as $stream){
            if(is_array($stream)){
                if(isset($stream['codec_type']) && $stream['codec_type'] === strtolower($codecType)){
                    if(isset($stream['codec_name']) && is_string($stream['codec_name'])){
                        return $stream['codec_name'];
                    }
                }
            }
        }

        return false;
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
    public static function createPreset(string $name, array $options=[], $overwrite=true):bool{
        $options = self::validateOptions($options);
        $path = self::presetPath($name);
        if(!is_string($path)){
            return false;
        }
        return json::writeFile($path, $options, $overwrite);
    }
    public static function cut(string $inPath, string $outPath, int|float|false $startTime=false, int|float|false $length=false):bool{
        if(!is_file($inPath)){
            mklog(2,'Input file does not exist');
            return false;
        }

        if(is_file($outPath)){
            mklog(2,'Output file already exists');
            return false;
        }

        if(!files::ensureFolder(files::getFileDir($outPath))){
            mklog(2,'Failed to ensure output files directory');
            return false;
        }

        $ffmpegPath = e_ffmpeg::path('ffmpeg');
        if(!is_string($ffmpegPath)){
            mklog(2,'Failed to find ffmpeg binaries');
            return false;
        }

        $command = files::validatePath($ffmpegPath,true) . ' ';

        if($startTime !== false){
            if($startTime < 0){
                mklog(2,'Cannot start from a negetive number');
                return false;
            }

            echo "Identifying source...\n";
            $sourceKeyframes = self::getVideoKeyframeInfo($inPath);
            if(!is_array($sourceKeyframes) || !isset($sourceKeyframes['frames'])){
                mklog(2,'Failed to get keyframes of source');
                return false;
            }

            $nextKeyframeTime = 0;
            foreach($sourceKeyframes['frames'] as $frameData){
                if(isset($frameData['pts_time'])){
                    $nextKeyframeTime = floatval($frameData['pts_time']);
                    if($nextKeyframeTime > $startTime){
                        break;
                    }
                }
            }

            if($nextKeyframeTime == 0){
                mklog(2,'Failed to locate close keyframe');
                return false;
            }

            $originalInfo = self::getVideoInfo($inPath);

            $videoCodec = self::getInfoCodec($originalInfo, 'video');
            $audioCodec = self::getInfoCodec($originalInfo, 'audio');

            if(!is_string($videoCodec) || !is_string($audioCodec)){
                mklog(2,'Failed to identify source codec');
                return false;
            }

            $videoReplacements = [
                'av1' => 'libaom-av1 -crf 28 -cpu-used 5'
            ];
            if(isset($videoReplacements[$videoCodec])){
                $videoCodec = $videoReplacements[$videoCodec];
            }

            $outPathFullname = substr($outPath,0,strripos($outPath,"."));
            $outPathExtension = files::getFileExtension($outPath);
            $part1path = $outPathFullname . '_part1.' . $outPathExtension;
            $part2path = $outPathFullname . '_part2.' . $outPathExtension;
            $fileslist = $outPathFullname . '_fileslist.txt';

            echo "Cutting first part...\n";

            exec($command . '-ss ' . $startTime . ' -i ' . files::validatePath($inPath,true) . ' -c:v ' . $videoCodec . ' -c:a ' . $audioCodec . ' -to ' . ($nextKeyframeTime - $startTime) . ' -loglevel error ' . files::validatePath($part1path,true) . ' -y');

            if(!self::isMedia($part1path)){
                mklog(2,'Failed to cut first part');
                if(is_file($part1path)){unlink($part1path);}
                return false;
            }

            echo "Cutting second part...\n";

            exec($command . '-ss ' . $nextKeyframeTime . ' -i ' . files::validatePath($inPath,true) . ' -c copy -loglevel error ' . files::validatePath($part2path,true) . ' -y');

            if(!self::isMedia($part2path)){
                mklog(2,'Failed to cut second part');
                if(is_file($part1path)){unlink($part1path);}
                if(is_file($part2path)){unlink($part2path);}
                return false;
            }

            echo "Mixing videos...\n";

            if(!txtrw::mktxt($fileslist, "file '" . str_replace("'","'\''",$part1path) . "'\nfile '" .  str_replace("'","'\''",$part2path) . "'", true)){
                mklog(2,'Failed to write fileslist');
                if(is_file($part1path)){unlink($part1path);}
                if(is_file($part2path)){unlink($part2path);}
                return false;
            }

            $command .= '-f concat -safe 0 -i ' . files::validatePath($fileslist,true) . ' -i ' . files::validatePath($inPath,true) . ' -map 0 -map_metadata 1 -map_chapters -1 ';
        }
        else{
            $command .= '-i ' . files::validatePath($inPath,true) . ' ';
        }

        $command .= '-c copy ';

        if($length !== false){
            if($length < 0){
                if($startTime !== false){
                    if(!isset($originalInfo['format']['duration'])){
                        mklog(2,'Failed to find source length');
                        return false;
                    }
                    $command .= '-to ' . (intval($originalInfo['format']['duration']) - $startTime + $length) . ' ';//Length is negative (original time - start offset - end offset)
                }
                else{
                    $info = self::getVideoInfo($inPath);
                    if(!is_array($info) || !isset($info['format']['duration'])){
                        mklog(2,'Failed to find source length');
                        return false;
                    }
                    $command .= '-to ' . (intval($info['format']['duration']) + $length) . ' ';//Length is negative (original time - end offset)
                }
            }
            else{
                $command .= '-to ' . $length . ' ';
            }
        }

        $command .= '-movflags +faststart -loglevel error ' . files::validatePath($outPath,true) . ' -y';

        exec($command);

        if($startTime !== false){
            if(is_file($part1path)){unlink($part1path);}
            if(is_file($part2path)){unlink($part2path);}
            if(is_file($fileslist)){unlink($fileslist);}
        }

        if(!self::isMedia($outPath)){
            mklog(2,'Failed to cut video');
            if(is_file($outPath) && !@unlink($outPath)){
                mklog(2,'Unable to delete unfinished file ' . $outPath);
            }
            return false;
        }

        return true;
    }
    public static function cutFolder(string $sourceFolder, string $destinationFolder, int|float|false $startTime=false, int|float|false $length=false, bool $recursive=false, array $videoTypes=["mp4","mov","mkv","avi"]):bool{
        if(!is_dir($sourceFolder)){
            mklog(2,'Source folder does not exist');
            return false;
        }
        $return = true;
        $somethingHappened = false;

        $files = ($recursive ? files::globRecursive($sourceFolder . "\\", "*.*") : glob($sourceFolder . "\\*"));
        foreach($files as $file){
            if(is_file("temp/video_encoder/stop")){
                mklog(1,"FolderEncode: Stop file found, stopping");
                break;
            }

            if(!self::isVideo($file,$videoTypes)){
                continue;
            }

            $outPath = str_replace($sourceFolder, $destinationFolder, $file);

            echo "Cutting " . $file . "\n";
            $somethingHappened = true;
            if(!self::cut($file, $outPath, $startTime, $length)){
                $return = false;
            }
        }

        if(!$somethingHappened){
            $return = false;
        }

        return $return;
    }

    private static function makeJobFolderString(string|bool $jobId=false):string{
        if(!is_string($jobId)){
            $jobId = (string) time::stamp();
        }
        return getcwd() . '\\temp\\video_encoder\\folder_jobs\\' . $jobId;
    }
    private static function isCinelikeD(string $path, array|bool $videoInfo=false):bool{
        if($videoInfo === false){
            $videoInfo = self::getVideoInfo($path);
        }
        if(files::getFileExtension($path) === "MOV"){
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
    private static function loadOptions(array $options=[]):array|false{

        if(isset($options['preset']) && is_string($options['preset']) && !empty($options['preset'])){

            $presetOptions = self::loadPreset($options['preset']);

            if(is_array($presetOptions)){
                $options = array_merge($presetOptions, $options);
            }
        }

        $options = self::validateOptions($options);

        return $options;
    }
    private static function validateOptions(array $options=[]):array{
        $defaultOptions = [
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
            "customArgs" => false,
            "commandIntoFile" => false,
            "livePreview" => false,
            "livePreviewWidth" => 69,
            "livePreviewHeight" => 39,
            "2pass" => false
        ];

        $outOptions = $defaultOptions;

        foreach($defaultOptions as $defaultOption => $defaultOptionValue){
            if(isset($options[$defaultOption])){
                $outOptions[$defaultOption] = $options[$defaultOption];
            }
        }
        
        return $outOptions;
    }
    private static function preview(string $command, int $width, int $height):bool{
        $bytesPerFrame = $width * $height * 3;
        if($bytesPerFrame > 8192){//Max pipe buffer
            mklog(2,'Specified preview frame size is too large');
            return false;
        }

        $ffmpegCmd = $command . ' -filter:v "scale=' . $width . ':' . $height . '" -f rawvideo -pix_fmt rgb24 pipe:0';
        
        // Open FFmpeg process with pipes
        $process = proc_open($ffmpegCmd, [['pipe', 'w']], $pipes, null, null, ['create_new_console'=>true]);
        
        if(!is_resource($process)){
            mklog(2,"Failed to open ffmpeg process (preview mode)");
            return false;
        }
        
        cli_pixels::set_screen_size($width,$height);
        
        while(true){
            // Read from FFmpeg stdout
            $data = fread($pipes[0], $bytesPerFrame);
            if($data !== false){
                if(!cli_pixels::showRgbFrame($data, $width, $height)){
                    echo "No more data\n";
                    break;
                }
            }
        }

        // Close
        fclose($pipes[0]);
        $exitCode = proc_close($process);
        
        if($exitCode !== 0){
            mklog(2,"Ffmpeg did not exit properly (preview mode)");
            return false;
        }

        return true;
    }
    private static function customArgsAllow2pass(string $args):bool{
        if(!empty($args)){
            $argsNoSpaces = str_replace(" ", "", $args);
            if(strpos($argsNoSpaces, '-c:vcopy') !== false || strpos($argsNoSpaces, '-codecvideocopy') !== false || strpos($argsNoSpaces, '-vcodeccopy') !== false || strpos($argsNoSpaces, '-ccopy') !== false){
                return false;
            }
        }
        return true;
    }
    private static function ffprobeJson(string $file, string $args):array|false{
        $ffprobePath = e_ffmpeg::path("ffprobe");
        if(!is_string($ffprobePath)){
            return false;
        }

        $result = shell_exec(files::validatePath($ffprobePath,true) . ' -v quiet ' . $args . ' -print_format json ' . files::validatePath($file,true));
        if(!is_string($result)){
            return false;
        }

        $json = json_decode($result,true);
        if(!is_array($json) || empty($json)){
            return false;
        }
        
        return $json;
    }
}