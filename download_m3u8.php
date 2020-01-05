<?php

/* 命令行方式参数获取 */
$long_opt = array(
    'url:',
);
$params = getopt('', $long_opt);

if (empty($params)) {
    echo "\n\nThis is a simple download m3u8 tool #_#\n
usage `php download_m3u8.php --url \"{url}\"` \n";
    exit();
}

if (empty($params['url'])) {
    echo "param `url` is needed\n";
}

$url = $params['url'];
download_m3u8($url);

/**
 * @param $url
 * @param string $dir
 */
function download_m3u8 ($url, $dir = '')
{
    try {
        $hash_name = md5($url);
        if (is_file("./tmp/{$hash_name}.m3u8")) {
            $content = file_get_contents("./tmp/{$hash_name}.m3u8");
        } else {
            $content = file_get_contents($url);
        }
    } catch (Exception $e) {
        echo "\n{$e}\n";
        exit();
    }

    file_put_contents("./tmp/{$hash_name}.m3u8", $content);

    // echo $content;
    if (
        preg_match_all(
            '/(http|https):\/\/.*/',
            $content,
            $matches
        ) or
        preg_match_all(
            '/.+\.ts/',
            $content,
            $matches
        )
    ) {
        if (!$dir) {
            $dir = "video/" . md5($url);
        }
        MakeDir($dir);

        echo "dir {$dir}\n";
        echo "\ndownload ts\n";

        $count = count($matches[0]);
        $ts_outputs_arr = [];

        foreach ($matches[0] as $key => $value) {

            if (strpos($value, 'http') === false) {
                $parse_url_result = parse_url($url);
                $url_path = $parse_url_result['path'];
                $arr = explode('/', $url_path);
                array_splice($arr, -1);
                $url_path_pre = $parse_url_result['scheme'] . "://" . $parse_url_result['host'] . implode('/', $arr) . "/";
                $value = $url_path_pre . $value;
            }

            $ts_output = "{$dir}/{$key}.ts";

            if (is_file($ts_output)) {
                $ts_outputs_arr[] = $ts_output;
                echo "\n{$ts_output} Already exists.\n";
                continue;
            }

            $cmd = "curl -L -o {$ts_output} '{$value}'";
            exec($cmd);
            echo "\n$cmd\n";

            if (is_file($ts_output)) {
                $ts_outputs_arr[] = $ts_output;
            } else {
                echo "create ts_output file failed ;\n $cmd";
                exit();
            }
        }

        if ($count > 100) {
            $to_concat = array_chunk($ts_outputs_arr, 100);
        } else {
            $to_concat[] = $ts_outputs_arr;
        }

        /*
        * ts 视频下载完成的节点
        * */
//        exit('ts 视频下载完成');

        echo "\nconcat ts to mp4\n";

        $mp4_outputs = [];
        foreach ($to_concat as $key => $value) {
            $str_concat = implode('|', $value);
            $mp4_output = "{$dir}/output{$key}.mp4";
            $cmd = "ffmpeg -i \"concat:{$str_concat}\" -acodec copy -vcodec copy -absf aac_adtstoasc {$mp4_output}";
            exec($cmd);
            echo "\n$cmd\n";
            if (is_file($mp4_output)) {
                $mp4_outputs[] = $mp4_output;
            } else {
                echo "create mp4_outputs file failed ;\n $cmd";
                exit();
            }
        }

//        dd($mp4_outputs);

        $last = "{$dir}/{$hash_name}.mp4";
        $file_list_str = '';
        if (count($mp4_outputs) > 1) {
            foreach ($mp4_outputs as $key => $value) {
                $file_list_str .= "file '{$value}'\n";
            }
            $file_list_file = "file_list.txt";
            file_put_contents($file_list_file, $file_list_str);

            $cmd = "ffmpeg -f concat -i {$file_list_file} -c copy {$last}";
            exec($cmd);
            echo "\n$cmd\n";
        } else {
            $mp4_output = "{$dir}/output0.mp4";
            rename($mp4_output, $last);
        }

        if (is_file($last)) {
            $cmd = "rm -rf {$dir}/*ts";
            exec($cmd);
            echo "\n$cmd\n";

            $cmd = "rm -rf {$dir}/output*.mp4";
            exec($cmd);
            echo "\n$cmd\n";

            $cmd = "rm -rf {$file_list_file}";
            exec($cmd);
            echo "\n$cmd\n";

            echo "\n\nsuccess {$last}\n";
        } else {
            echo "\n\nfailed\n";
        }


    }
}

/**
 * @param $dir
 * @return bool
 */
function MakeDir ($dir)
{
    return is_dir($dir) or
        (MakeDir(dirname($dir)) and
            mkdir($dir, 0777));
}
