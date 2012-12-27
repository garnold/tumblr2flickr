<?php

set_include_path(get_include_path() . ":" . dirname(__FILE__) . "/phpFlickr-3.1");

require_once("t2f.config.php");
require_once("phpFlickr.php");

defined("T2F_TUMBLR_API_URL") or die;
defined("T2F_FLICKR_API_KEY") or die;
defined("T2F_FLICKR_SECRET") or die;
defined("T2F_FLICKR_TOKEN") or die;
defined("T2F_FLICKR_USERNAME") or die;

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, T2F_TUMBLR_API_URL);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
curl_setopt($curl, CURLOPT_FAILONERROR, true);

$tumblr_json = curl_exec($curl);

if (!curl_errno($curl)) {
    $tumblr_posts_by_id = array();
    $tumblr_photo_count = 0;

    $tumblr_feed = json_decode($tumblr_json);
    foreach (array_reverse($tumblr_feed->response->posts) as $post) {
        if ($post->type == "photo") {
            $tumblr_posts_by_id[$post->id] = $post;
            $tumblr_photo_count += count($post->photos);
        }
    }

    $flickr = new phpFlickr(T2F_FLICKR_API_KEY, T2F_FLICKR_SECRET, true);
    $flickr->setToken(T2F_FLICKR_TOKEN);

    $flickr_account = $flickr->people_findByUsername(T2F_FLICKR_USERNAME);
    $flickr_photos = $flickr->people_getPublicPhotos($flickr_account["id"], null, null, $tumblr_photo_count);

    foreach ($flickr_photos["photos"]["photo"] as $photo) {
        $photo_info = $flickr->photos_getInfo($photo["id"]);
        $description = $photo_info["photo"]["description"];

        unset($tumblr_posts_by_id[$description]);
    }

    while (list($id, $post) = each($tumblr_posts_by_id)) {
        $caption = html_entity_decode(strip_tags($post->caption), ENT_QUOTES | ENT_XML1, "UTF-8");

        foreach ($post->photos as $photo) {
            $url = $photo->original_size->url;
            $name = basename($url);
            
            $file = sys_get_temp_dir() . "/" . $name;
            file_put_contents($file, file_get_contents($url));
            
            printf("Uploading photo \"%s\" (%s) from post %s\n", $caption, $name, $id);
            $flickr->async_upload($file, $caption, $id, null, true);
            
            @unlink($file);
        }
    }
}

curl_close($curl);