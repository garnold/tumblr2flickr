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
    $tumblr_photos_and_captions = array();

    $tumblr_feed = json_decode($tumblr_json);
    foreach (array_reverse($tumblr_feed->response->posts) as $post) {
        $caption = $post->caption;
        foreach ($post->photos as $photo) {
            $tumblr_photos_and_captions[$photo->original_size->url] =
                empty($caption) ? null : htmlspecialchars_decode(strip_tags($caption));
        }
    }

    $flickr = new phpFlickr(T2F_FLICKR_API_KEY, T2F_FLICKR_SECRET, true);
    $flickr->setToken(T2F_FLICKR_TOKEN);

    $flickr_account = $flickr->people_findByUsername(T2F_FLICKR_USERNAME);
    $flickr_photos = $flickr->people_getPublicPhotos($flickr_account["id"], null, null, count($tumblr_photos_and_captions));

    foreach ($flickr_photos["photos"]["photo"] as $photo) {
        $photo_info = $flickr->photos_getInfo($photo["id"]);
        $description = $photo_info["photo"]["description"];

        reset($tumblr_photos_and_captions);
        while (list($photo, $caption) = each($tumblr_photos_and_captions)) {
            if (strpos($description, $photo) !== false) {
                unset($tumblr_photos_and_captions[$photo]);
            }
        }
    }

    print_r($tumblr_photos_and_captions);

    reset($tumblr_photos_and_captions);
    while (list($photo, $caption) = each($tumblr_photos_and_captions)) {
        $temp_file = tempnam(sys_get_temp_dir(), basename($photo) . ".");
        echo "\"" . $caption . "\" (" . $photo . ") => " . $temp_file . "\n";
        file_put_contents($temp_file, file_get_contents($photo));

        $flickr->async_upload($temp_file, $caption, $photo, null, true);
    }
}

curl_close($curl);