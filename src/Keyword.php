<?php
function suggest($keyword)
{
    $return_data['items'] = [];
    $keyword = trim($keyword);
    $bilibili_suggest_url = 'https://s.search.bilibili.com/main/suggest?func=suggest&suggest_type=accurate&sub_type=tag&main_ver=v1&highlight=&bangumi_acc_num=1&special_acc_num=1&topic_acc_num=1&upuser_acc_num=3&tag_num=10&special_num=10&bangumi_num=10&upuser_num=3&term=' . urlencode($keyword);
    $curl_obj = curl_init();
    curl_setopt($curl_obj, CURLOPT_URL, $bilibili_suggest_url);
    curl_setopt($curl_obj, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1 Safari/605.1.15");
    curl_setopt($curl_obj, CURLOPT_RETURNTRANSFER, true);
    $out_html = curl_exec($curl_obj);
    curl_close($curl_obj);
    $result = json_decode($out_html, true);

    if (isset($result['result']['tag'])) {
        addItem($keyword, '', $return_data['items']);
        $keyword_result = $result['result']['tag'];
        foreach ($keyword_result as $k_r) {
            if($k_r['value'] == $keyword) continue;
            addItem($k_r['value'], '', $return_data['items']);
        }
    } else {
        addItem($keyword, '', $return_data['items']);
    }
    return json_encode($return_data);
}
function addItem($title, $subtitle, &$data)
{
    $item['valid'] = true;
    $item['title'] = $title;
    $item['arg'] = $title;
    $item['variables']['action'] = 'search';
    $item['mods']['ctrl']['valid'] = true;
    $item['mods']['ctrl']['arg'] = $title;
    $item['mods']['ctrl']['variables']['action'] = 'search_no_pic';
    $item['mods']['ctrl']['subtitle'] = "无缩略图模式进行搜索";
    $item['mods']['alt']['valid'] = true;
    $item['mods']['alt']['arg'] = 'https://search.bilibili.com/all?keyword=' . urlencode($title);
    $item['mods']['alt']['variables']['action'] = 'open_search';
    $item['mods']['alt']['subtitle'] = "直接打开搜索页";
    if (!empty($subtitle)) $item['subtitle'] = $subtitle;
    $data[] = $item;
}
