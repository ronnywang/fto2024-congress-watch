<?php

// 01:07:51,118 => 1 * 3600 + 7 * 60 + 51 + 0.118
$to_second = function($str) {
    list($str, $num) = explode(",", $str);
    $num = (float) ("0." . $num);

    $str = explode(":", $str);
    return $str[0] * 3600 + $str[1] * 60 + $str[2] + $num;
};

foreach (glob("result/whisperx-*.json") as $json_file) {
    $id = basename($json_file, ".json");
    $id = str_replace("whisperx-", "", $id);
    $target = __DIR__ . '/subtitles/' . $id . '.json';
    if (file_exists($target)) {
        continue;
    }
    error_log($target);

    $obj = json_decode(file_get_contents($json_file));
    $obj = $obj->job->result;

    $srt = $obj->srt;
    $lines = explode("\n", $srt);
    $lines = array_map("trim", $lines);
    $result = [];
    while (count($lines)) {
        $line = array_shift($lines); // seq
        if (empty($line)) {
            continue;
        }
        $line = array_shift($lines); // time
        $line = explode(" --> ", $line);
        $start = $line[0];
        $start = $to_second($start);

        $end = $line[1];
        $end = $to_second($end);

        $text = array_shift($lines); // text
        array_shift($lines); // empty
        $result[] = [
            "start" => $start,
            "end" => $end,
            "text" => $text,
        ];
    }

    file_put_contents($target, json_encode($result, JSON_UNESCAPED_UNICODE));
}
