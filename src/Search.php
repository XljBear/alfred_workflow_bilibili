<?php
function search($keyword)
{
    $return_data['items'] = [];
    $mode = getenv('action');
    $keyword = trim($keyword);
    $page = 1;
    $has_page = false;
    $page_detect = explode(' ',$keyword);
    if(sizeof($page_detect) > 1 && is_numeric($page_detect[sizeof($page_detect) - 1])){
        $keyword = '';
        $page = intval($page_detect[sizeof($page_detect) - 1]);
        if($page != 1){
            $has_page = true;
        }
        foreach ($page_detect as $hp_k=>$hp_v){
            if($hp_k == (sizeof($page_detect) -1))break;
            $keyword .= $hp_v . ' ';
        }
        $keyword = trim($keyword);
    }
    if($keyword == ''){
        $no_result['valid'] = false;
        $no_result['title'] = '傻瓜～要输入关键字才能进行搜索哦(°∀°)ﾉ';
        echo json_encode(['items'=>[$no_result]]);
        exit();
    }
    $pic_path = 'pic/';
    $bilibili_search_url = 'https://search.bilibili.com/all?keyword=';
    $curl_obj = curl_init();
    curl_setopt($curl_obj, CURLOPT_URL, $bilibili_search_url . urlencode($keyword) . '&page=' . $page);
    curl_setopt($curl_obj, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1 Safari/605.1.15");
    curl_setopt($curl_obj, CURLOPT_RETURNTRANSFER, true);
    $search_page_html = curl_exec($curl_obj);
    curl_close($curl_obj);
    $js_start_pos = strpos($search_page_html,'__INITIAL_STATE__=') + strlen('__INITIAL_STATE__=');
    $js_end_pos = strpos($search_page_html,'};') + 1;
    $js = substr($search_page_html,$js_start_pos,$js_end_pos - $js_start_pos);
    $video_js_info = json_decode($js,true);
    $video_list = [];
    $user_list = [];
    $bangumi_list = [];
    $ft_list = [];
    if(is_dir($pic_path)){
        array_map('unlink', glob($pic_path.'*'));
    }
    if(!isset($video_js_info['flow']['getMixinFlowList-jump-keyword-'.$keyword]['result']) || sizeof($video_js_info['flow']['getMixinFlowList-jump-keyword-'.$keyword]['result']) < 1){
        $no_result['valid'] = false;
        $no_result['title'] = '对不起～木有找到任何结果呢...(´；ω；`)';
        echo json_encode(['items'=>[$no_result]]);
        exit();
    }
    addPageChange($video_js_info['flow']['getMixinFlowList-jump-keyword-'.$keyword],$keyword,$mode,$return_data['items']);
    foreach ($video_js_info['flow']['getMixinFlowList-jump-keyword-'.$keyword]['result'] as $f_result){
        if(!$has_page){
            switch ($f_result['result_type']){
                case 'media_bangumi':
                    $bangumi_list = $f_result['data'];
                    break;
                case 'media_ft':
                    $ft_list = $f_result['data'];
                    break;
                case 'bili_user':
                    $user_list = $f_result['data'];
                    break;
                case 'video':
                    $video_list = $f_result['data'];
                    break;
            }
        }else {
            switch ($f_result['type']) {
                case 'media_bangumi':
                    $bangumi_list[] = $f_result;
                    break;
                case 'media_ft':
                    $ft_list[] = $f_result;
                    break;
                case 'bili_user':
                    $user_list[] = $f_result;
                    break;
                case 'video':
                    $video_list[] = $f_result;

            }
        }
    }
    foreach ($user_list as $u_list){
        $title = "【Up主:{$u_list['uname']} [Lv:{$u_list['level']}]】";
        if($u_list['is_live']){
            $title .= "【正在直播】";
        }
        $fans = formatNumebr($u_list['fans']);
        $subtitle = "视频数量:{$u_list['videos']} / 粉丝数量:{$fans}";
        if(strpos($mode,'no_pic')>1){
            $icon = '';
        }else{
            download('https:' . $u_list['upic'],$pic_path);
            $icon = $pic_path . pathinfo($u_list['upic'], PATHINFO_BASENAME);
        }
        $roomid = $u_list['room_id'];
        $mid = $u_list['mid'];
        addUserItem($title,$subtitle,$icon,$mid,$roomid,$return_data['items']);
    }
    foreach ($video_list as $v_list){
        if(strpos($mode,'no_pic')>1){
            $icon = '';
        }else{
            download('https:' . $v_list['pic'],$pic_path);
            $icon = $pic_path . pathinfo($v_list['pic'], PATHINFO_BASENAME);
        }
        $title = strip_tags($v_list['title']);
        $pubdate = date('Y-m-d H:i:s',$v_list['pubdate']);
        $fav_number = formatNumebr($v_list['favorites']);
        $play_number = formatNumebr($v_list['play']);
        $subtitle = "Up:{$v_list['author']} / 时长:{$v_list['duration']} / 发布时间:{$pubdate} / 收藏:{$fav_number} / 播放次数:{$play_number}";
        $mid = $v_list['mid'];
        $aid = $v_list['aid'];
        addVideoItem($title,$subtitle,$icon,$aid,$mid,$return_data['items']);
    }
    if(sizeof($return_data['items']) <= 1){
        $no_result['valid'] = false;
        $no_result['title'] = '对不起～木有找到任何结果呢...(´；ω；`)';
        echo json_encode(['items'=>[$no_result]]);
        exit();
    }
    addPageChange($video_js_info['flow']['getMixinFlowList-jump-keyword-'.$keyword],$keyword,$mode,$return_data['items']);
    return json_encode($return_data);
}
function addPageChange($page_json,$keyword,$mode,&$data){
    $now_page = $page_json['extra']['page'];
    $total_page = $page_json['extra']['numPages'];
    if($total_page == 1){
        return;
    }
    $total_results = $page_json['extra']['numResults'];
    $item['title'] = "------ {$now_page} / {$total_page} ------";
    if($now_page > 1){
        $prev_page = $now_page - 1;
        $item['mods']['ctrl']['valid'] = true;
        $item['mods']['ctrl']['arg'] = "{$keyword} {$prev_page}";
        if(strpos($mode,'no_pic')>1) {
            $item['mods']['ctrl']['variables']['action'] = 'prev_page_no_pic';
        }else{
            $item['mods']['ctrl']['variables']['action'] = 'prev_page';
        }
        $item['mods']['ctrl']['subtitle'] = "按回车键回到上一页";
    }
    $next_page = $now_page + 1;
    if($now_page < $total_page){
        $item['valid'] = true;
        $item['arg'] = "{$keyword} {$next_page}";
        if(strpos($mode,'no_pic')>1){
            $item['variables']['action'] = 'next_page_no_pic';
        }else{
            $item['variables']['action'] = 'next_page';
        }
        if($now_page == 1){
            $item['subtitle'] = "共有{$total_results}条记录，直接按回车键向下翻页";
        }else{
            $item['subtitle'] = "共有{$total_results}条记录，按住control键向上翻页、直接按回车键向下翻页";
        }
    }else{
        $item['valid'] = false;
        $item['subtitle'] = "共{$total_results}条记录，按住control键向上翻页";
    }
    $data[] = $item;
}
function addVideoItem($title,$subtitle,$icon,$video_aid,$mid,&$data)
{
    $item['valid'] = true;
    $item['title'] = $title;
    $item['arg'] = 'https://www.bilibili.com/video/av' . $video_aid;
    $item['variables']['action'] = 'open_result';
    if(!empty($icon)) $item['icon']['path'] = $icon;
    $item['mods']['ctrl']['valid'] = true;
    $item['mods']['ctrl']['arg'] = 'https://space.bilibili.com/' .$mid;
    $item['mods']['ctrl']['variables']['action'] = 'open_author';
    $item['mods']['ctrl']['subtitle'] = "打开该Up的主页";
    if (!empty($subtitle)) $item['subtitle'] = $subtitle;
    $data[] = $item;
}
function addUserItem($title,$subtitle,$icon,$mid,$roomid,&$data)
{
    $item['valid'] = true;
    $item['title'] = $title;
    $item['arg'] = 'https://space.bilibili.com/' .$mid;
    $item['variables']['action'] = 'open_author';
    if(!empty($icon)) $item['icon']['path'] = $icon;
    $item['mods']['ctrl']['valid'] = true;
    $item['mods']['ctrl']['arg'] = 'https://live.bilibili.com/' .$roomid;
    $item['mods']['ctrl']['variables']['action'] = 'open_live';
    $item['mods']['ctrl']['subtitle'] = "打开该Up的直播间";
    if (!empty($subtitle)) $item['subtitle'] = $subtitle;
    $data[] = $item;
}
function formatNumebr($number){
    $number_string = '';
    if($number < 1000){
        $number_string = $number;
    }elseif ($number >= 1000 && $number < 10000){
        $number_string = number_format($number / 1000,2) . '千';
    }else if($number >= 10000){
        $number_string = number_format($number / 10000,2) . '万';
    }
    return $number_string;
}
function download($url, $path)
{
    if(!is_dir($path)){
        @mkdir($path);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    $file = curl_exec($ch);
    curl_close($ch);
    $filename = pathinfo($url, PATHINFO_BASENAME);
    $resource = fopen($path . $filename, 'a');
    fwrite($resource, $file);
    fclose($resource);
}
