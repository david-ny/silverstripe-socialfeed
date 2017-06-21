<?php

class SocialFeedManager extends Object {

    public function createFeeds() {
        $feeds = new FeedAggregateor();
        foreach ($this->config()->feeds as $feedConfig) {
             $config = new SocialFeedSettings();
             $config->setFeedID($feedConfig['feedID']);
             $config->setConnectionData($feedConfig['connectionData']);
             $socialFeedClass = $feedConfig['class'];
             $feeds->registerFeed(new $socialFeedClass($config));
        }
        return $feeds;
    }
}

class PostModel implements PostModelInterface {

    public static function getPostIDs($type, $userID) {
        $postIDs = SocialPost::get()
            ->setQueriedColumns(array('PostID'))
            ->filter(array(
                'PostType' => $type
                ,'UserID' => $userID
            ))
            ->map('ID', 'PostID')
            ->toArray();

        return $postIDs;
    }

    public static function getPosts($type, $userID) {

        return SocialPost::get()->filter(array(
            'PostType' => $type
            ,'UserID' => $userID
        ));

    }

    public static function getSourceData($type, $userID, $postID) {

        $post = SocialPost::get()
                ->setQueriedColumns(array('SourceData'))
                ->filter(array(
                    'PostType' => $type
                    ,'UserID' => $userID
                    ,'PostID' => $postID
                    )
                )
                ->first();

        return unserialize(base64_decode($post->SourceData));
    }

    public static function createPost($postType, $userID, $postID, $date, $sourceData) {

            $socialPost = SocialPost::create();
            $socialPost->UserID = $userID;
            $socialPost->PostID = $postID;
            $socialPost->PostType = $postType;
            $socialPost->Date = $date;
            $socialPost->SourceData = base64_encode(serialize($sourceData));
            $socialPost->write();

    }

    public static function updatePost($type, $userID, $postID, $sourceData) {
        $post = SocialPost::get()
            ->filter(array(
                'PostType' => $type
                ,'UserID' => $userID
                ,'PostID' => $postID
                )
            )
            ->first();
        $post->SourceData = base64_encode(serialize($sourceData));
        $post->write();
    }

    public static function deletePost($type, $userID, $postID) {
        $post = SocialPost::get()
            ->filter(array(
                'PostType' => $type
                ,'UserID' => $userID
                ,'PostID' => $postID
                )
            )
            ->first();
        $post->delete();
    }

    public static function getSyncData($type, $userID) {
        $socialPostSync =  SocialPostSync::get()
            ->filter(array(
                'PostType' => $type
                ,'UserID'  => $userID
             ))
             ->First();
        $syncData = new SyncData();
        if ($socialPostSync == null) {
             $syncData->lastSync = '1970-01-01 01:01:01';
        } else {
            $syncData->lastSync = $socialPostSync->LastSync;
        }
        $syncData->unreachableSince = $socialPostSync->UnreachableSince;
        $syncData->serviceUnreachable = $socialPostSync->ServiceUnreachable;
        return $syncData;
    }

    public static function setSyncData($type, $userID, $serviceUnreachableNow = false) {

        $socialPostSync = SocialPostSync::get()
            ->filter(array(
                'PostType' => $type
                ,'UserID' => $userID
            ))
            ->First();
        $lastSync = SS_Datetime::now()->format('Y-m-d H:i:s');

        if ($serviceUnreachableNow && !$socialPostSync->ServiceUnreachable) {
            $unreachableSince = SS_Datetime::now()->format('Y-m-d H:i:s');
            SocialFeedLogger::email($type, $userID, 'ServiceUnreachable');
        } else if ($serviceUnreachableNow && $socialPostSync->ServiceUnreachable) {
            $unreachableSince = $socialPostSync->UnreachableSince;
        } else {
            $unreachableSince = NULL;
        }

        if ($socialPostSync == null) {
             $socialPostSync = SocialPostSync::create();
             $socialPostSync->PostType = $type;
             $socialPostSync->UserID   = $userID;
             $socialPostSync->LastSync = $lastSync;
             $socialPostSync->ServiceUnreachable = $serviceUnreachableNow;
             $socialPostSync->UnreachableSince = $unreachableSince;
             $socialPostSync->write();
        } else {
            $socialPostSync->LastSync = $lastSync;
            $socialPostSync->ServiceUnreachable = $serviceUnreachableNow;
            $socialPostSync->UnreachableSince = $unreachableSince;
            $socialPostSync->write();
        }
    }
}


class SocialFeedLogger {

    public static function email($type, $userID, $messageType) {

        if ($messageType == 'ServiceUnreachable') {
            $subjectBase =
                Director::absoluteBaseURL() . ' ' .
                'SocialFeed: ' . $type . ' ' . $userID;
            $subject =
                $subjectBase . ' - ' .
                _t('SocialFeedLogger.ServiceUnreachable',"Service unreachable");
        } else {
            $subject = $subjectBase . ' - ' . $messageType;
        }

        $body = SS_Datetime::now()->format('Y-m-d H:i:s') . ' ' . $subject;
        $emailConfig = Config::inst()->get('SocialFeedLogger', 'email');
        $email = Email::create(
            $emailConfig['from']
            ,$emailConfig['to']
            // $this->config()->email['from']
            // ,$this->config()->email['to']
            ,$subject
            ,$body
        );
        //Debug::log($emailConfig['from'] . ' ' . $body.  "\n\n ");
        $email->send();
    }
}
