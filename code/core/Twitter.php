<?php

class TwitterRequest extends SocialFeedRequest {

    protected function buildBaseString($baseURI, $method, $params) {
        $r = array();
        ksort($params);
        foreach($params as $key=>$value){
            $r[] = "$key=" . rawurlencode($value);
        }
        return $method."&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
    }

    protected function buildAuthorizationHeader($oauth) {
        $r = 'Authorization: OAuth ';
        $values = array();
        foreach ($oauth as $key => $value) {
            $values[] = "$key=\"" . rawurlencode($value) . "\"";
        }
        $r .= implode(', ', $values);
        return $r;
    }

    protected function buildHeader() {
        $twitter_handle = $this->connectionData['twitter_handle'];
        $url = $this->connectionData['url'];
        $oauth_access_token = $this->connectionData['oauth_access_token'];
        $oauth_access_token_secret = $this->connectionData['oauth_access_token_secret'];
        $consumer_key = $this->connectionData['consumer_key'];
        $consumer_secret = $this->connectionData['consumer_secret'];
        $oauth = array(
            'oauth_consumer_key' => $consumer_key,
            'oauth_nonce' => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_token' => $oauth_access_token,
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0',
            'screen_name' => $twitter_handle
        );
        $base_info = $this->buildBaseString($url, 'GET', $oauth);
        $composite_key = rawurlencode($consumer_secret) . '&' . rawurlencode($oauth_access_token_secret);
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
        $oauth['oauth_signature'] = $oauth_signature;
        // Make Requests
        $header = array($this->buildAuthorizationHeader($oauth), 'Content-Type: application/json', 'Expect:');
        return $header;
    }

    protected function url() {
        return $this->connectionData['url'] . '?screen_name=' . $this->connectionData['twitter_handle'];
    }

    protected function setOptions() {
        $this->options = array(
            CURLOPT_URL => $this->url(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->buildHeader(),
            //CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        );
    }


}

class TwitterFeed extends SocialFeed {

    const TYPE = 'twitter';

    protected function getType() {
        return self::TYPE;
    }

    protected function createRequest($connectionData) {
        return new TwitterRequest($connectionData);
    }

    protected function createPost($type, $sourceData) {
        return new TwitterPost($type, $sourceData);
    }

    protected function getPostsFromResponse($response) {
        $feedData = json_decode($response, true);

        if (isset($feedData) && (!empty($feedData)) ) {
            return $feedData;
        } else {
            return array();
        }
    }
}

class TwitterPost extends SocialFeedPost {

    protected function setID() {
        $this->postID = $this->sourceData['id_str'];
    }

    protected function setDate() {
        $sourceData = $this->sourceData;
        $date_str = $sourceData['created_at'];
        $this->postDate = DateTime::createFromFormat(
            'D M d H:i:s ***** Y',
            $date_str
        );
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
        $link = $sourceData['text'];
        $picture = $sourceData['entities']['media']['0']['media_url'];
        $text = '';
        $post =  array(
                'postType' => $this->type
                ,'postLink' => $link
                ,'date' => $this->postDate->format('Y-m-d H:m:s')
                ,'dateToShow' => $this->postDate->format('h:i:s A - j M Y')
                ,'text' => $text
                ,'picture' => $picture
                ,'postID' => $this->postID()
        );
        return $post;
    }
}
