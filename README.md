# video_encoder
video_encoder is a PHP-CLI package that provides functionality to encode videos using ffmpeg.

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
- **livePreview**: Boolean, default is false, specifies weather to provide a live preview of what ffmpeg is currently encoding, causes ffmpeg to be opened in a new command window and may cause small performance drop.
- **livePreviewWidth**: Integer, default of 69, specifies the width of the live preview, uses cli_pixels so the total number of pixels in the preview is limited to 2730.
- **livePreviewHeight**: Integer, default of 39, specifies the height of the live preview.
- **2pass**: Boolean, default of false, specifies weather ffmpeg should first scan the file before encoding.

# Functions
- **encode_video(string $inPath, string $outPath, array $options=[]):bool**: Encodes a video with the specified options. Returns true on success or false on failure.
- **encode_folder(string $sourceFolder, string $destinationFolder, bool $recursive=false, bool|string $jobId=false, array $videoTypes=["mp4","mov","mkv","avi"], array $encodeOptions=[], string $outFileExtension="mp4", bool $deleteSourceAfter=false, bool $useConductor=false):bool**: Encodes a folder of videos and calls encode_video for each one, can add jobs to conductor instead of executing on the local machine if needed. Returns true on success and false on failure.
- **getFolderLength(string $sourceFolder, bool $recursive=false, array $videoTypes=["mp4","mov","mkv","avi"]):int|false**: Calculates the total duration of all the videos in a folder while showing a running total in the command line. Returns the total length in seconds on success or false on failure.
- **isVideo(string $path, array $videoTypes=["mp4","mov","mkv","avi"]):bool**: Checks weather a file has an extension in the $videoTypes array. Returns true on success or false on failure.
- **getVideoInfo(string $path, string|bool $jobFolder=false):array|bool**: Uses ffprobe to get detailed information about a video file. Returns the information on success or false on failure.
- **doesPresetExist(string $name):bool**: Checks weather a preset name exists. Returns true on success or false on failure.
- **loadPreset(string $name):array|false**: Gets a presets video options. Returns the video options on success or false on failure.
- **createPreset(string $name, array $options=[], $overwrite=true):bool**: Creates a preset with the given video options. Returns true on success or false on failure.