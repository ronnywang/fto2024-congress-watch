<?php

include(__DIR__ . '/config.php');
include(__DIR__ . '/CrawlLib.php');

for ($v = 55333; $v > 55000; $v --) {
    $target = __DIR__ . "/result/whisperx-jp-shugiintv-{$v}.json";
    if (file_exists($target)) {
        continue;
    }
    $url = "https://www.shugiintv.go.jp/jp/index.php?ex=VL&deli_id=$v&media_type=";;
    try {
        list($video_link, $id) = CrawlLib::getVideoLinkByURL($url);
    } catch (Exception $e) {
        continue;
    }
    echo $id . "\n";
    echo $video_link . "\n";

    CrawlLib::add_job('/queue/add', [
        'url' => $video_link,
        'tool' => 'whisperx',
        'language' => 'ja',
        'id' => $id,
    ]);
    CrawlLib::handle_jobs(function($id, $result){
        file_put_contents(__DIR__ . "/result/whisperx-{$id}.json", json_encode($result, JSON_UNESCAPED_UNICODE));
    });
}
