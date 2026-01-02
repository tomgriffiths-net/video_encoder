# video_encoder
video_encoder is a PHP-CLI package that provides functionality to encode videos using ffmpeg.

# Commands
- **cropdetect [videoPath]**: Prints out crop information, uses detectVideoCrop function.
- **mediainfo [videoPath] (options)**: Prints out ffprobe information about a media file, optional "-showstreams" to display stream information.

# Video Options
- **outputResolutionWidth**: Integer, default of 1920, specifies the width of the output video, cannot be smaller than 10 or larger than 8192.
- **outputResolutionHeight**: Integer, default of 1080, specifies the height of the output video, cannot be smaller than 10 or larger than 4320.
- **outputVideoBitrate**: Integer, no default, specifies the kilobits per second of the output video.
- **outputAudioBitrate**: Integer, no default, specifies the kilobits per second of the output video's audio.
- **outputAudioSampleRate**: Integer, no default, specifies the sample rate of the output video's audio in hertz.
- **saturation**: Integer or Float, no default, specifies the saturation multiplier to be passed to ffmpeg, 1 would be no change and 2 would be a doubleing.
- **framesPerSecond**: Integer or Float, no default, specifies the framerate of the output video.
- **colorBitDepth**: Integer, default of 8, can only be set to 8 or 10, specifies the bit depth of the output video.
- **NVENC**: Boolean, default of false, specifies weather to use the h264_nvenc codec or not.
- **cpuThreads**: Integer, no default, specifies the limit of threads that ffmpeg can use.
- **qualityLoss**: Integer, default of 23, specifies the amount of compression to apply to the output video, is more sensetive when using cpu encoding.
- **format**: String, no default, specifies the format of the output video.
- **realTime**: Boolean, default of false, specifies weather to encode the video in real time or not, cannot be used with 2pass.
- **customArgs**: String, no default, specifies custom ffmpeg arguments, overrides everything exept the input, output, realTime, threads, and livePreview options, can be used with 2pass but all video options must come before all audio and other options.
- **commandIntoFile**: Boolean, default of false, specifies weather to output the ffmpeg command into a file rather than executing the command, cannot be used with 2pass.
- **livePreview**: Boolean, default is false, specifies weather to provide a live preview of what ffmpeg is currently encoding, opens a ffplay window and may cause performance drop if encoding fast.
- **2pass**: Boolean, default of false, specifies weather ffmpeg should first scan the file before encoding, will not be enabled if the video streams are only being copied (e.g. "-c:v copy" is present).

# Settings
- **presetsPath**: String, default of ""videoencoder/presets", specifies the directory to keep presets in.
- **secondTry**: Boolean, default of true, specifies weather to try a second time if ffmpeg fails.
- **allowCinelikeDSaturationModification**: Boolean, default of false, specifies weather to apply a 1.3x saturation filter to MOV videos that have been recorded in the panasonic CINELIKE-D colour profile.
- **copyFilesToLocal**: Boolean, default of false, specifies weather to copy video files to and from temporary local storage when encoding a video.

# Functions
- **encode_video(string $inPath, string $outPath, array $options=[]):bool**: Encodes a video with the specified options. Returns true on success or false on failure.
- **addEncodeToConductor(string $inPath, string $outPath, array $options=[], string $conductorIp="127.0.0.1", int $conductorPort=52000):string|false**: Adds a single video encode task to a conductor server, returns the job id on success or false on failure.
- **encode_folder(string $sourceFolder, string $destinationFolder, bool $recursive=false, bool|string $jobId=false, array $videoTypes=["mp4","mov","mkv","avi"], array $encodeOptions=[], string $outFileExtension="mp4", bool $deleteSourceAfter=false, bool $useConductor=false, string $filter=""):bool**: Encodes a folder of videos and calls encode_video for each one, can add jobs to conductor instead of executing on the local machine if needed. Returns true on success and false on failure.
- **getFolderLength(string $sourceFolder, bool $recursive=false, array $videoTypes=["mp4","mov","mkv","avi"]):int|false**: Calculates the total duration of all the videos in a folder while showing a running total in the command line. Returns the total length in seconds on success or false on failure.
- **isVideo(string $path, array $videoTypes=["mp4","mov","mkv","avi"]):bool**: Checks weather a file has an extension in the $videoTypes array. Returns true on success or false on failure.
- **getVideoInfo(string $path, string|bool $jobFolder=false):array|bool**: Uses ffprobe to get detailed information about a video file. Returns the information on success or false on failure.
- **getVideoKeyframeInfo(string $path, string|bool $jobFolder=false):array|bool**: Uses ffprobe to get information about the keyframes in a given video. Returns the information on success or false on failure.
- **getInfoCodec(array $videoInfo, string $codecType="video"):string|false**: Takes in videoInfo from getVideoInfo and returns the codec name on success or false on failure.
- **getStreamMap(array $videoInfo):array**: Returns the stream indexes reletave to their type indexes, returns the map on success or an empty array on failure.
- **nameStreams(array $videoInfo, bool $shortHand=true):array|false**: Uses getStreamMap to rename streams from getVideoInfo to names like v:0 and a:0 rather than 0, 1, etc, returns the entire videoInfo array with renamed streams array on success or false on failure.
- **matchFilter(array $videoInfo, string $filter):bool**: Uses nameStreams and assigns the result to a variable named $info, this can then be used in a comparison/code snippet inside $filter to test if some videoInfo passes the test, e.g. to check if a video was over 6 Mbps you could use "$info['streams']['v:0']['bit_rate'] > 6000000" as the filter. Returns true on match or false otherwise.
- **doesPresetExist(string $name):bool**: Checks weather a preset name exists. Returns true on success or false on failure.
- **loadPreset(string $name):array|false**: Gets a presets video options. Returns the video options on success or false on failure.
- **createPreset(string $name, array $options=[], $overwrite=true):bool**: Creates a preset with the given video options. Returns true on success or false on failure.
- **cut(string $inPath, string $outPath, int|float|false $startTime=false, int|float|false $length=false):bool**: Cuts a given video, if a start time is set then some of the source will have to be re encoded, the amount varies for each video, the length is the length of the video after the start time, a negative length will cut that many seconds of the end of the video. Returns true on success or false on failure. Does not work on some videos as there are too many keyframes for php to process.
- **cutFolder(string $sourceFolder, string $destinationFolder, int|float|false $startTime=false, int|float|false $length=false, bool $recursive=false, array $videoTypes=["mp4","mov","mkv","avi"]):bool**: Runs the cut function on a group of video files. Returns true on success or false on failure.
- **getVideoBitrate(string $path):int|false**: Gets the overall bitrate of a video, returns the bitrate in bits per second on success or false on failure.
- **useCompressionToTargetBitrate(string $inPath, string $outPath, string $customArgs, int $bitrate, string $mode="closest", int $minCompression=20, int $maxCompression=40):bool**: Uses a compression value to tarfget a bitrate, rather than specifying an exact bitrate, can be slow sometimes, bitrate is in bits per second, mode can be min or max or closest, replaces "&lt;cmp&gt;" with the compression number in the custom args parameter, returns true on success or false on failure.
- **useCompressionToTargetBitrateOnFolder(string $sourceFolder, string $destinationFolder, string $customArgs, int $bitrate, string $mode="closest", int $minCompression=20, int $maxCompression=40, bool $recursive=false, array $videoTypes=["mp4","mov","mkv","avi"]):bool**: Runs useCompressionToTargetBitrate() on each video file in a folder, each video file will be processed seperately, returns true on success or false on failure.
- **detectVideoCrop(string $path):array|false**: Returns crop information on a video file on success or false on failure.