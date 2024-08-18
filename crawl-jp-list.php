<?php
include(__DIR__ . '/config.php');
include(__DIR__ . '/CrawlLib.php');
$all_committees = json_decode(file_get_contents('committees.json'));

$get_html = function($url) {
    $cache = __DIR__ . '/html-cache/' . md5($url);
    if (file_exists($cache)) {
        return file_get_contents($cache);
    }
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    // agent
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36');
    $html = curl_exec($curl);
    file_put_contents($cache, $html);
    return $html;
};

fputcsv(STDOUT, ['id', 'country', 'date', 'title', 'video_link', 'tags']);
$output_ret = function($ret) {
    fputcsv(STDOUT, [
        $ret->id,
        $ret->country,
        $ret->date,
        $ret->title,
        $ret->video_link,
        implode(',', $ret->tags),
    ]);
};

$name_committees = [];
foreach ($all_committees as $country_committees) {
    list($country, $committees) = $country_committees;
    foreach ($committees as $committee) {
        $name = $committee->labels->{$country}->value;
        if ($country == 'ko') {
            $name = str_replace('대한민국 국회 ', '', $name);
        }
        $name_committees[$name] = $committee;
    }
}

/*
// korea video
for ($d = 1; $d < 10; $d ++) {
    $url = sprintf("https://w3.assembly.go.kr/main/service/list.do?cmd=subList&menu=1&ct1=22&curPages=%d", $d);
    $json = $get_html($url);
    $obj = json_decode($json);
    foreach ($obj->confList as $conf) {
        $ret = new StdClass;
        $ret->id = sprintf("kr-%d-%d-%d-%d", $conf->mc, $conf->ct1, $conf->ct2, $conf->ct3);
        $ret->country = 'kr';
        $ret->date = $conf->confDate;
        $ret->title = $conf->confTitle;

        $url = sprintf("https://w3.assembly.go.kr/main/service/movie.do?cmd=movieInfo&mc=%d&ct1=%d&ct2=%d&ct3=%d", $conf->mc, $conf->ct1, $conf->ct2, $conf->ct3);



        print_r($conf);
        exit;
    }
    print_r($obj);
    exit;
}
 */

// jp video
for ($v = 55333; $v > 55000; $v --) {
    $target = __DIR__ . "/result/whisperx-jp-shugiintv-{$v}.json";
    if (!file_exists($target)) {
        continue;
    }
    $url = "https://www.shugiintv.go.jp/jp/index.php?ex=VL&deli_id=$v&media_type=";;
    $ret = new StdClass;
    $doc = new DOMDocument;
    $content = $get_html($url);
    @$doc->loadHTML($content);
    $table_dom = $doc->getElementsByTagName('table')->item(0);
    try {
        list($video_link, $id) = CrawlLib::getVideoLinkByURL($url);
    } catch (Exception $e) {
        continue;
    }
    if (!preg_match('#(\d+)年(\d+)月(\d+)日#', $doc->saveHTML($table_dom), $matches)) {
        continue;
    }
    $ret->id = $id;
    $ret->country = 'jp';
    $ret->date = sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
    $ret->title = $table_dom->getElementsByTagName('tr')->item(1)->getElementsByTagName('td')->item(3)->nodeValue;
    $ret->video_link = $video_link;
    $ret->tags = [];
    if (preg_match('#(.*)委員会#', $ret->title, $matches)) {
        $name = $matches[0];
        if (array_key_exists($name, $name_committees)) {
            $ret->tags[] = 'committee-' . $name_committees[$name]->id;
        }
    }

    $output_ret($ret);
}
