<?php


class InstagramRequest extends SocialFeedRequest {

    protected function url() {
        $url = $this->connectionData['url']
            . '/' . $this->connectionData['user_id']
            . '/' . $this->connectionData['service']
            . '/?count=' . $this->connectionData['count']
            . '&access_token=' . $this->connectionData['access_token'];
        return $url;
    }
}


class InstagramFeed extends SocialFeed {

    const TYPE = 'instagram';

    protected function getType() {
        return self::TYPE;
    }

    protected function createRequest($connectionData) {
        return new InstagramRequest($connectionData);
    }

    protected function createPost($type, $sourceData) {
        return new InstagramPost($type, $sourceData);
    }

    protected function getPostsFromResponse($response) {
        $feedData = json_decode($response, true);
        if (isset($feedData['data']) && (!empty($feedData['data'])) ) {
            return $feedData['data'];
        } else {
            return array();
        }
    }
}

class InstagramPost extends SocialFeedPost {

    protected function setID() {
        $this->postID = $this->sourceData['id'];
    }

    protected function setDate() {
        $sourceData = $this->sourceData;
        $date = date('Y-m-d H:m:s', $sourceData['created_time']);
        $this->postDate = DateTime::createFromFormat('Y-m-d H:i:s', $date);
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
        $link = $sourceData['link'];
        $picture_low = $sourceData['images']['low_resolution']['url'];
        $picture_standard = $sourceData['images']['standard_resolution']['url'];
        if (isset ($sourceData['caption']['text'])) {
            $text = $sourceData['caption']['text'];
        } else {
            $text = '';
        }

        $post =  array(
                'postType' => $this->type
                ,'postLink' => $link
                ,'date' => $this->postDate->format('Y-m-d H:m:s')
                ,'dateToShow' => $this->postDate->format('h:i:s A - j M Y')
                ,'text' => $text
                ,'picture' => $picture_low
                ,'pictureStandard' => $picture_standard
                ,'postID' => $this->postID()
        );
        return $post;
    }
}
