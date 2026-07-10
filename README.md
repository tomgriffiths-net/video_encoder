# video_encoder
video_encoder is a PHP-CLI package that provides functionality to encode videos using ffmpeg. see https://www.tomgriffiths.net/php-cli/package-docs/video_encoder.html for function definitions.

# Commands
You can use "video_encoder" or "ve" as the start of each command and each command also has smaller versions of the names.
Commands have arguments that are values with no name whose position relative to eachother set what the mean,
parameters are values with names preceded with two dashes,
options have no value and are true if they are present in the command (false otherwise) and are preceded by one dash.
Names and values can be surrounded with quotes to stop spaces seperating them.

| Command Name        | Arguments                                                                                      | Options                                                                              | Parameters                                                                                                                  | Notes                                                                                                                                       |
|---------------------|------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------|
| help/h              | none                                                                                           | none                                                                                 | none                                                                                                                        | Opens the video_encoder github page.                                                                                                        |
| mediainfo/mi        | - Path to source media file.                                                                   | **showstreams/ss** Shows the source streams rather than just the format information. | none                                                                                                                        | Prints information about a media file.                                                                                                      |
| cropdetect/cd       | - Path to source media file.                                                                   | none                                                                                 | none                                                                                                                        | Shows ffmpeg crop information that can be used to remove detected black boarders in a video file.                                           |
| runpreset/rp        | - Preset name.                                                                                 | none                                                                                 | **in** Input 0 path override.<br> **out** Output 0 path override.                                                           | Runs a preset using encodeVideo() with optional first input/output overrides.                                                               |
| runpresetfolder/rpf | - Preset name.                                                                                 | none                                                                                 | **in** Source folder override.<br> **out** Destination folder override.                                                     | Runs a preset for encodeFolder() and passes the preset to encodeVideo().                                                                    |
| folderlength/fl     | - Path to source media folder.                                                                 | **recursive/r** Weather to recursively glob the folder.                              | none                                                                                                                        | Reads the length for every media file in a folder and sums their durations.                                                                 |
| ismedia/im          | - Path to source media file.                                                                   | none                                                                                 | none                                                                                                                        | Tests weather a given file is a valid media file or not, prints YES or NO.                                                                  |
| codecname/cn        | - Path to source media file.<br> - Stream type (optional).<br> - Stream type index (optional). | none                                                                                 | none                                                                                                                        | Prints the codec name of the specified stream, default stream type is "video" and default stream number is 0.                               |
| streammap/sm        | - Path to source media file.                                                                   | **json/j** Print the map in json format.                                             | none                                                                                                                        | Shows a map for stream type index to stream index for a given media file.                                                                   |
| presetexists/pe     | - Preset name.                                                                                 | none                                                                                 | none                                                                                                                        | Prints YES or NO indicating preset existance.                                                                                               |
| viewpreset/vp       | - Preset name.                                                                                 | none                                                                                 | none                                                                                                                        | Prints a presets settings in json format if it exists.                                                                                      |
| createpreset/cp     | - Preset name.                                                                                 | **overwrite/ow** Weather to overwrite an existing preset with the same name.         | none                                                                                                                        | Creates an empty preset with the specified name.                                                                                            |
| cut/c               | - Source file path.<br> - Destination file path.                                               |                                                                                      | **start/s** The start time to start the output from in the input file.<br> **duration/d** The duration of the output video. | Cuts a video for any start time and/or duration without relying on keyframes.                                                               |
| cutfolder/cf        | - Source folder path.<br> - Destination folder path.                                           | **recursive/r** Weather to recursively glob the source folder.                       | **start/s** The start time to start the output from in the input file.<br> **duration/d** The duration of the output video. | Cuts a video for any start time and/or duration without relying on keyframes for a folder.                                                  |
| getbitrate/gb       | - Source file path.                                                                            |                                                                                      |                                                                                                                             | Gets a media files total bitrate in Kbps.                                                                                                   |
| vmaf2crf/v2c        | - Encoder name.<br> - ve encoder speed preset.<br> - Target VMAF (40 < x <= 100).              |                                                                                      |                                                                                                                             | Takes an encoder name and video_encoder encoder speed preset and returns the crf / crf like quality number required to reach a target VMAF. |

# encodeVideo Options
- **preset**: A preset to use to fill out unspecified data, this also effects input and output options.
- **complexFilter**: String, a complex filter to put into a ffmpeg command after the inputs and before the first maps.
- **commandIntoFile**: String, a file path to a text file to put the command into rather than running the command, does not contain shell escapes like quotes on file paths.
- **threads**: Integer, the -threads setting for ffmpeg.
- **allowNVDEC**: Boolean, weather to allow using nvidia hardware accelerated decoding.
- **allowNVENC**: Boolean, weather to allow using nvidia hardware accelerated encoding.
- **overwrite**: Boolean, weather to overwrite an existing media file.
- **allowFileCopy**: Boolean, weather to allow eligble inputs and outputs to be copied locally before or after processing.
- **loglevel**: String, -loglevel setting for ffmpeg.

## encodeVideo customArgs
customArgs is a setting that is available in many places, it is a list of arguments to pass to ffmpeg,
and within each arcument there can be a "&lt;code:$somephpcode&gt;" somewhere in the string,
each place has different variables available to it,
any refrences to source input number or stream type index variables are after the -1 expansion so you will not have a -1 as the value, the real value will be present.
The "&lt;code:$somephpcode&gt;" will be replaced with what its value is.

# encodeVideo Input options
Inputs is a list where each item can be a string for a file path or an array to contain extra information, the path has to be specified in the array.
- **path**: String, the path to the input file, not optional.
- **customArgs**: Array, a list of arguments to pass directly to ffmpeg infront of the input file, code variables available:
    - inputNumber: Integer, the current input number.
    - inputInfo: Array, similar to getVideoInfo, contains format and streams array, but streams are type indexed, so $inputInfo['streams']['v'][0]['pix_fmt'] is the pixel format for the first video, etc.
- **format**: String, the format to read the input as, ffmpegs -f option.
- **seek**: String, the amout of time to seek input side, ffmpegs -ss option.
- **to**: String, the timestamp to stop reading, ffmpegs -to option.
- **duration**: String, the duration of the input to read, ffmpegs -t option.
- **loop**: Integer, the number of times to loop the input, -1 is forever, 0 is normal runtime, 1 is loop once so twice runtime.
- **fps**: String with fraction, Float, or Integer, the framerate to read the input as, ffmpegs -framerate option input side.
- **realTime**: Boolean, weather to force ffmpeg to read the input in realtime, ffmpegs -re flag.
- **forceNVDEC**: Boolean, weather to force passing -hwaccell cuda to ffmpeg.

# encodeVideo Output options
Outputs is a list of arrays describing the output files ffmpeg should produce, each output needs path and streams set, and each stream needs a type and source.
- **path**: String, the path to the output file, not optional.
- **format**: String, the format to set the output to, ffmpegs -f option.
- **customArgs**: Array, a list of arguments to pass directly to ffmpeg infront of the output file, code variables available:
    - outputNumber: Integer, the current output number.
    - inputInfos: An array with the structure of $inputInfos[0]['streams']['v'][0] is the first input first video stream information.
- **faststart**: Boolean, weather to move the moov atom in mov family formats to the start of the file.
- **seek**: String, output side seek, frame accurate.
- **duration**: String, the duration of the output.
- **to**: String, the timestamp to stop outputting, ffmpegs -to option.
- **shortest**: Boolean, weather to stop outputting when its shortest source ends.
- **metadata**: Array, the keys are metadata names like "title" and values to put with the names like "My Film".
- **mapMetadata**: Integer, -1 is strip all metadata, other integer is copy from that input number.
- **mapChapters**: Integer, -1 is strip all chapters, other integer is copy from that input number.
- **streams**: A list of arrays, see below.

## encodeVideo Output streams
Each stream is an array that has to contain at least a type and source.
- **type**: String, the type of stream, accepts full names and single letter types, e.g. video, v, audio, a, subtitle, s, data, d.
- **source**: Array/String, a list containing where to get this streams data from, the first element is the input number, -1 for all, the second element is the stream type index for that input, -1 for all, optionally a third element can be set to true which is the equivelant to ffmpegs "?" operator in map, e.g. [0,0] would be input 0's first stream of the current type. If a string, it is the name of a complex_filter output not including the square brackets.
- **metadata**: Array, stream specific metadata, where the array keys are metadata names and values are metadata values.
- **disposition**: String, the disposition of the stream, e.g. default, forced, comment, etc.
- **customArgs**: Array, a list of arguments to pass directly to ffmpeg about the current stream, each argument starting with a dash has :type:typeindex added to the end, code variables available:
    - outputNumber: Integer, the current output number.
    - streamNumber: Integer, the current output stream number, type independent.
    - streamTypeIndex: Integer, the current output stream type index.
    - streamType: String, the type of the output stream, can be: v, a, s, or d for video, audio, subtitle, or data.
    - sourceInput: Integer, the input number the source of this stream is from.
    - sourceTypeIndex: Integer, the type index for the source of this stream.
    - sourceStreamInfo: Array, the ffprobe stream information for the source of this output stream.
    - sourceFormatInfo: Array, the format information for the input the source stream comes from.

## encodeVideo Output streams video/v
- **resW**: Integer, sets the width of the video.
- **resH**: Integer, sets the height of the video.
- If only one of resW or resH is set, the other is calculated using the aspect ratio of the input stream.
- **fps**: String with fraction, Float, or Integer, the framerate to set the output stream to.
- **bits**: Integer, sets the bit depth of the output video stream, default of 8.
- **chroma**: String, something like 420 or 422 or 444, the chroma subsampling to use.
- To automatically generate a pixFmt setting, either bits or chroma or both have to be set, if neither are set and pixFmt is not set, it does not change the source pixFmt.
- **pixFmt**: String, a bits and chroma override.
- **filter**: String, ffmpegs -filter:v:n, video filters for the stream, doesnt work when source is from a complex filter.
- **format**: String, the output format for the video, video_encoder then automatically selects an appropriate encoder, can be set to copy to copy the source stream information.
- **encoder**: String, an automatic encoder selection override, passed to -c:x:n in ffmpeg command.
- **maxRate**: Integer, the maximum bitrate in kilobits per second.
- **quality**: Integer/Float, a desired VMAF score for the output stream.
- **speed**: String, either slower, slow, medium, fast, faster.
- **range**: String, sets the range metadata for the video stream.
- **colorSpace**: String, sets the color space metadata for the video stream.
- **ColorPrimaries**: String, sets the color primaries metadata for the video stream.
- **gamma**: String, sets the gamma metadata for the video stream.

## encodeVideo Output streams audio/a
- **filter**: String, ffmpegs -filter:a:n, audio filters for the stream, doesnt work when source is from a complex filter.
- **channels**: Integer, the number of audio channels to mix to, -ac:a:n option in ffmpeg.
- **format**: String, the audio encoder, can be set to copy.
- **samples**: Integer, the number of samples per second override.
- **bitrate**: Integer, the bitrate in kilobits per second.

## encodeVideo Output streams subtitle/s
- **format**: String, the subtitle encoder, can be set to copy.

## encodeVideo Output streams data/d
- **format**: String, most of the time copy.

# encodeFolder Options
- **preset**: String, a preset name to read settings from the folder array in the preset file.
- **source**: String, a source override, only works if the $sourceFolder function argument is empty.
- **destination**: String, a destination override, only works if the $destinationFolder function argument is empty.
- **sourcePrefix**: String, a prefix for the source folder path.
- **sourceSuffix**: String, a suffix for the source folder path.
- **destPrefix**: String, a prefix for the destination folder path.
- **destSuffix**: String, a suffix for the destination folder path.
- **recursive**: Boolean, weather to recursively glob the source folder.
- **videoTypes**: Array, a list of file extensions to select input files, default of ["mp4","mov","mkv","avi"].
- **outExtension**: String, the file extension all output files should have, can be set to "same" to retain the source file extension, default of "same".
- **deleteSource**: Boolean, weather to delete the source after a successful encode.
- **stopfile**: String, a path to a file that if the file exists, the encode is stopped at the end of the current encode, default of "temp/video_encoder/stop".
- **filter**: String, a peice of php code thats value/return should be a be a boolean, such as a greater than comparison for a source bitrate, there is a $info variable available which contains the output from nameStreams from getVideoInfo for the input file, uses matchFilter function internally.
- **conductor**: Array, an array containing an ip and port, if set, encodeFolder will add the encode jobs to the specified conductor server instead of executing the encodes itself.

# Settings
- **presetsPath**: String, default of "videoencoder/presets", specifies the directory to keep presets in.
- **copyFilesToLocal**: Boolean, default of false, specifies weather to allow copying video files to and from temporary local storage when encoding a video.
- **allowNVDEC**: Boolean, weather to allow Nvidia accelerated decoding if available.
- **allowNVENC**: Boolean, weather to allow Nvidia accelerated encoding if available.
