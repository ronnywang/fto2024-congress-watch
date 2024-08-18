<?php

class CrawlLib
{
    public static function getVideoLinkByURL($url)
    {
        // Ex: https://www.shugiintv.go.jp/jp/index.php?ex=VL&deli_id=55289&media_type=
        // Ex: https://www.webtv.sangiin.go.jp/webtv/detail.php?sid=8000#7552.8
        // Ex: https://w3.assembly.go.kr/main/player.do?ref=main&menu=1&mc=356&ct1=22&ct2=417&ct3=05&type=&no=&wv=1&
        if (strpos($url, 'shugiintv.go.jp') !== false) {
            $html = file_get_contents($url);
            $deli_id = null;
            if (!preg_match('#deli_id=([0-9]+)#', $url, $matches)) {
                throw new Exception("deli_id not found: " . $url);
            }
            $deli_id = $matches[1];
            if (!preg_match('#<input type="hidden" id="vtag_src_base_vod" value="([^"]*)"#', $html, $matches)) {
                throw new Exception("vtag_src_base_vod not found: " . $url);
            }
            $matches[1] = str_replace('http://', 'https://', $matches[1]);
            return [$matches[1], "jp-shugiintv-{$deli_id}"];
        }

        if (strpos($url, 'webtv.sangiin.go.jp') !== false) {
            $html = file_get_contents($url);
            if (!preg_match('#<script src="(https://public.mediasp.jp/v1/player\?hash=[^"]+)#', $html, $matches)) {
                throw new Exception("player src not found: " . $url);
            }
            $js = file_get_contents($matches[1]);
            if (!preg_match('#,url:"([^"]*)"#', $js, $matches)) {
                throw new Exception("url not found: " . $url);
            }
            return $matches[1];
        }

        // ct1 = 屆期(4年1屆)
        // ct2 = 總累積會議次數(22: 415 ~, 21: 379 ~ 414, 20: 343 ~ 378, 19: 307 ~ 342 ...)
        // ct3 = 會議次數(01, 02, 03 ...)
        // mc = 委員會種類( Ex: 325 司法, 345: 公共管理與安全 ...)
        // https://w3.assembly.go.kr/main/player.do?menu=1&mc=337&ct1=22&ct2=417&ct3=01&wv=1&
        // list: https://w3.assembly.go.kr/main/sub.do?menu=1&ct1=18&curPages=1
        // mc=337&ct1=22&ct2=417&ct3=01
    }

    public static $_jobs = [];

    public static function add_job($action, $params)
    {
        $get_params = [];
        $get_params[] = 'key=' . getenv('WHISPERAPI_KEY');
        foreach ($params as $k => $v) {
            $get_params[] = urlencode($k) . '=' . urlencode($v);
        }
        $url = sprintf("https://%s%s?%s", getenv('WHISPERAPI_HOST'), $action, implode('&', $get_params));
        $obj = json_decode(file_get_contents($url));
        $job_id = $obj->job_id ?? false;
        if (!$job_id) {
            throw new Exception("job_id not found: " . $url);
        }
        error_log("add job: {$obj->api_url}");
        self::$_jobs[] = [$job_id, $obj->api_url];
    }

    public static function handle_jobs($callback)
    {
        error_log('handle_jobs');
        $start_time = null;
        $data = null;
        if (count(self::$_jobs) == 0) {
            error_log("no job");
            sleep(60);
        }
        while (count(self::$_jobs) > 0) {
            list($job_id, $api_url) = self::$_jobs[0];
            if (is_null($start_time)) {
                $start_time = time();
                error_log(sprintf("(remain: %d) checking: %s", count(self::$_jobs), $api_url));
            }
            $obj = json_decode(file_get_contents($api_url));
            if ($obj->job->status != 'error' and $obj->job->status != 'done') {
                if (time() - $start_time > 6000) {
                    throw new Exception("timeout: " . $api_url);
                }
                sleep(1);
                continue;
            }
            $start_time = null;

            $id = $obj->job->data->id;
            $callback($id, $obj);
            array_shift(self::$_jobs);
            error_log("job done: {$id}");
        }
    }
}

