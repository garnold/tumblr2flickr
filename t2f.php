<?php

set_include_path(get_include_path() . ":" . dirname(__FILE__) . "/phpFlickr-3.1");

define("T2F_TUMBLR_API_POST_LIMIT", 20);
define("T2F_FLICKR_API_POST_LIMIT", 500);

require_once("t2f.config.php");
require_once("phpFlickr.php");

defined("T2F_TUMBLR_API_URL") or die;
defined("T2F_FLICKR_API_KEY") or die;
defined("T2F_FLICKR_SECRET") or die;
defined("T2F_FLICKR_TOKEN") or die;
defined("T2F_FLICKR_USERNAME") or die;

defined("T2F_TUMBLR_POSTS_TO_SYNC") or define("T2F_TUMBLR_POSTS_TO_SYNC", T2F_TUMBLR_API_POST_LIMIT);

$tumblr_posts_by_id = array();
$tumblr_photo_count = 0;

for ($offset = 0; $offset < T2F_TUMBLR_POSTS_TO_SYNC; $offset += T2F_TUMBLR_API_POST_LIMIT) {
    $url = T2F_TUMBLR_API_URL . "&limit=" . T2F_TUMBLR_API_POST_LIMIT . "&offset={$offset}";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);

    $tumblr_json = curl_exec($curl);

    if (!curl_errno($curl)) {
        foreach (json_decode($tumblr_json)->response->posts as $post) {
            if ($post->type == "photo") {
                $tumblr_posts_by_id[$post->id] = $post;
                $tumblr_photo_count += count($post->photos);
            }
        }
    }
    else {
        printf("%s%s", curl_error($curl), PHP_EOL);
    }

    curl_close($curl);
}

$tumblr_posts_by_id = array_reverse($tumblr_posts_by_id, true);

$flickr = new phpFlickr(T2F_FLICKR_API_KEY, T2F_FLICKR_SECRET, true);
$flickr->setToken(T2F_FLICKR_TOKEN);

$flickr_account = $flickr->people_findByUsername(T2F_FLICKR_USERNAME);

for ($page = 1; $page <= ceil($tumblr_photo_count / T2F_FLICKR_API_POST_LIMIT); $page++) {
    $per_page = min($tumblr_photo_count, T2F_FLICKR_API_POST_LIMIT);
    $flickr_photos = $flickr->people_getPublicPhotos($flickr_account["id"], null, null, $per_page, $page);

    foreach ($flickr_photos["photos"]["photo"] as $photo) {
        $photo_info = $flickr->photos_getInfo($photo["id"]);
        $description = $photo_info["photo"]["description"];

        unset($tumblr_posts_by_id[$description]);
    }
}

while (list($id, $post) = each($tumblr_posts_by_id)) {
    $caption = html_entity_decode(strip_tags($post->caption), ENT_QUOTES, "UTF-8");

    foreach ($post->photos as $photo) {
        $url = $photo->original_size->url;
        $name = basename($url);
        
        printf("Syncing photo \"%s\" (%s) from post %s%s", $caption, $name, $id, PHP_EOL);

        $file = sys_get_temp_dir() . "/" . $name;
        file_put_contents($file, file_get_contents($url));
        
        if (is_readable($file) && filesize($file) > 0) {
            $flickr->async_upload($file, $caption, $id, null, true);
        }
        else {
            printf("File size was 0%s", PHP_EOL);
        }
        
        @unlink($file);
    }
}
