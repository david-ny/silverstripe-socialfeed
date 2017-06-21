<?php

class YouTubeRequest extends SocialFeedRequest {

    protected function url() {
        $url = $this->connectionData['url']
            . '&playlistId=' . $this->connectionData['playlistId']
            . '&key=' . $this->connectionData['key'];
        return $url;
    }
}


class YouTubeFeed extends SocialFeed {

    const TYPE = 'youtube';

    protected function getType() {
        return self::TYPE;
    }

    protected function createRequest($connectionData) {
        return new YouTubeRequest($connectionData);
    }

    protected function createPost($type, $sourceData) {
        return new YouTubePost($type, $sourceData);
    }

    protected function getPostsFromResponse($response) {
        $feedData = json_decode($response, true);
        if (isset($feedData['items']) && (!empty($feedData['items'])) ) {
            return $feedData['items'];
        } else {
            return array();
        }
    }
}

class YouTubePost extends SocialFeedPost {


    protected function setID() {
        $this->postID = $this->sourceData['id'];
    }

    protected function setDate() {
        $sourceData = $this->sourceData;
        $date_str = substr(
            str_replace('T', ' ', $sourceData['snippet']["publishedAt"]), 0,19 );
        //$this->postDate = DateTime::createFromFormat('D M d H:i:s ***** Y', $date_str);
        $this->postDate = DateTime::createFromFormat('Y-m-d H:i:s', $date_str);
    }

    protected function setValidity() {
        $post = $this->sourceData;
        $valid = true;
        if (empty($post)) {
            $valid = false;
        }
        $this->valid = $valid;
    }

    public function data() {
        $sourceData = $this->sourceData;
        $videoId = $sourceData['snippet']['resourceId']['videoId'];
        $link = 'https://www.youtube.com/watch?v=' . $videoId;
        $post =  array(
            'postType' => $this->type
            ,'postLink' => $link
            ,'date' => $this->postDate->format('Y-m-d H:m:s')
            ,'dateToShow' => $this->postDate->format('h:i:s A - j M Y')
            ,'title' => $sourceData['snippet']['title']
            ,'description' => $sourceData['snippet']['description']
            ,'picture' => $sourceData['snippet']['thumbnails']['medium']['url'] //320 x 180
            ,'picture_default' => $sourceData['snippet']['thumbnails']['default']['url'] //120 x 90
            ,'picture_medium' => $sourceData['snippet']['thumbnails']['medium']['url'] //320 x 180
            ,'picture_high' => $sourceData['snippet']['thumbnails']['high']['url'] // 480 x 360
            ,'picture_standard' => $sourceData['snippet']['thumbnails']['standard']['url'] // 640 x 480
            ,'picture_maxres' => $sourceData['snippet']['thumbnails']['maxres']['url'] // 1280 x 720
            ,'video_id' => $videoId
            ,'postID' => $this->postID()
        );
        return $post;
    }
}
