<?php
class video_encoder{
    private static $nvCapabilities = null;
    public static function init():void{
        $defaultSettings = [
            "presetsPath" => "videoencoder/presets",
            "copyFilesToLocal" => false,
            "allowNVDEC" => true,
            "allowNVENC" => true
        ];
        foreach($defaultSettings as $dsName => $dsValue){
            settings::set($dsName, $dsValue, false);
        }

        if(!self::doesPresetExist("default")){
            self::createPreset("default",[],[],[],[]);
        }

        cli::registerAlias("ve", "video_encoder");

        self::$nvCapabilities = self::nvCardCapabilities();
    }
    public static function command($line):void{
        $command = cli::parseLine($line);
        $commandName = strtolower(array_shift($command['args']));

        if(in_array($commandName, ['help','h'])){
            echo "Openning https://github.com/tomgriffiths-net/video_encoder\n";
            exec('start "" "https://github.com/tomgriffiths-net/video_encoder"');
        }
        elseif(in_array($commandName, ['mediainfo','mi'])){
            if(!isset($command['args'][0])){
                echo "Error: You need to specify an input file.\n";
                return;
            }

            $info = self::getVideoInfo($command['args'][0]);
            if(!is_array($info) || !isset($info['format'])){
                echo "Failed to get video info.\n";
                return;
            }

            if(in_array("showstreams", $command['options']) || in_array("ss", $command['options'])){
                echo json_encode($info, JSON_PRETTY_PRINT) . "\n";
            }
            else{
                echo json_encode($info['format'], JSON_PRETTY_PRINT) . "\n";
            }
        }
        elseif(in_array($commandName, ['cropdetect','cd'])){
            if(!isset($command['args'][0])){
                echo "Error: You need to specify an input file.\n";
                return;
            }
            
            $info = self::detectVideoCrop($command['args'][0]);
            if(!is_array($info)){
                echo "Failed to get crop information.\n";
                return;
            }
            echo json_encode($info, JSON_PRETTY_PRINT) . "\n";
        }
        elseif(in_array($commandName, ['runpreset','rp'])){
            $inputs = [];
            $outputs = [];
            $options = [];

            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No preset specified\n";
                return;
            }
            $options['preset'] = $command['args'][0];

            if(isset($command['params']['in']) && !empty($command['params']['in'])){
                $inputs[0]['path'] = $command['params']['in'];
            }
            if(isset($command['params']['out']) && !empty($command['params']['out'])){
                $outputs[0]['path'] = $command['params']['out'];
            }

            if(!self::encodeVideo($inputs, $outputs, $options)){
                echo "Failed\n";
                return;
            }
        }
        elseif(in_array($commandName, ['runpresetfolder','rpf'])){
            $sourceFolder = "";
            $destinationFolder = "";
            $options = [];
            $encodeOptions = [];

            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No preset specified\n";
                return;
            }
            $options['preset'] = $command['args'][0];
            $encodeOptions['preset'] = $command['args'][0];

            if(isset($command['params']['in']) && !empty($command['params']['in'])){
                $sourceFolder = $command['params']['in'];
            }
            if(isset($command['params']['out']) && !empty($command['params']['out'])){
                $destinationFolder = $command['params']['out'];
            }

            if(!self::encodeFolder($sourceFolder, $destinationFolder, [], [], $encodeOptions, $options)){
                echo "Failed\n";
                return;
            }
        }
        elseif(in_array($commandName, ['folderlength','fl'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No source folder specified\n";
                return;
            }

            $length = self::getFolderLength($command['args'][0], (in_array("r", $command['options']) || in_array("recursive", $command['options'])));
            if(!is_int($length)){
                echo "Failed\n";
                return;
            }
            
            echo "Final seconds: " . $length . "\n";
        }
        elseif(in_array($commandName, ['ismedia','im'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No file specified\n";
                return;
            }

            echo (self::isMedia($command['args'][0]) ? "YES" : "NO") . "\n";
        }
        elseif(in_array($commandName, ['codecname','cn'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No file specified\n";
                return;
            }

            $streamType = "video";
            $streamIndex = 0;

            if(isset($command['args'][1])){
                if(empty($command['args'][1]) || !in_array($command['args'][1], ['video','audio','subtitle','data'])){
                    echo "Error: Invalid stream type\n";
                    return;
                }
                $streamType = $command['args'][1];
            }
            if(isset($command['args'][2])){
                if($command['args'][2] === "" || !is_numeric($command['args'][2]) || strpos($command['args'][2], ".") !== false){
                    echo "Error: Invalid stream type index\n";
                    return;
                }
                $streamIndex = intval($command['args'][2]);
            }

            $info = self::getVideoInfo($command['args'][0]);
            if(!is_array($info)){
                echo "Failed to read media information\n";
                return;
            }

            $codecName = self::getCodecName($info, $streamType, $streamIndex);
            if(!is_string($codecName) || empty($codecName)){
                echo "Failed to get codec name for $streamType $streamIndex\n";
                return;
            }

            echo $codecName . "\n";
        }
        elseif(in_array($commandName, ['streammap','sm'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No file specified\n";
                return;
            }

            $info = self::getVideoInfo($command['args'][0]);
            if(!is_array($info)){
                echo "Failed to read media information\n";
                return;
            }

            $map = self::getStreamMap($info);
            if(empty($info)){
                echo "Failed to generate map\n";
                return;
            }

            if(in_array("j", $command['options']) || in_array("json", $command['options'])){
                echo json_encode($map, JSON_PRETTY_PRINT) . "\n";
                return;
            }

            foreach($map as $type => $typeInfo){
                foreach($typeInfo as $typeIndex => $realIndex){
                    echo $type . " " . $typeIndex . " -> " . $realIndex . "\n";
                }
            }
        }
        elseif(in_array($commandName, ['presetexists','pe'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No preset name specified\n";
                return;
            }

            if(self::doesPresetExist($command['args'][0])){
                echo "YES\n";
            }
            else{
                echo "NO\n";
            }
        }
        elseif(in_array($commandName, ['viewpreset','vp'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No preset name specified\n";
                return;
            }

            $preset = self::loadPreset($command['args'][0]);
            if(!is_array($preset)){
                echo "Failed to load preset " . $command['args'][0] . "\n";
            }

            echo json_encode($preset, JSON_PRETTY_PRINT) . "\n";
        }
        elseif(in_array($commandName, ['createpreset','cp'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No preset name specified\n";
                return;
            }

            $overwrite = (in_array("ow", $command['options']) || in_array("overwrite", $command['options']));
            if(self::doesPresetExist($command['args'][0]) && $overwrite === false){
                echo "Failed, the specified preset already exists and overwrite is false\n";
                return;
            }

            if(self::createPreset($command['args'][0], [], [], [], [], true)){
                echo "CREATED\n";
            }
            else{
                echo "FAILED\n";
            }
        }
        elseif(in_array($commandName, ['cut','c','cutfolder','cf'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No source file specified\n";
                return;
            }
            if(!isset($command['args'][1]) || empty($command['args'][1])){
                echo "Error: No destination file specified\n";
                return;
            }

            $startTime = "0";
            $duration = "0";

            if(isset($command['params']['s'])){
                $startTime = $command['params']['s'];
            }
            if(isset($command['params']['start'])){
                $startTime = $command['params']['start'];
            }
            $startTime = floatval($startTime);
            if($startTime === 0){
                $startTime = false;
            }

            if(isset($command['params']['d'])){
                $duration = $command['params']['d'];
            }
            if(isset($command['params']['duration'])){
                $duration = $command['params']['duration'];
            }
            $duration = floatval($duration);
            if($duration === 0){
                $duration = false;
            }

            if(in_array($commandName, ['cut','c'])){
                $success = self::cut($command['args'][0], $command['args'][1], $startTime, $duration);
            }
            else{
                $recursive = (in_array("r", $command['options']) || in_array("recursive", $command['options']));
                $success = self::cutFolder($command['args'][0], $command['args'][1], $startTime, $duration, $recursive);
            }

            if($success){
                echo "SUCCESS\n";
            }
            else{
                echo "FAILED\n";
            }
        }
        elseif(in_array($commandName, ['getbitrate','gb'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No file name specified\n";
                return;
            }

            $bitrate = self::getVideoBitrate($command['args'][0]);
            if(!is_int($bitrate)){
                echo "Failed to read media files total bitrate\n";
                return;
            }

            echo round($bitrate/1024) . " Kbps\n";
        }
        elseif(in_array($commandName, ['vmaf2crf','v2c'])){
            if(!isset($command['args'][0]) || empty($command['args'][0])){
                echo "Error: No encoder specified\n";
                return;
            }
            if(!isset($command['args'][1]) || empty($command['args'][1])){
                echo "Error: No speed preset specified\n";
                return;
            }
            if(!isset($command['args'][2]) || empty($command['args'][2])){
                echo "Error: No target vmaf specified\n";
                return;
            }

            $vmaf = floatval($command['args'][2]);
            if($vmaf < 40 || $vmaf > 100){
                echo "Error: Invalid vmaf specified\n";
                return;
            }

            $crf = self::targetVmafToCrf($command['args'][0], $command['args'][1], $vmaf);
            if(!is_float($crf)){
                echo "Failed to get crf to generate vmaf " . $vmaf . " for encoder " . $command['args'][0] . " at speed preset " . $command['args'][1] . "\n";
                return;
            }

            $videoFormats = self::videoEncoders();
            if(is_array($videoFormats)){
                if(self::issetAndType($videoFormats, "integerQualitySetting", "list")){
                    if(in_array($command['args'][0], $videoFormats['integerQualitySetting'])){
                        $crf = round($crf);
                    }
                }
            }
            else{
                echo "Warning: Failed to get integer only crf encoders list\n";
            }

            echo $crf . "\n";
        }
        else{
            echo "Invalid command, see https://github.com/tomgriffiths-net/video_encoder or type 'video_encoder help' for help.\n";
            return;
        }
    }
    /**
     * Encodes a video using ffmpeg, see https://github.com/tomgriffiths-net/video_encoder for detailed inputs, outputs, and options information.
     * @param array $inputs The inputs to pass to ffmpeg.
     * @param array $outputs The outputs ffmpeg will produce.
     * @param array $options Extra options.
     * @return bool Weather ffmpeg outputted a valid media file.
     */
    public static function encodeVideo(array $inputs, array $outputs, array $options):bool{
        if(self::issetAndType($options, "allowFileCopy", "boolean") && $options['allowFileCopy'] && settings::read('copyFilesToLocal')){
            return self::copyFilesAndEncode($inputs, $outputs, $options);
        }

        if(!array_is_list($inputs)){
            mklog(2, "Inputs must be a list, converting to a list");
            $inputs = array_values($inputs);
        }
        if(!array_is_list($outputs)){
            mklog(2, "Outputs must be a list, converting to a list");
            $outputs = array_values($outputs);
        }

        if(self::issetAndType($options, "preset", "string")){
            $presetOptions = self::loadPreset($options['preset']);
            if(is_array($presetOptions)){
                $options = array_merge($presetOptions['options'], $options);
            }
            else{
                mklog(2, "Failed to read preset " . $options['preset']);
            }
            unset($presetOptions);
        }

        $command = [e_ffmpeg::path("ffmpeg") ?? 'ffmpeg'];
        
        foreach($inputs as &$input){
            if(is_string($input)){
                $input = ['path'=>$input];
            }
        }
        unset($input);

        $inputInfos = [];

        foreach($inputs as $inputNumber => $input){
            $arguments = [];
            if(!is_array($input) || !self::issetAndType($input, "path", "string")){
                mklog(2, "Input $inputNumber has no path, ignoring input");
                unset($inputs[$inputNumber]);
                continue;
            }

            $inputInfos[$inputNumber] = self::nameStreams(self::getVideoInfo($input['path']) ?? []);
            if(!is_array($inputInfos[$inputNumber]) || empty($inputInfos[$inputNumber])){
                mklog(2, "Failed to get information for input " . $inputNumber);
            }

            foreach(['format'=>'f', 'seek'=>'ss', 'duration'=>'t', 'to'=>'to'] as $inputDataName => $inputCommandOption){
                if(self::issetAndType($input, $inputDataName, "string")){
                    $arguments[] = "-$inputCommandOption";
                    $arguments[] = $input[$inputDataName];
                }
            }
            if(self::issetAndType($input, "loop", "integer")){
                $arguments[] = "-stream_loop";
                $arguments[] = max($input["loop"], -1);
            }
            if(isset($input['fps']) && in_array(gettype($input['fps']), ['integer', 'double', 'string'])){
                if(is_string($input['fps'])){
                    if(preg_match('#^[1-9]\d*/[1-9]\d*$#', $input['fps']) === 1){
                        $arguments[] = "-framerate";
                        $arguments[] = $input["fps"];
                    }
                }
                elseif(floatval($input['fps']) > 0){
                    $arguments[] = "-framerate";
                    $arguments[] = $input["fps"];
                }
            }
            if(self::issetAndType($input, "realTime", "boolean")){
                if($input["realTime"]){
                    $arguments[] = "-re";
                }
            }

            $addCuda = false;
            if(self::issetAndType($options, "allowNVDEC", "boolean") && $options['allowNVDEC'] && settings::read("allowNVDEC")){
                if(self::$nvCapabilities && isset($inputInfos[$inputNumber]['streams']['v'])){
                    foreach($inputInfos[$inputNumber]['streams']['v'] as $probeStream){
                        if(($probeStream['disposition']['attached_pic'] ?? 0) === 1){// cover art, not a real video stream
                            continue;
                        }
                        if(!self::issetAndType($probeStream, "codec_name", "string")){
                            $addCuda = false;
                            break;
                        }
                        if(!self::issetAndType($probeStream, "pix_fmt", "string") || !self::issetAndType($probeStream, "width", "integer") || !self::issetAndType($probeStream, "height", "integer")){
                            $addCuda = false;
                            break;
                        }

                        if(!isset(self::$nvCapabilities['decode'][$probeStream['codec_name']])){
                            $addCuda = false;
                            break;
                        }
                        $codecCaps = self::$nvCapabilities['decode'][$probeStream['codec_name']];

                        $pixelFormat = self::pixFmtInfo($probeStream['pix_fmt']);
                        if(!in_array($pixelFormat['chroma'], ($codecCaps['formats'][$pixelFormat['bits']] ?? []), true)){
                            $addCuda = false;
                            break;
                        }
                        if($probeStream['width'] < 100 || $probeStream['width'] > $codecCaps['maxres'][0] || $probeStream['height'] < 50 || $probeStream['height'] > $codecCaps['maxres'][1]){
                            $addCuda = false;
                            break;
                        }

                        $addCuda = true;
                    }
                }
            }
            if(self::issetAndType($input, "forceNVDEC", "boolean") && $input['forceNVDEC'] && !$addCuda){
                mklog(2, "NVDEC was forced on but the input is suspected to not work with -hwaccel cuda and ffmpeg may fall back to cpu decoding");
                $addCuda = true;
            }
            //-hwaccel cuda is a hint, not a must
            if($addCuda){
                $arguments[] = "-hwaccel";
                $arguments[] = "cuda";
            }

            if(self::issetAndType($input, "customArgs", "list")){
                foreach($input['customArgs'] as $customArg){
                    $arguments[] = self::doCodeTag($customArg, [
                        'inputNumber' => $inputNumber,
                        'inputInfo' => $inputInfos[$inputNumber]
                    ]);
                }
            }

            $arguments[] = "-i";
            $arguments[] = $input['path'];

            $command = array_merge($command, $arguments);
        }
        if(empty($inputs)){
            mklog(3, "There are no valid inputs, aborting");
            return false;
        }

        if(self::issetAndType($options, "complexFilter", "string")){
            $command[] = "-filter_complex";
            $command[] = $options["complexFilter"];
        }

        $extensionsAndFormats = self::extensionsAndFormats();
        $videoFormats = self::videoEncoders();
        
        foreach($outputs as $outputNumber => $output){
            $arguments = [];

            if(!is_array($output) || !self::issetAndType($output, "path", "string")){
                mklog(2, "Output $outputNumber has no path, ignoring output");
                unset($output[$outputNumber]);
                continue;
            }

            files::ensureFolder(dirname($output['path']));
            
            $overwrite = self::issetAndType($options, "overwrite", "boolean") && $options['overwrite'];
            if(!$overwrite && file_exists($output['path'])){
                mklog(3, "The file " . basename($output['path']) . " (output $outputNumber) already exists and overwrite is set to false");
                return false;
            }

            $fileFormat = '';
            $addFormatFlag = false;
            if(self::issetAndType($output, "format", "string")){
                $fileFormat = strtolower($output['format']);
                $addFormatFlag = true;
            }
            else{
                $outputFileExtension = strtolower(pathinfo($output['path'], PATHINFO_EXTENSION));
                if(!empty($outputFileExtension)){
                    $fileFormat = $outputFileExtension;
                    if(isset($extensionsAndFormats['extensionToFormat'][$fileFormat])){
                        $fileFormat = $extensionsAndFormats['extensionToFormat'][$fileFormat];
                    }
                }
                if(empty($fileFormat)){
                    mklog(2, "No file format information available for output $outputNumber, ignoring output");
                    continue;
                }
            }

            if(!self::issetAndType($output, "streams", "list")){
                mklog(2, "Output $outputNumber has no streams list, ignoring output");
                unset($output[$outputNumber]);
                continue;
            }

            //Sort out per type indexes in streams array
            $n = ['v'=>0, 'a'=>0, 's'=>0, 'd'=>0];
            $originalOutputStreams = $output['streams'];
            $output['streams'] = [];
            foreach($originalOutputStreams as $streamNumber => $stream){
                if(!is_array($stream) || !self::issetAndType($stream, "type", "string") || !in_array(strtolower($stream['type']), ['video','v','audio','a','subtitles','s','data','d'])){
                    mklog(2, "Output $outputNumber stream $streamNumber has no valid type");
                    continue;
                }
                $stream['type'] = strtolower(substr($stream['type'], 0, 1));

                if(self::issetAndType($stream, "source", "string")){
                    $stream['typeIndex'] = $n[$stream['type']];
                    $n[$stream['type']]++;
                    $output['streams'][] = $stream;
                    continue;
                }
                
                if(!self::issetAndType($stream, "source", "list")){
                    $stream['source'] = [0, -1, true];
                }
                if(!self::issetAndType($stream['source'], 1, "integer")){
                    $stream['source'][1] = -1;
                }
                if(!self::issetAndType($stream['source'], 2, "boolean")){
                    $stream['source'][2] = true;
                }

                $refrencedInputs = [];
                $sources = [];

                //Expand -1 input
                if($stream['source'][0] < 0){
                    foreach($inputInfos as $inputNumber => $inputInfo){
                        $refrencedInputs[] = $inputNumber;
                    }
                }
                else{
                    $refrencedInputs[] = $stream['source'][0];
                }

                //Expand -1 stream
                foreach($refrencedInputs as $refrencedInput){
                    if($stream['source'][1] < 0){
                        foreach($inputInfos[$refrencedInput]['streams'][$stream['type']] ?? [] as $inputStreamNumber => $inputStream){
                            $sources[] = [$refrencedInput, $inputStreamNumber, $stream['source'][2], true];
                        }
                    }
                    else{
                        $sources[] = [$refrencedInput, $stream['source'][1], $stream['source'][2]];
                    }
                }

                //remove optionals not existing and add sources to streams
                foreach($sources as $source){
                    if(!isset($inputInfos[$source[0]]['streams'][$stream['type']][$source[1]])){
                        if(!$source[2]){
                            mklog(3, "Input " . $source[0] . " stream " . $stream['type'] . $source[1] . " was selected and forced in output " . $outputNumber . " stream " . $streamNumber . " (" . $stream['type'] . $n[$stream['type']] . ") but the source stream does not exist");
                            return false;
                        }
                        continue;
                    }

                    //Make sure duplicates from globs are removed
                    foreach($output['streams'] as $existingOutputStreamNumber => $existingOutputStream){
                        if($existingOutputStream['source'][0] === $source[0] && $existingOutputStream['source'][1] === $source[1]){//existing stream has the same source
                            if(isset($existingOutputStream['source'][3])){//existing stream source was from a glob
                                if(!isset($source[3])){//new stream was not a glob, overwrite.
                                    unset($output['streams'][$existingOutputStreamNumber]);
                                }
                            }
                        }
                    }

                    $stream['source'] = [$source[0], $source[1]];
                    $stream['typeIndex'] = $n[$stream['type']];
                    $n[$stream['type']]++;

                    if(isset($source[3]) && ($inputInfos[$source[0]]['streams'][$stream['type']][$source[1]]['disposition']['attached_pic'] ?? 0) === 1){
                        $stream1 = $stream;
                        $stream1['format'] = "copy";
                        $output['streams'][] = $stream1;
                    }
                    else{
                        $output['streams'][] = $stream;
                    }
                }
            }

            //Make map
            foreach($output['streams'] as $streamNumber => $stream){
                $arguments[] = "-map";

                if(is_array($stream['source'])){
                    $arguments[] = $stream['source'][0] . ":" . $stream['type'] . ":" . $stream['source'][1];
                }
                else{
                    $arguments[] = "[" . $stream['source'] . "]";
                }
            }
            //Do filter and pix_fmt
            foreach($output['streams'] as $streamNumber => $stream){
                if(!in_array($stream['type'], ['v','a'])){
                    continue;
                }

                if(is_array($stream['source'])){
                    $filter = "";

                    if($stream['type'] === "v"){
                        if(isset($stream['fps']) && in_array(gettype($stream['fps']), ['integer', 'double', 'string'])){
                            if(is_string($stream['fps'])){
                                if(preg_match('#^[1-9]\d*/[1-9]\d*$#', $stream['fps']) === 1){
                                    $filter .= "fps=" . $stream['fps'] . ",";
                                }
                            }
                            elseif(floatval($stream['fps']) > 0){
                                $filter .= "fps=" . $stream['fps'] . ",";
                            }
                        }

                        foreach(['resW','resH'] as $thing){
                            if(!self::issetAndType($stream, $thing, "integer") || $stream[$thing] < 50){
                                $stream[$thing] = -2;
                            }
                        }
                        if($stream['resW'] > 49 || $stream['resH'] > 49){
                            $filter .= "scale=" . $stream['resW'] . ":" . $stream['resH'] . ",";
                        }
                    }

                    if(self::issetAndType($stream, "filter", "string")){
                        $filter .= $stream['filter'];
                    }

                    $filter = rtrim($filter, ',');

                    if(!empty($filter)){
                        $arguments[] = "-filter:" . $stream['type'] . ":" . $stream['typeIndex'];
                        $arguments[] = $filter;
                    }
                    unset($filter);
                }

                //pix_fmt
                if($stream['type'] === "v"){
                    if(!self::issetAndType($stream, "pixFmt", "string")){
                        $bitsSet = self::issetAndType($stream, "bits", "integer") && in_array($stream['bits'], [8,10,12]);
                        $chromaSet = self::issetAndType($stream, "chroma", "string") && in_array($stream['chroma'], ['420','422','444']);
                        if($bitsSet || $chromaSet){
                            if(!$bitsSet){
                                mklog(1, "Assuming 8 bit video for output $outputNumber video " . $stream['typeIndex']);
                                $stream['bits'] = 8;
                            }
                            if(!$chromaSet){
                                mklog(1, "Assuming 420 chroma video for output $outputNumber video " . $stream['typeIndex']);
                                $stream['chroma'] = '420';
                            }

                            $stream['pixFmt'] = "yuv" . $stream['chroma'] . "p" . ($stream['bits'] > 8 ? $stream['bits'] . "le" : "");
                        }
                    }

                    if(self::issetAndType($stream, "pixFmt", "string")){
                        $arguments[] = "-pix_fmt:v:" . $stream['typeIndex'];
                        $arguments[] = $stream['pixFmt'];
                    }
                }
            }
            //Do encoder stuff
            foreach($output['streams'] as $streamNumber => $stream){
                if($stream['type'] === "d"){
                    if(isset($stream['format']) && $stream['format'] !== "copy"){
                        mklog(1, "Data streams can only be copied, setting output $outputNumber data stream " . $stream['typeIndex'] . " to copy");
                    }

                    $arguments[] = "-c:d:" . $stream['typeIndex'];
                    $arguments[] = "copy";

                    continue;
                }
                elseif($stream['type'] === "s"){
                    if(!self::issetAndType($stream, "format", "string")){
                        $stream['format'] = "copy";
                    }

                    if(in_array($fileFormat, $extensionsAndFormats['movFamilyFormats'])){
                        if($stream['format'] !== "mov_text"){
                            mklog(1, "Mov family formats can only hold mov_text subtitles, setting output $outputNumber subtitles " . $stream['typeIndex'] . " to mov_text");
                        }
                        $stream['format'] = "mov_text";
                    }

                    if($fileFormat === "webm"){
                        if($stream['format'] !== "webvtt"){
                            mklog(1, "WebM can only hold webvtt subtitles, setting output $outputNumber subtitles " . $stream['typeIndex'] . " to webvtt");
                        }
                        $stream['format'] = "webvtt";
                    }

                    $arguments[] = "-c:s:" . $stream['typeIndex'];
                    $arguments[] = $stream['format'];

                    continue;
                }
                elseif($stream['type'] === "a"){
                    if(!self::issetAndType($stream, "format", "string")){
                        $stream['format'] = ($fileFormat === "webm" ? "libopus" : "aac");
                    }
                    $arguments[] = "-c:a:" . $stream['typeIndex'];
                    $arguments[] = $stream['format'];
                    if($stream['format'] === "copy"){
                        continue;
                    }

                    if(self::issetAndType($stream, "channels", "integer")){
                        $arguments[] = "-ac:a:" . $stream['typeIndex'];
                        $arguments[] = $stream['channels'];
                    }
                    if(self::issetAndType($stream, "samples", "intfloat")){
                        $arguments[] = "-ar:a:" . $stream['typeIndex'];
                        $arguments[] = $stream['samples'] . 'k';
                    }
                    if(self::issetAndType($stream, "bitrate", "integer")){
                        $arguments[] = "-b:a:" . $stream['typeIndex'];
                        $arguments[] = $stream['bitrate'] . 'k';
                    }
                }
                elseif($stream['type'] === "v"){
                    if(!self::issetAndType($stream, "format", "string")){
                        $stream['format'] = ($fileFormat === "webm" ? "av1" : "h264");
                    }

                    if($stream['format'] === "copy"){
                        $arguments[] = "-c:v:" . $stream['typeIndex'];
                        $arguments[] = "copy";
                        continue;
                    }

                    if(isset($videoFormats['nicknames'][$stream['format']])){
                        $stream['format'] = $videoFormats['nicknames'][$stream['format']];
                    }

                    if(!self::issetAndType($stream, "encoder", "string")){
                        if(self::issetAndType($options, "allowNVENC", "boolean") && $options['allowNVENC'] && self::$nvCapabilities && settings::read("allowNVENC")){
                            if(isset($videoFormats['formatToEncoder'][$stream['format']]['nvenc']) && isset(self::$nvCapabilities['encode'][$stream['format']])){
                                $sourceInfo = null;
                                if(is_array($stream['source'])){
                                    $sourceInfo = $inputInfos[$stream['source'][0]]['streams'][$stream['type']][$stream['source'][1]];
                                }
                                $eventuals = [];

                                foreach(['pixFmt'=>'string', 'width'=>'integer', 'height'=>'integer'] as $checkName => $checkType){
                                    if(self::issetAndType($stream, $checkName, $checkType)){
                                        $eventuals[$checkName] = $stream[$checkName];
                                    }
                                    else{
                                        $eventuals[$checkName] = $sourceInfo[($checkName === "pixFmt" ? "pix_fmt" : $checkName)] ?? null;
                                    }
                                }

                                $nvencCaps= self::$nvCapabilities['encode'][$stream['format']];

                                if($eventuals['pixFmt'] && $eventuals['width'] && $eventuals['height']){
                                    if($nvencCaps['maxres'][0] >= $eventuals['width'] && $nvencCaps['maxres'][1] >= $eventuals['height']){
                                        $pixFmtInfo = self::pixFmtInfo($eventuals['pixFmt']);
                                        if(is_string($pixFmtInfo['chroma']) && is_int($pixFmtInfo['bits'])){
                                            if(in_array($pixFmtInfo['chroma'], $nvencCaps['formats'][$pixFmtInfo['bits']] ?? [], true)){
                                                $stream['encoder'] = $videoFormats['formatToEncoder'][$stream['format']]['nvenc'];
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if(!self::issetAndType($stream, "encoder", "string")){
                            if(isset($videoFormats['formatToEncoder'][$stream['format']]['cpu'])){
                                $stream['encoder'] = $videoFormats['formatToEncoder'][$stream['format']]['cpu'];
                            }
                            else{
                                $stream['encoder'] = $stream['format'];
                            }
                        }
                    }
                    $arguments[] = "-c:v:" . $stream['typeIndex'];
                    $arguments[] = $stream['encoder'];

                    if(self::issetAndType($videoFormats['qualityExtraOptions'] ?? [], $stream['encoder'], "list")){
                        foreach($videoFormats['qualityExtraOptions'][$stream['encoder']] as $extraSetting){
                            if(substr($extraSetting,0,1) === "-"){
                                $arguments[] = $extraSetting . ":v:" . $stream['typeIndex'];
                            }
                            else{
                                $arguments[] = $extraSetting;
                            }
                        }
                    }
                    if(self::issetAndType($stream, "maxRate", "integer")){
                        $arguments[] = "-maxrate:v:" . $stream['typeIndex'];
                        $arguments[] = $stream['maxRate'] . "k";
                        $arguments[] = "-bufsize:v:" . $stream['typeIndex'];
                        $arguments[] = $stream['maxRate']*2 . "k";
                    }

                    if(!self::issetAndType($stream, "quality", "numeric")){
                        $stream['quality'] = 95;
                    }
                    if(!self::issetAndType($stream, "speed", "string") || !in_array(strtolower($stream['speed']), ["slower","slow","medium","fast","faster"])){
                        $stream['speed'] = "medium";
                    }
                    $stream['quality'] = floatval($stream['quality']);
                    $stream['speed'] = strtolower($stream['speed']);

                    $qualitySetting = "crf";
                    if(self::issetAndType($videoFormats['notCrfEncoders'] ?? [], $stream['encoder'], "string")){
                        $qualitySetting = $videoFormats['notCrfEncoders'][$stream['encoder']];
                    }
                    $qualitySettingValue = self::targetVmafToCrf($stream['encoder'], $stream['speed'], $stream['quality']);
                    if($qualitySettingValue === null){
                        mklog(3, "Could not find quality information for encoder " . $stream['encoder'] . " at speed " . $stream['speed']);
                        return false;
                    }
                    if(self::issetAndType($videoFormats, "integerQualitySetting", "list")){
                        if(in_array($stream['encoder'], $videoFormats['integerQualitySetting'])){
                            $qualitySettingValue = round($qualitySettingValue);
                        }
                    }
                    $arguments[] = "-$qualitySetting:" . $stream['type'] . ":" . $stream['typeIndex'];
                    $arguments[] = $qualitySettingValue;

                    $videoPresets = json::readFile("packages/video_encoder/videoEncoderPresets.json");
                    if(!is_array($videoPresets) || !isset($videoPresets[$stream['encoder']][$stream['speed']]) || !is_string($videoPresets[$stream['encoder']][$stream['speed']])){
                        mklog(3, "Could not find speed preset information for encoder " . $stream['encoder'] . " at speed " . $stream['speed']);
                        return false;
                    }
                    $speedArgs = $videoPresets[$stream['encoder']][$stream['speed']];
                    if(strpos($stream['encoder'], "nvenc") !== false){
                        $features = [];
                        $maxBf = 0;
                        if(is_array(self::$nvCapabilities)){
                            if(isset(self::$nvCapabilities['encode'][$stream['format']]['features']) && is_array(self::$nvCapabilities['encode'][$stream['format']]['features'])){
                                $features = self::$nvCapabilities['encode'][$stream['format']]['features'];
                            }
                            if(isset(self::$nvCapabilities['encode'][$stream['format']]['max_bframes']) && is_int(self::$nvCapabilities['encode'][$stream['format']]['max_bframes'])){
                                $maxBf = self::$nvCapabilities['encode'][$stream['format']]['max_bframes'];
                            }
                        }
                        
                        if(strpos($speedArgs, "-tune uhq") !== false && !in_array("tune_uhq", $features)){
                            $speedArgs = str_replace("-tune uhq", "-tune hq", $speedArgs);
                        }

                        if(preg_match('/-bf\s+(\d+)/', $speedArgs, $matches, PREG_OFFSET_CAPTURE)){
                            $fullMatch = $matches[0][0];   // "-bf 3"
                            //$startPos  = $matches[0][1];   // offset where "-bf 3" starts
                            //$endPos    = $startPos + strlen($fullMatch); // offset where it ends

                            $bframeValue = intval($matches[1][0]); // just 3
                            //$valueStart  = $matches[1][1]; // offset where "3" starts

                            if($maxBf < $bframeValue){
                                mklog(2, "Specified bframes over nvenc limit, reducing to " . $maxBf);
                                $speedArgs = str_replace($fullMatch, "-bf " . $maxBf, $speedArgs);
                            }
                        }
                        if(strpos($speedArgs, "-bf 0") !== false){
                            $speedArgs = str_replace("-bf 0", "", $speedArgs);
                        }

                        $speedArgsExplode = explode(' -', preg_replace('/\s+/', ' ', trim($speedArgs, " -")));
                        $speedArgs = [];
                        foreach($speedArgsExplode as $speedArg){
                            $spacePos = strpos($speedArg, " ");
                            $name = substr($speedArg, 0, $spacePos);
                            $value = substr($speedArg, $spacePos+1);

                            if(in_array($name, ["preset","tune","rc","multipass","bf"]) || in_array($name, $features)){
                                $speedArgs[] = "-$name";
                                $speedArgs[] = $value;
                            }
                        }

                    }
                    else{
                        $speedArgs = preg_split('/\s+/', trim($speedArgs));
                    }

                    foreach($speedArgs as $speedArg){
                        if(substr($speedArg,0,1) === "-"){
                            $arguments[] = $speedArg . ":v:" . $stream['typeIndex'];
                        }
                        else{
                            $arguments[] = $speedArg;
                        }
                    }

                    foreach(['range'=>'color_range', 'colorSpace'=>'colorspace', 'colorPrimaries'=>'color_primaries', 'gamma'=>'color_trc'] as $thingName => $thingFFName){
                        if(self::issetAndType($stream, $thingName, "string")){
                            $arguments[] = "-$thingFFName:v:" . $stream['typeIndex'];
                            $arguments[] = $stream[$thingName];
                        }
                    }
                }
            }
            //Do metadata stuff and customArgs
            foreach($output['streams'] as $streamNumber => $stream){
                if(self::issetAndType($stream, "metadata", "array")){
                    foreach($output['metadata'] as $metadataName => $metadataValue){
                        if(!is_string($metadataName) || $metadataName === '' || strpos($metadataName, "=") !== false || !is_string($metadataValue)){
                            continue;
                        }
                        $arguments[] = "-metadata:s:" . $stream['type'] . ":" . $stream['typeIndex'];
                        $arguments[] = "$metadataName=$metadataValue";
                    }
                }
                if(self::issetAndType($stream, "disposition", "string") && $stream['typeIndex'] > -1){
                    $arguments[] = "-disposition:" . $stream['type'] . ":" . $stream['typeIndex'];
                    $arguments[] = $stream['disposition'];
                }
                if(self::issetAndType($stream, "customArgs", "list")){
                    foreach($stream['customArgs'] as &$customArg){
                        if(substr($customArg, 0, 1) === "-"){
                            $customArg = $customArg . ":" . $stream['type'] . ":" . $stream['typeIndex'];
                        }
                    }
                    unset($customArg);
                    foreach($stream['customArgs'] as $customArg){
                        $arguments[] = self::doCodeTag($customArg, [
                            'outputNumber' => $outputNumber,
                            'streamNumber' => $streamNumber,
                            'streamTypeIndex' => $stream['typeIndex'],
                            'streamType' => $stream['type'],
                            'sourceInput' => $stream['source'][0],
                            'sourceTypeIndex' => $stream['source'][1],
                            'sourceStreamInfo' => $inputInfos[$stream['source'][0]]['streams'][$stream['type']][$stream['source'][1]] ?? [],
                            'sourceFormatInfo' => $inputInfos[$stream['source'][0]]['format'] ?? []
                        ]);
                    }
                }
            }

            if(empty($output['streams'])){
                mklog(2, "Output $outputNumber has no valid streams, ignoring output");
                unset($output[$outputNumber]);
                continue;
            }

            foreach(['seek'=>'ss', 'duration'=>'t', 'to'=>'to'] as $outputDataName => $outputCommandOption){
                if(isset($output[$outputDataName])){
                    if((is_string($output[$outputDataName]) && !empty($output[$outputDataName])) || is_numeric($output[$outputDataName])){
                        $arguments[] = "-$outputCommandOption";
                        $arguments[] = (string) $output[$outputDataName];
                    }
                }
            }

            if(self::issetAndType($output, "shortest", "boolean") && $output['shortest']){
                $arguments[] = "-shortest";
            }
            if(self::issetAndType($output, "mapMetadata", "integer")){
                $arguments[] = "-map_metadata";
                $arguments[] = (string) max($output['mapMetadata'], -1);
            }
            if(self::issetAndType($output, "mapChapters", "integer")){
                $arguments[] = "-map_chapters";
                $arguments[] = (string) max($output['mapChapters'], -1);
            }
            if(self::issetAndType($output, "metadata", "array")){
                foreach($output['metadata'] as $metadataName => $metadataValue){
                    if(!is_string($metadataName) || $metadataName === '' || strpos($metadataName, "=") !== false || !is_string($metadataValue)){
                        continue;
                    }
                    $arguments[] = "-metadata";
                    $arguments[] = "$metadataName=$metadataValue";
                }
            }
            if(self::issetAndType($output, "faststart", "boolean") && $output['faststart']){
                if(self::isRealPath($output['path']) && in_array($fileFormat, $extensionsAndFormats['movFamilyFormats'])){
                    $arguments[] = '-movflags';
                    $arguments[] = '+faststart';
                }
                else{
                    mklog(1, "Could not add faststart option to output " . $outputNumber . " because its not in the mov family");
                }
            }

            if($addFormatFlag){
                $arguments[] = "-f";
                $arguments[] = $fileFormat;
            }

            if(self::issetAndType($output, "customArgs", "list")){
                foreach($output['customArgs'] as $customArg){
                    $arguments[] = self::doCodeTag($customArg, [
                        'outputNumber' => $outputNumber,
                        'inputInfos' => $inputInfos
                    ]);
                }
            }

            $arguments[] = $output['path'];

            $command = array_merge($command, $arguments);
        }
        if(empty($outputs)){
            mklog(3, "There are no valid outputs");
            return false;
        }

        if(self::issetAndType($options, "threads", "integer")){
            $command[] = "-threads";
            $command[] = (string) $options['threads'];
        }
        if(self::issetAndType($options, "loglevel", "string")){
            $command[] = "-loglevel";
            $command[] = $options['loglevel'];
        }

        $command[] = "-y";

        if(self::issetAndType($options, "commandIntoFile", "string")){
            return files::mkFile($options['commandIntoFile'], implode(" ",$command), "w", true);
        }

        $proc = proc_open($command, [['pipe','r'],STDOUT,STDERR], $_);
        if(!is_resource($proc)){
            mklog(3, "Failed to open ffmpeg process");
            return false;
        }

        // proc_close() blocks until ffmpeg exits, then returns its exit code
        $exitCode = proc_close($proc);
        if($exitCode !== 0){
            mklog(3, "FFmpeg exited with a non-zero code ($exitCode)");
            return false;
        }

        foreach($outputs as $output){
            if(!file_exists($output['path']) || !self::isMedia($output['path'])){
                mklog(3, "FFmpeg did not output a valid media file " . basename($output['path']));
                @unlink($output['path']);
                return false;
            }
        }

        return true;
    }
    /**
     * Adds an encodeVideo() function to a conductor server, see https://github.com/tomgriffiths-net/video_encoder for detailed info.
     * @param array $inputs The inputs to pass to encodeVideo().
     * @param array $outputs The outputs to pass to encodeVideo().
     * @param array $options The options to pass to encodeVideo().
     * @param string $conductorIp The ip address of the conductor server.
     * @param int $conductorPort The port the conductor server is running on.
     * @return string|false The conductor job id on success or false on failure.
     */
    public static function addEncodeToConductor(array $inputs, array $outputs, array $options, string $conductorIp="127.0.0.1", int $conductorPort=52000):string|false{
        $functionString = "video_encoder::encodeVideo(";
        $functionString .= "unserialize(base64_decode(\"" . base64_encode(serialize($inputs)) . "\")),";
        $functionString .= "unserialize(base64_decode(\"" . base64_encode(serialize($outputs)) . "\")),";
        $functionString .= "unserialize(base64_decode(\"" . base64_encode(serialize($options)) . "\")));";

        $conductorJob = conductor_client::addJob($conductorIp, $functionString, $conductorPort);
        if(is_string($conductorJob)){
            mklog(1,'Added job ' . $conductorJob . ' to conductor ' . $conductorIp . ':' . $conductorPort);
            return true;
        }
        else{
            mklog(2,'Failed to add job to conductor ' . $conductorIp . ':' . $conductorPort);
            return false;
        }
    }
    /**
     * Encodes a folder of media using encodeVideo(), see https://github.com/tomgriffiths-net/video_encoder for details on encodeVideo().
     * @param string $sourceFolder The source folder to read media from.
     * @param string $destinationFolder The destination to put the encoded media.
     * @param array $inputOptions A template of what should be passed to encodeVideo()'s inputs. Only input 0 will have its path automatically set.
     * @param array $outputOptions A template of what should be passed to encodeVideo()'s outputs. Only output 0 will have its path automatically set.
     * @param array $encodeOptions The options to be passed to encodeVideos()'s options.
     * @param array $options An array containing folder processing information, see https://github.com/tomgriffiths-net/video_encoder for more info.
     * @return bool Weather the encode and temporary to final file rename was successful.
     */
    public static function encodeFolder(string $sourceFolder, string $destinationFolder, array $inputOptions, array $outputOptions, array $encodeOptions, array $options=[]):bool{
        if(self::issetAndType($options, "preset", "string")){
            $presetOptions = self::loadPreset($encodeOptions['preset']);
            if(is_array($presetOptions)){
                $options       = array_merge($presetOptions['folder'],  $options);
                $inputOptions  = array_merge($presetOptions['inputs'],  $inputOptions);
                $outputOptions = array_merge($presetOptions['outputs'], $outputOptions);
            }
            else{
                mklog(2, "Failed to read preset " . $options['preset']);
            }
            unset($presetOptions);
        }

        if(self::issetAndType($options, "source", "string") && empty($sourceFolder)){
            $sourceFolder = $options['source'];
        }
        if(self::issetAndType($options, "destination", "string") && empty($destinationFolder)){
            $destinationFolder = $options['destination'];
        }
        if(self::issetAndType($options, "sourcePrefix", "string")){
            $sourceFolder = $options['sourcePrefix'] . "/" . $sourceFolder;
        }
        if(self::issetAndType($options, "sourceSuffix", "string")){
            $sourceFolder = $sourceFolder . "/" . $options['sourceSuffix'];
        }
        if(self::issetAndType($options, "destPrefix", "string")){
            $destinationFolder = $options['destPrefix'] . "/" . $destinationFolder;
        }
        if(self::issetAndType($options, "destSuffix", "string")){
            $destinationFolder = $destinationFolder . "/" . $options['destSuffix'];
        }

        if(!self::issetAndType($options, "recursive", "boolean")){
            $options['recursive'] = false;
        }
        if(!self::issetAndType($options, "videoTypes", "list")){
            $options['videoTypes'] = ["mp4","mov","mkv","avi"];
        }
        if(!self::issetAndType($options, "outExtension", "string")){
            $options['outExtension'] = "same";
        }
        if(!self::issetAndType($options, "deleteSource", "boolean")){
            $options['deleteSource'] = false;
        }
        if(!self::issetAndType($options, "stopfile", "string")){
            $options['stopfile'] = "temp/video_encoder/stop";
        }

        $sourceFolder      = rtrim($sourceFolder,      '/\\ ');
        $destinationFolder = rtrim($destinationFolder, '/\\ ');

        if(!is_dir($sourceFolder)){
            mklog(3, "FolderEncode: The input folder does not exist " . $sourceFolder);
            return false;
        }

        $files = ($options['recursive'] ? files::globRecursive($sourceFolder . "\\", "*.*") : glob($sourceFolder . "\\*"));
        if(!is_array($files) || empty($files)){
            mklog(3,'FolderEncode: No files found in source folder');
            return false;
        }

        $return = false;
        foreach($files as $file){
            if(is_file($options['stopfile'])){
                mklog(1,"FolderEncode: Stop file exists, stopping");
                return $return;
            }

            if(!file_exists($file) || !in_array(pathinfo($file, PATHINFO_EXTENSION), $options['videoTypes'])){
                continue;
            }

            $videoInfo = self::getVideoInfo($file);
            if(!isset($videoInfo['format']['filename']) || !isset($videoInfo['format']['bit_rate']) || !isset($videoInfo['streams'])){
                mklog(2,"FolderEncode: " . $file . " is broken or unreadable, skipping");
                continue;
            }

            if(self::issetAndType($options, "filter", "string")){
                if(!self::matchFilter($videoInfo, $options['filter'])){
                    mklog(1,"FolderEncode: Skipping (filter) " . $file);
                    continue;
                }
            }

            $fileExtenstion = pathinfo($file, PATHINFO_EXTENSION);
            if($options['outExtension'] === "same"){
                $outExtension = $fileExtenstion;
            }
            else{
                $outExtension = $options['outExtension'];
            }

            $inDirLength = strlen($sourceFolder);
            $subPath = substr($file, $inDirLength +1, -(strlen($fileExtenstion) +1));

            $info = [
                'outfolder' => $destinationFolder,
                'tempfile' => round(microtime(true)*1000) . "." . $outExtension,
                'outfile' => $subPath . "." . $outExtension,
                'delsource' => $options['deleteSource'],
                'original' => $file
            ];

            $fileInputOptions = $inputOptions;
            $fileInputOptions[0]['path'] = $file;

            $fileOutputOptions = $outputOptions;
            $fileOutputOptions[0]['path'] = $info['outfolder'] . "/" . $info['tempfile'];

            if(isset($options['conductor'])){
                if(!is_array($options['conductor']) || !self::issetAndType($options['conductor'], "ip", "string") || !self::issetAndType($options['conductor'], "port", "integer")){
                    mklog(3, "FolderEncode: ConductorInfo was given but is wrong");
                    return false;
                }
                $functionString = "video_encoder::encodeVideo(";
                $functionString .= "unserialize(base64_decode('" . base64_encode(serialize($fileInputOptions))  . "')),";
                $functionString .= "unserialize(base64_decode('" . base64_encode(serialize($fileOutputOptions)) . "')),";
                $functionString .= "unserialize(base64_decode('" . base64_encode(serialize($encodeOptions))    . "')));";

                $finishFunctionString = "video_encoder::afterFolderEncode(";
                $finishFunctionString .= '$jobData[\'return\'],';
                $finishFunctionString .= "unserialize(base64_decode('" . base64_encode(serialize($info)) . "')));";

                $conductorJob = conductor_client::addJob($options['conductor']['ip'], $functionString, $options['conductor']['port'], $finishFunctionString);
                if(is_string($conductorJob)){
                    mklog(1,'FolderEncode: Added job ' . $conductorJob . ' to conductor ' . $options['conductor']['ip'] . ':' . $options['conductor']['port']);
                    $return = true;
                }
                else{
                    mklog(2,'FolderEncode: Failed to add job to conductor ' . $options['conductor']['ip'] . ':' . $options['conductor']['port']);
                }
            }
            else{
                $success = self::encodeVideo($fileInputOptions, $fileOutputOptions, $encodeOptions);

                if(self::afterFolderEncode($success, $info)){
                    $return = true;
                }
            }
        }
        return $return;
    }
    /**
     * @internal
     */
    public static function afterFolderEncode($encodeSuccess, $info):bool{
        if(
            !is_bool($encodeSuccess) || !is_array($info) ||
            !self::issetAndType($info, "outfolder", "string") ||
            !self::issetAndType($info, "tempfile",  "string") ||
            !self::issetAndType($info, "outfile",   "string") ||
            !self::issetAndType($info, "original",  "string") ||
            !self::issetAndType($info, "delsource", "boolean")
        ){
            mklog(2, "FolderEncode: Unable to finalize encode (typeError)");
            sleep(2);
            return false;
        }

        $fullTempPath = $info['outfolder'] . "/" . $info['tempfile'];
        $fullOutPath  = $info['outfolder'] . "/" . $info['outfile'];

        if(!$encodeSuccess){
            mklog(2, "FolderEncode: Unable to complete encode for output " . $fullOutPath . " reading " . $info['original']);
            return false;
        }

        if($info['delsource']){
            if(!unlink($info['original'])){
                mklog(2, "FolderEncode: Unable to remove source " . $info['original']);
            }
        }

        if(!rename($fullTempPath, $fullOutPath)){
            mklog(2, "FolderEncode: Unable to rename temporary file to " . $fullOutPath);
            return false;
        }

        return true;
    }
    /**
     * Reads all media files in a folder and calculates the total length of all the media combined.
     * @param string $sourceFolder The folder containing all the media.
     * @param bool $recursive Weather to recursivly glob the source folder.
     * @param array $videoTypes The file extensions to treat as videos.
     * @return int|false The total media length in seconds on success or false on failure.
     */
    public static function getFolderLength(string $sourceFolder, bool $recursive=false, array $videoTypes=["mp4","mov","mkv","avi"]):int|false{
        $return = false;
        if(is_dir($sourceFolder)){
            $length = 0;

            echo "Searching for files...\n";
            $files = ($recursive ? files::globRecursive($sourceFolder . "\\", "*.*") : glob($sourceFolder . "\\*"));

            foreach($files as $file){
                if(!file_exists($file) || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $videoTypes)){
                    continue;
                }

                echo "Scanning file " . $file . "\n";
                $videoInfo = self::getVideoInfo($file);
                
                if(!is_array($videoInfo) || !isset($videoInfo['format']['duration'])){
                    mklog(2, $file . " is broken or unreadable");
                    continue;
                }

                $length += round($videoInfo['format']['duration']);

                echo "Length: " . floor($length / 3600) . gmdate(":i:s", $length % 3600) . " \n";

                $return = intval($length);
            }
        }
        return $return;
    }
    /**
     * Tests weather a file is valid media.
     * @param string $path The path to the file.
     * @return bool Weather ffprobe could read the file and the file has a format duration.
     */
    public static function isMedia(string $path):bool{
        $info = self::getVideoInfo($path);
        return (is_array($info) && isset($info['format']['duration']));
    }
    /**
     * Uses ffprobe to get a videos information (-show_format -show_streams).
     * @param string $path The path to the media info.
     * @return null|array The decoded json array ffprobe gives on success or null on failure.
     */
    public static function getVideoInfo(string $path):?array{
        return self::ffprobeJson($path, '-show_format -show_streams');
    }
    /**
     * Uses ffprobe to get the first video streams keyframe information (-select_streams v:0 -show_entries frame=pkt_pts_time,pict_type -show_frames -skip_frame nokey).
     * @param string $path The path to the media.
     * @return null|array The decoded json array ffprobe would give on success or null on failure.
     */
    public static function getVideoKeyframeInfo(string $path):?array{
        return self::ffprobeJson($path, '-select_streams v:0 -show_entries frame=pkt_pts_time,pict_type -show_frames -skip_frame nokey');
    }
    /**
     * Gets the codec name of the first stream of a given type in a media file.
     * @param array $videoInfo The output from getVideoInfo().s
     * @param string $codecType The codec type.
     * @param int $typeIndex The stream type index.
     * @return string|false The codec name on success or false on failure.
     */
    public static function getCodecName(array $videoInfo, string $codecType="video", int $typeIndex=0):string|false{
        if(!isset($videoInfo['streams']) || !is_array($videoInfo['streams'])){
            return false;
        }

        foreach($videoInfo['streams'] as $stream){
            if(is_array($stream)){
                if(isset($stream['codec_type']) && $stream['codec_type'] === strtolower($codecType)){
                    if($typeIndex === 0){
                        if(isset($stream['codec_name']) && is_string($stream['codec_name'])){
                            return $stream['codec_name'];
                        }
                        else{
                            return false;
                        }
                    }
                    else{
                        $typeIndex --;
                    }
                }
            }
        }

        return false;
    }
    /**
     * Generates a map from stream type index to stream index for given media info.
     * @param array $videoInfo Info from getVideoInfo().
     * @return array An array containing video types and stream numbers, e.g. ["audio"][0] => 1 if the first audio stream is the second stream in the file.
     */
    public static function getStreamMap(array $videoInfo):array{
        if(!self::issetAndType($videoInfo, "streams", "list")){
            return [];
        }

        $return = [];

        foreach($videoInfo['streams'] as $stream){

            if(!self::issetAndType($stream, "index", "integer")){
                continue;
            }

            if(!self::issetAndType($stream, "codec_type", "string")){
                $stream['codec_type'] = "unknown";
            }

            $return[$stream['codec_type']][] = $stream['index'];
        }

        return $return;
    }
    /**
     * Uses getStreamMap() to re-organise data from getVideoInfo() so that the data is accesible through stream type indexes, e.g. ["streams"]["a"][0] for the first audio stream rather than ["streams"][1] assuming the audio stream was the second stream in the file.
     * @param array $videoInfo Information from getViedoInfo().
     * @return null|array The reordered information.
     */
    public static function nameStreams(array $videoInfo):?array{
        $map = self::getStreamMap($videoInfo);
        if(empty($map)){
            return null;
        }

        $originalStreams = $videoInfo['streams'];
        unset($videoInfo['streams']);

        foreach($map as $type => $streamNumbers){
            foreach($streamNumbers as $typeStreamNumber => $streamNumber){
                $videoInfo['streams'][strtolower(substr($type,0,1))][$typeStreamNumber] = $originalStreams[$streamNumber];
            }
        }

        return $videoInfo;
    }
    /**
     * Tests a code filter against some media information, runs nameStreams on the videoInfo.
     * @param array $videoInfo Information from getVideoInfo().
     * @param string $filter The code filter, e.g. "$info['v'][0]['bitrate'] > 10000000" where $info is the output from nameStreams().
     * @return bool Weather the code returned a truthy value.
     */
    public static function matchFilter(array $videoInfo, string $filter):bool{
        if(empty($filter)){
            return true;
        }

        $info = self::nameStreams($videoInfo);
        unset($videoInfo);
        if(!is_array($info)){
            return false;
        }

        return (bool) self::saferEval($filter, ['info'=>$info]);
    }
    /**
     * Tests weather a preset exists.
     * @param string $name The name of the preset.
     * @return bool Weather the preset exists.
     */
    public static function doesPresetExist(string $name):bool{
        $path = self::presetPath($name);
        if(!is_string($path)){
            return false;
        }
        return is_file($path);
    }
    /**
     * Gets a presets information.
     * @param string $name The name of the preset.
     * @return null|array The preset information on success or null on failure.
     */
    public static function loadPreset(string $name):?array{
        $path = self::presetPath($name);
        if(!is_string($path)){
            return null;
        }

        $preset = json::readFile($path,false);
        if(!is_array($preset)){
            return null;
        }
        foreach(['inputs','outputs','options','folder'] as $thing){
            if(!isset($preset[$thing]) || !is_array($preset[$thing])){
                $preset[$thing] = [];
            }
        }

        return $preset;
    }
    /**
     * Creates a preset.
     * @param string $name The name of the preset to create.
     * @param array $inputOptions The input options, this is mixed with encodeVideo()'s inputs array.
     * @param array $outputOptions The output options, this is mixed with encodeVideo()'s outputs array.
     * @param array $generalOptions The general options, this is mixed with encodeVideo()'s options array.
     * @param bool $overwrite Weather to overwrite any existing preset with the same name.
     * @return bool Weather the preset was created.
     */
    public static function createPreset(string $name, array $inputOptions=[], array $outputOptions=[], array $generalOptions=[], array $folderOptions=[], bool $overwrite=false):bool{
        $path = self::presetPath($name);
        if(!is_string($path)){
            return false;
        }
        return json::writeFile($path, ['inputs'=>$inputOptions, 'outputs'=>$outputOptions, 'options'=>$generalOptions, 'folder'=>$folderOptions], $overwrite);
    }
    /**
     * Cuts a video, main use for specifying arbitrary start time to start a video, this will re encode up to the nearest keyframe then stream copy from there, allowing a mostly lossles arbitrary start seek.
     * @param string $inPath The original media file.
     * @param string $outPath The output media file.
     * @param int|float|false $startTime A start time.
     * @param int|float|false $length The length of the output.
     * @return bool Weather the cut was successful.
     */
    public static function cut(string $inPath, string $outPath, int|float|false $startTime=false, int|float|false $length=false):bool{
        if(!is_file($inPath)){
            mklog(3, 'Input file does not exist');
            return false;
        }

        if(is_file($outPath)){
            mklog(3, 'Output file already exists');
            return false;
        }

        if($startTime === 0){
            $startTime = false;
        }
        if($length === 0){
            $startTime = false;
        }

        if($startTime === false && $length === false){
            mklog(3, "Both start time and length are not positive numbers specified, no cut can be performed");
            return false;
        }

        if($startTime !== false){
            if($startTime < 0){
                mklog(3, 'Cannot start from a negetive number');
                return false;
            }

            echo "Reading source keyframes...\n";
            $sourceKeyframes = self::getVideoKeyframeInfo($inPath);
            if(!is_array($sourceKeyframes) || !isset($sourceKeyframes['frames'])){
                mklog(3, 'Failed to get keyframes of source');
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
                mklog(3, 'Failed to locate close keyframe');
                return false;
            }

            $originalInfo = self::getVideoInfo($inPath);
            $videoCodec = self::getCodecName($originalInfo, 'video');
            $audioCodec = self::getCodecName($originalInfo, 'audio');
            $subCodec   = self::getCodecName($originalInfo, 'subtitle');

            $outPathFullname = substr($outPath,0,strripos($outPath,"."));
            $outPathExtension = pathinfo($outPath, PATHINFO_EXTENSION);
            $part1path = $outPathFullname . '_part1.' . $outPathExtension;
            $part2path = $outPathFullname . '_part2.' . $outPathExtension;
            $fileslist = $outPathFullname . '_fileslist.txt';

            echo "Cutting first part...\n";

            if(!self::encodeVideo(
                [
                    $inPath
                ],
                [
                    [
                        'path' => $part1path,
                        'seek' => $startTime,
                        'duration' => ($nextKeyframeTime - $startTime),
                        'streams' => [
                            [
                                'type' => 'v',
                                'source' => [0,-1,true],
                                'format' => $videoCodec,
                                'quality' => 99,
                                'speed' => "slow",
                            ],
                            [
                                'type' => 'a',
                                'source' => [0,-1,true],
                                'format' => $audioCodec,
                            ],
                            [
                                'type' => 's',
                                'source' => [0,-1,true],
                                'format' => $subCodec,
                            ],
                        ]
                    ]
                ],
                [
                    'loglevel' => "warning"
                ]
            )){
                mklog(3, 'Failed to cut first part');
                if(is_file($part1path)){
                    @unlink($part1path);
                }
                return false;
            }

            echo "Cutting second part...\n";
            exec('ffmpeg -ss ' . $nextKeyframeTime . ' -i ' . files::validatePath($inPath,true) . ' -c copy ' . ($subCodec === "mov_text" ? "-c:s mov_text" : "") . ' -loglevel warning ' . files::validatePath($part2path,true) . ' -y');
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

            $command = '-f concat -safe 0 -i ' . files::validatePath($fileslist,true) . ' -i ' . files::validatePath($inPath,true) . ' -map 0 -map_metadata 1 -map_chapters -1 ';
        }
        else{
            $command = '-i ' . files::validatePath($inPath,true) . ' ';
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

        $command .= '-loglevel warning ' . files::validatePath($outPath,true) . ' -y';
        exec("ffmpeg " . $command);

        if($startTime !== false){
            if(is_file($part1path)){unlink($part1path);}
            if(is_file($part2path)){unlink($part2path);}
            if(is_file($fileslist)){unlink($fileslist);}
        }

        if(!self::isMedia($outPath)){
            mklog(3,'Failed to cut video');
            if(is_file($outPath) && !@unlink($outPath)){
                mklog(2,'Unable to delete unfinished file ' . $outPath);
            }
            return false;
        }

        return true;
    }
    /**
     * Runs cut() on all the files in a folder, does not support having the source and destination folders being the same.
     * @param string $sourceFolder The folder containing the source files.
     * @param string $destinationFolder The folder where all the new files will be put.
     * @param int|float|false $startTime The startTime that will be passed to cut().
     * @param int|float|false $length The length that will be passed to cut().
     * @param bool $recursive Weather to recursively glob the source directory.
     * @param array $videoTypes The file extensions to treat as videos.
     * @return bool Weather all cut operations were successful.
     */
    public static function cutFolder(string $sourceFolder, string $destinationFolder, int|float|false $startTime=false, int|float|false $length=false, bool $recursive=false, array $videoTypes=["mp4","mov","mkv","avi"]):bool{
        if(!is_dir($sourceFolder)){
            mklog(3,'Source folder does not exist');
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

            if(!in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $videoTypes)){
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
    /**
     * Gets a media files total bitrate.
     * @param string $path The path to the media file.
     * @return int|false The total bitrate in bits per second on success or false on failure.
     */
    public static function getVideoBitrate(string $path):int|false{
        $data = self::getVideoInfo($path);
        if(!is_array($data) || !isset($data['format']['bit_rate'])){
            return false;
        }
        return intval($data['format']['bit_rate']);
    }
    /**
     * Detects any black borders in the central 60 seconds of a video.
     * @param string $path The path to the video file.
     * @return array|false Information about any found borders on success or false on failure, no borders being present is not a failure case.
     */
    public static function detectVideoCrop(string $path):array|false{
        $videoInfo = self::getVideoInfo($path);
        if(!is_array($videoInfo)){
            mklog(2, "Failed to get video info");
            return false;
        }

        if(!isset($videoInfo['format']['duration']) || !is_numeric($videoInfo['format']['duration'])){
            mklog(2, "Video info incomplete");
            return false;
        }
        $middlePoint = (round(floatval($videoInfo['format']['duration'])) / 2) - 30;

        $origWidth = null;
        $origHeight = null;
        foreach($videoInfo['streams'] as $stream){
            if($stream['codec_type'] === "video"){
                $origWidth = $stream['width'];
                $origHeight = $stream['height'];
                break;
            }
        }
        if(!$origWidth || !$origHeight){
            mklog(2, "Failed to get video resolution");
            return false;
        }

        $output = shell_exec(sprintf(
            'ffmpeg -ss %d -i %s -t 60 -vf cropdetect=round=2 -f null - 2>&1',
            $middlePoint,
            escapeshellarg($path)
        ));
        if(!$output){
            mklog(2, "Failed to scan video for cropping");
            return false;
        }

        $lines = explode("\n", $output);
        $cropValues = [];
        
        foreach($lines as $line){
            if(preg_match('/crop=(\d+:\d+:\d+:\d+)/', $line, $matches)){
                $cropValues[] = $matches[1];
            }
        }
        if(empty($cropValues)){
            mklog(2, "Failed to get crop values for video");
            return false;
        }
        
        // Get the most common crop value (mode)
        $cropCounts = array_count_values($cropValues);
        arsort($cropCounts);
        $mostCommonCrop = key($cropCounts);
        
        // Parse crop values to check if borders exist
        list($width, $height, $x, $y) = explode(':', $mostCommonCrop);
        
        // Check if cropping is needed
        $hasBorders = ($x != 0 || $y != 0 || $width != $origWidth || $height != $origHeight);
        
        return [
            'has_borders' => $hasBorders,
            'crop_filter' => $hasBorders ? "crop={$mostCommonCrop}" : null,
            'crop_value' => $mostCommonCrop,
            'original_size' => "{$origWidth}x{$origHeight}",
            'cropped_size' => "{$width}x{$height}"
        ];
    }
    /**
     * Checks weather an ffmpeg input is a real media file path or another source.
     * @param string $path The path to test.
     * @return bool Weather the file is a real file path.
     */
    public static function isRealPath(string $path):bool{
        if($path === '' || $path === '-'){
            return false;                       // '-' = stdout shorthand
        }

        // ffmpeg pipe protocol: pipe:, pipe:0, pipe:1 ...
        if(preg_match('#^pipe:#i', $path)){
            return false;
        }

        // A protocol scheme is 2+ chars (letter-led) before the ':'.
        // A SINGLE letter before ':' is a Windows drive (C:\...)
        if(preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]+:#', $path, $m)){
            $scheme = strtolower(rtrim($m[0], ':'));
            // 'file:' is a local target via protocol - treat as local
            return $scheme === 'file';
        }

        return true;   // no scheme, or single-letter drive → local path
    }
    /**
     * Reads a pix_fmt string and returns the bit depth (bits) and chroma information.
     * @param string $pixFmt The pix_fmt to read.
     * @return array The bit depth (bits) and chroma information.
     */
    public static function pixFmtInfo(string $pixFmt):array{
        $depth  = 8;
        $chroma = null;

        // --- planar YUV with explicit depth: yuv420p10le, yuv422p12le, yuv444p ---
        if(preg_match('/yuv[aj]?(\d{3})p(\d{1,2})?/', $pixFmt, $m)){
            $chroma = $m[1];                          // 420 / 422 / 444
            $depth  = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 8;
        }
        // --- packed YUV 4:2:2: yuyv422, uyvy422 (8-bit) ---
        elseif(preg_match('/(?:yuyv|uyvy|yvyu)(\d{3})/', $pixFmt, $m)){
            $chroma = $m[1];
            $depth  = 8;
        }
        // --- semi-planar pNNN: p010/p016 = 420, p210/p216 = 422, p410 = 444 ---
        elseif(preg_match('/^p([024])(\d{2})/', $pixFmt, $m)){
            $chroma = ['0' => '420', '2' => '422', '4' => '444'][$m[1]];
            $depth  = (int)$m[2];                      // 10, 12, 16...
        }
        // --- NV semi-planar (8-bit): nv12/nv21 = 420, nv16 = 422, nv24 = 444 ---
        elseif(preg_match('/^nv(12|21|16|24)$/', $pixFmt, $m)){
            $chroma = ['12' => '420', '21' => '420', '16' => '422', '24' => '444'][$m[1]];
            $depth  = 8;
        }
        // --- RGB / planar GBR: treat as 4:4:4 ---
        elseif(str_starts_with($pixFmt, 'gbrp') || str_contains($pixFmt, 'rgb') || str_contains($pixFmt, 'bgr')){
            $chroma = '444';
            if(preg_match('/(\d{1,2})(?:le|be)?$/', $pixFmt, $m) && (int)$m[1] > 6){
                $depth = (int)$m[1];                    // gbrp10le etc.; guard against trailing 0 in rgb0
            }
        }

        return ['bits' => $depth, 'chroma' => $chroma];
    }
    /**
     * Estimate the CRF that hits $targetVmaf for one (encoder, speed) result set.
     * Accepts fractional VMAF targets (e.g. 99.8) for fine control at the high end.
     * Clamps to the tested range; interpolates linearly between measured points.
     *
     * @param array $points     ["16"=>99.86, "18"=>99.63, ...] crf => vmaf
     * @param float $targetVmaf requested VMAF (may be fractional)
     * @return float            estimated CRF (fractional; round for int-only encoders)
     */
    public static function crfForVmaf(array $points, float $targetVmaf):float{
        $crfs = array_map('intval', array_keys($points));
        sort($crfs);

        $bestCrf  = $crfs[0];                // lowest crf  = highest vmaf
        $worstCrf = $crfs[count($crfs) - 1]; // highest crf = lowest vmaf

        // Clamp outside the tested range.
        if($targetVmaf >= $points[$bestCrf]){
            return $bestCrf;
        }
        if($targetVmaf <= $points[$worstCrf]){
            return $worstCrf;
        }

        // Find the adjacent measured pair that brackets the target VMAF.
        for($i = 0; $i < count($crfs) - 1; $i++){
            $crfLo  = $crfs[$i];                    // lower crf
            $crfHi  = $crfs[$i + 1];                // higher crf
            $vmafHi = $points[$crfLo];     // vmaf at lower crf  (higher value)
            $vmafLo = $points[$crfHi];     // vmaf at higher crf (lower value)

            // Divide-by-zero guard: flat segment from rounding ties.
            if($vmafHi == $vmafLo){
                if($targetVmaf == $vmafHi){
                    return $crfLo;
                }   // exact match on a flat spot
                continue;                                     // otherwise this pair can't bracket it
            }

            if($targetVmaf <= $vmafHi && $targetVmaf >= $vmafLo){
                $frac = ($vmafHi - $targetVmaf) / ($vmafHi - $vmafLo);   // 0..1
                return $crfLo + $frac * ($crfHi - $crfLo);
            }
        }

        return ($worstCrf + $bestCrf) / 2; // safety fallback (clamps should prevent reaching here)
    }
    /**
     * Given an encoder and speed and targer VMAF, this returns the recommended crf like quality number.
     * @param string $encoder The encoder passed to -c:v.
     * @param string $speed The encoders speed preset from video_encoder, not the encoders internal ffmpeg speed.
     * @param float $targetVmaf The target VMAF.
     * @return null|float The crf like quality number that is expected to reach the target VMAF on success, null on failure.
     */
    public static function targetVmafToCrf(string $encoder, string $speed, float $targetVmaf):?float{
        $vmafs = json::readFile("packages/video_encoder/videoEncoderVmafs.json");
        if(!is_array($vmafs) || !isset($vmafs[$encoder][$speed]) || !is_array($vmafs[$encoder][$speed])){
            return null;
        }

        return round(self::crfForVmaf($vmafs[$encoder][$speed], $targetVmaf), 2);
    }

    /**
     * Gets all NVDEC/NVENC capabilities based on card generation.
     * @return null|array The information on success or null on failure.
     */
    public static function nvAllCapabilities():?array{
        $data = json::readFile("packages/video_encoder/nvCapabilities.json");
        if(!is_array($data) || empty($data)){
            return null;
        }
        return $data;
    }
    /**
     * Gets the name of the currently installed Nvidia GPU using nvidia-smi.
     * @return null|string The name of the card on success or null on failure.
     */
    public static function nvCardName():?string{
        $out = [];
        $code = 0;

        @exec('nvidia-smi --query-gpu=name --format=csv,noheader 2>&1', $out, $code);
        if($code !== 0 || empty($out[0])){
            return null;
        }

        return trim($out[0]);
    }
    /**
     * The NVDEC/NVENC capabilities of the currently installed GPU.
     * @return null|array The capabilities of the current card, see https://github.com/tomgriffiths-net/video_encoder for details.
     */
    public static function nvCardCapabilities():?array{
        $cardName = self::nvCardName();
        if(!$cardName){
            return null;
        }

        // Match the model number after GTX/RTX/GT.
        if(!preg_match('/\b(?:RTX|GTX|GT)\s*(\d{3,4})/i', $cardName, $m)){
            return null;
        }
        $model = $m[1];

        $cardGen = (int)(strlen($model) === 4 ? substr($model, 0, 2) : substr($model, 0, 1));

        $capabilities = self::nvAllCapabilities();

        if(!isset($capabilities[$cardGen])){
            return null;
        }
        
        return $capabilities[$cardGen];
    }

    /**
     * Gets information about file extensions and formats.
     * @return null|array The information on success or null on failure.
     */
    public static function extensionsAndFormats():?array{
        $data = json::readFile("packages/video_encoder/extensionsAndFormats.json");
        if(!is_array($data) || empty($data)){
            return null;
        }
        return $data;
    }
    /**
     * Gets information about video encoders, but not the speed preset information.
     * @return null|array The information on success or null on failure.
     */
    public static function videoEncoders():?array{
        $data = json::readFile("packages/video_encoder/videoEncoders.json");
        if(!is_array($data) || empty($data)){
            return null;
        }
        return $data;
    }

    private static function issetAndType(array $items, int|string $name, string $type, bool $checkEmptyStringAndArray=true):bool{
        $checkList = false;
        if($type === "list"){
            $type = "array";
            $checkList = true;
        }

        if(!isset($items[$name])){
            return false;
        }

        if($type === "numeric"){
            return is_numeric($items[$name]);
        }
        
        if(gettype($items[$name]) !== $type){
            return false;
        }
        
        if($checkEmptyStringAndArray && in_array($type, ['string','array'])){
            if(empty($items[$name])){
                return false;
            }
        }

        if($checkList){
            if(!array_is_list($items[$name])){
                return false;
            }
        }

        return true;
    }
    private static function copyFilesAndEncode(array $inputs, array $outputs, array $options):bool{
        $options['allowFileCopy'] = false;

        $tempDir = getcwd() . "/temp/video_encoder";
        files::ensureFolder($tempDir);

        $base = $tempDir . "/" . round(microtime(true)*1000);

        $inMap = [];
        $outMap = [];

        for($i=0; $i < count($inputs); $i++){
            if(is_array($inputs[$i]) && isset($inputs[$i]['path'])){
                $inputPath = &$inputs[$i]['path'];
            }
            else{
                $inputPath = &$inputs[$i];
            }

            if(!is_string($inputPath) || !is_file($inputPath)){
                continue;
            }

            $tempFile = $base . "in" . $i;
            $inputPathExt = files::getFileExtension($inputPath);
            if(!empty($inputPathExt)){
                $tempFile .= "." . $inputPathExt;
            }

            $inMap[$tempFile] = $inputPath;

            if(!files::copyFile($inputPath, $tempFile)){
                mklog(2, "Failed to copy input file to local storage, " . $inputPath . " -> " . $tempFile);
                foreach($inMap as $oldTempFile => $_){
                    @unlink($oldTempFile);
                }
                return false;
            }

            $inputPath = $tempFile;
        }
        unset($inputPath);

        for($i=0; $i < count($outputs); $i++){
            if(!is_array($outputs[$i]) || !isset($outputs[$i]['path']) || !is_string($outputs[$i]['path'])){
                continue;
            }

            $tempFile = $base . "out" . $i;
            $outputPathExt = files::getFileExtension($outputs[$i]['path']);
            if(!empty($outputPathExt)){
                $tempFile .= "." . $outputPathExt;
            }

            $outMap[$tempFile] = $outputs[$i]['path'];

            $outputs[$i]['path'] = $tempFile;
        }
        unset($tempFile);
        unset($base);

        $videoDone = self::encodeVideo($inputs, $outputs, $options);
        $success = $videoDone;

        if($videoDone){
            foreach($outMap as $outputTempFile => $outputRealFile){
                if(!files::copyFile($outputTempFile, $outputRealFile)){
                    mklog(2, "Failed to copy output temporary file to destination, " . $outputTempFile . " -> " . $outputRealFile);
                    $success = false;
                }
            }
        }

        foreach($inMap as $inputTempFile => $_){
            @unlink($inputTempFile);
        }
        foreach($outMap as $outputTempFile => $_){
            @unlink($outputTempFile);
        }
        
        return $success;
    }
    private static function doCodeTag(string $command, array $evalVars=[]):?string{
        $pos = strpos($command, '<code:');
        if($pos !== false){
            $end = strpos($command, '>', $pos);
            if($end){
                $code = substr($command, $pos +6, $end - $pos -6);

                $secondBit = self::saferEval($code, $evalVars);
                if($secondBit === null){
                    return null;
                }

                $firstBit = substr($command, 0, $pos);
                $thirdBit = substr($command, $end +1);

                $command = $firstBit . $secondBit . $thirdBit;

                if(strpos($command, '<code:')){
                    $command = self::doCodeTag($command, $evalVars);
                    if(!is_string($command)){
                        return null;
                    }
                }
            }
        }

        return $command;
    }
    private static function saferEval(string $thecodethatisrun, array $thevariablesavailaible):mixed{
        if(!preg_match('/^(?=.+)[a-zA-Z_$()!][a-zA-Z0-9_\[\].\s$(),"\'&|!<>=+:-]*$/', $thecodethatisrun)){
            return null;
        }

        foreach($thevariablesavailaible as $avariablename => $avariablevalue){
            $$avariablename = $avariablevalue;
        }
        unset($avariablename);
        unset($avariablevalue);
        unset($thevariablesavailaible);

        try{
            return eval('return (' . $thecodethatisrun . ');');
        }
        catch(\Error){
            return null;
        }
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
    private static function ffprobeJson(string $file, string $args):?array{
        $ffprobePath = e_ffmpeg::path("ffprobe");
        if(!is_string($ffprobePath) || empty($ffprobePath)){
            return null;
        }

        $result = shell_exec(files::validatePath($ffprobePath,true) . ' -v quiet ' . $args . ' -print_format json ' . files::validatePath($file,true));
        if(!is_string($result)){
            return null;
        }

        $json = json_decode($result,true);
        if(!is_array($json) || empty($json)){
            return null;
        }
        
        return $json;
    }
}