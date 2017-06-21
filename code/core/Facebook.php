<?php

class FacebookRequest extends SocialFeedRequest {

    protected function url() {
        $url = $this->connectionData['url']
            . '/' . $this->connectionData['user_id']
            . '/posts?fields=' . $this->connectionData['fields']
            . '&access_token=' . $this->connectionData['access_token'];
        return $url;
    }
}

class FacebookFeed extends SocialFeed {

    const TYPE = 'facebook';

    protected function getType() {
        return self::TYPE;
    }

    protected function createRequest($connectionData) {
        return new FacebookRequest($connectionData);
    }

    protected function createPost($type, $sourceData) {
        return new FacebookPost($type, $sourceData);
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

class FacebookPost extends SocialFeedPost {

    protected function setID() {
        $this->postID = $this->sourceData['id'];
    }

    protected function setDate() {
        $sourceData = SafeToAccessArray::create($this->sourceData);
        $updated_time = substr(
                str_replace('T', ' ', $sourceData['updated_time']),
                 0,19
        );
        $this->postDate = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $updated_time
        );
    }

    protected function setValidity() {
        $post = SafeToAccessArray::create($this->sourceData);
        $valid = true;
        if (empty($post)) {
            $valid = false;
        }
        if ($valid) {
            if (
                ($post['type']         == 'status') &&
                ($post['picture']            == '') &&
                ($post['messageSummary']     == '') &&
                ($post['descriptionSummary'] == '') &&
                ($post['captionSummary']     == '')
            ) {
                 $valid = false;
            }
        }
        $this->valid = $valid;
    }

    public function data() {
        $sourceData = SafeToAccessArray::create($this->sourceData);
        $picture = $sourceData['full_picture'];
        //$picture = $this->safeAccess($sourceData['full_picture']);
        $message = $sourceData['message'];
        $summary = HTMLText::create();
        $summary->setValue($message);
        $messageSummary = $summary->Summary(25);
        $summary->setValue($sourceData['description']);
        $descriptionSummary = $summary->Summary(25);
        $summary->setValue($sourceData['caption']);
        $captionSummary = $summary->Summary(25);
        $summary->setValue($sourceData['story']);
        $storySummary = $summary->Summary(25);
        $link = ($sourceData['link'] == '')
            ?
            'javascript:void(0);'
            :
            $sourceData['link']
        ;

        $post = ['postType' => $this->type
                ,'date' => $this->postDate->format('Y-m-d H:m:s')
                ,'dateToShow' => $this->postDate->format('h:i:s A - j M Y')
                ,'dow' => $this->postDate->format('l')
                ,'day' => $this->postDate->format('d')
                ,'moth_year' => $this->postDate->format('M') . ' '
                    . $this->postDate->format('Y')
                ,'story' => $sourceData['story']
                ,'message' => $message
                ,'picture' => $picture
                //,'photo' => $photo
                ,'name' => $sourceData['from']['name']
                ,'caption' => $sourceData['caption']
                ,'description' => $sourceData['description']
                ,'postLink' => $link
                ,'caption' => $sourceData['caption']
                ,'icon' => $sourceData['icon']
                ,'comment' => $sourceData['actions'][0]['link']
                ,'like' => $sourceData['actions'][1]['link']
                ,'type' => $sourceData['type']
                ,'status_type' => $sourceData['status_type']
                ,'postID' => $this->postID()
                ,'messageSummary' => $messageSummary
                ,'descriptionSummary' => $descriptionSummary
                ,'captionSummary' => $captionSummary
                ,'storySummary' => $storySummary
                ];
        return $post;
    }
}
