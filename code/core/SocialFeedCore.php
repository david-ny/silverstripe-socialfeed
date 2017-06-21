<?php

interface SocialFeedRequestInterface {
    public function send();
    public function hasFailed(); //TODO:find a better name
    public function getResponseCode();
    public function getResponseBody();
}

interface SocialFeedInterface {
    public function getPostsFromFeed();
    public function getPostsFromDB();
    public function canSync();
    public function syncPosts();
}

interface SocialFeedPostInterface {
    public function postID();
    public function date();
    public function valid();
    public function sourceData();
    public function data();
}

interface PostModelInterface {
    public static function getPostIDs($type, $userID);
    public static function getPosts($type, $userID);
    public static function getSourceData($type, $userID, $postID);
    public static function createPost($postType, $userID, $postID, $date, $sourceData);
    public static function updatePost($type, $userID, $postId, $sourceData);
    public static function getSyncData($type, $userID);
    public static function setSyncData($type, $userID, $serviceUnreachableNow = false);
}

class SocialFeedRequest implements SocialFeedRequestInterface {

    protected $connectionData;
    protected $curl;
    protected $options;
    protected $responseCode;
    protected $responseBody;
    protected $failed = false;
    protected $curl_error;

    public function __construct($connectionData) {
        $this->connectionData = $connectionData;
    }

    protected function url() {
        return $this->connectionData->url;
    }

    protected function setOptions() {
        $this->options = array(
            CURLOPT_URL => $this->url(),
            CURLOPT_RETURNTRANSFER => true, // return it instead of output directly
            CURLOPT_FAILONERROR => true,    // let curl perceive http error codes
        );

    }

    protected function build() {
        $this->setOptions();
        $this->curl = curl_init();
        curl_setopt_array($this->curl, $this->options);
    }

    public function send() {
        $this->build();
        $response = curl_exec($this->curl);


        //TODO
        $err = curl_error($this->curl);
        if ($err) {
            $this->failed = true;
            $this->curl_error = $err;
        }

        $this->responseCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $this->responseBody = $response;

        //because you can't get the response body from curl if CURLOPT_FAILONERROR is true
        if ($err && $this->responseCode > 0) {
            $this->build();
            curl_setopt($this->curl, CURLOPT_FAILONERROR, false);
            curl_setopt($this->curl, CURLOPT_HTTP200ALIASES, (array)400);
        }
        $this->responseBody = curl_exec($this->curl);

        curl_close($this->curl);
    }

    public function hasFailed() {
        return $this->failed;
    }

    public function getResponseCode() {
        return $this->responseCode;
    }

    public function getResponseBody() {
        return $this->responseBody;
    }

    public function getCurlError() {
        return $this->curl_error;
    }
}

abstract class SocialFeed implements SocialFeedInterface {

    protected $postType;
    protected $syncStatus;
    protected $connectionData;
    protected $request;
    protected $requestError;
    protected $feedID; //userID
    protected $savedPostsIDs = array();
    protected $feedsPostsIDs = array();
    protected $postsFromFeed = array();

    public function __construct($settings) {
        $this->connectionData = $settings->getConnectionData();
        $this->feedID = $settings->getFeedID();
        $this->syncStatus = new SyncStatus($this->getType(), $this->feedID);
    }

    protected abstract function getType();

    protected abstract function createPost($type, $sourceData);

    protected abstract function createRequest($connectionDataArray);

    //extract the posts array from the decoded response
    protected abstract function getPostsFromResponse($feed);


    public function getFeedID() {
        return $this->feedID;
    }

    public function getPostsFromFeed() {
        $this->request = $this->createRequest($this->connectionData);
        $this->request->send();
        if ($this->request->hasFailed()) {
            return false;
        }
        $response = $this->request->getResponseBody();
        $postsInFeed = $this->getPostsFromResponse($response);
        $posts = array();

        foreach ($postsInFeed as $postInFeed) {
            $this->syncStatus->in_response++;
            $post = $this->createPost($this->getType(), $postInFeed);
            if ($post->valid()) {
                $index = $post->date() .'_'.$post->postID();
                $posts[$index] = $post->sourceData();
            } else {
                $this->syncStatus->invalid++;
            }
        }
        return $posts;
    }

    public function getPostsFromDB() {
        $socialPosts = PostModel::getPosts($this->getType(), $this->getFeedID());
        $posts = array();
        foreach ($socialPosts as $socialPost) {
            $post = $this->createPost(
                $this->getType(),
                unserialize(base64_decode(($socialPost->SourceData)))
            );
            $posts[$post->date() . ' ' . $post->postID()] = $post;
        }
        return $posts;
    }

    //the default canSync function
    //minimum 10 minutes has to pass between two syncs
    public function canSync() {
        // return true; //for testing
        $syncData = PostModel::getSyncData($this->getType(), $this->getFeedID());
        $lastSync = new DateTime($syncData->lastSync);
        $now = new DateTime;
        $dateInterval = DateInterval::createFromDateString('10 minutes');
        $dateToCompare = $lastSync->add($dateInterval);
        return ($dateToCompare <= $now)?true:false;
    }

    public function canDelete() {
        if (!$this->request->hasFailed()) {
            return true;
        }
        $syncData = PostModel::getSyncData($this->getType(), $this->getFeedID());
        $unreachableSince = new DateTime($syncData->unreachableSince);
        $now   = new DateTime;
        //minimum one week has to pass before deleting all posts
        //becouse of an unreachable server
        //TODO: make this time configurable per feed
        //TODO: add remaining time to syncstatus
        $dateInterval = DateInterval::createFromDateString('7 days');
        $dateToCompare = $unreachableSince->add($dateInterval);
        $enoughTimePassed = ($dateToCompare <= $now)?true:false;
        return ($syncData->serviceUnreachable && $enoughTimePassed)?true:false;
    }

    protected function syncInit() {
        $this->syncStatus->should_sync = 'yes';
        $this->syncStatus->sync_started = 'yes';
        // $this->syncStatus->userID = $this->getFeedID();
        $this->savedPostsIDs = PostModel::getPostIDs(
            $this->getType(),
            $this->getFeedID()
        );
    }

    protected function syncRead() {
        $this->postsFromFeed = $this->getPostsFromFeed();
        if (!$this->postsFromFeed) {
            array_push($this->syncStatus->errors, 'Couldn\'t access feed');
            array_push($this->syncStatus->errors, $this->request->getCurlError());
            array_push(
                $this->syncStatus->errors,
                'Http response code: ' . $this->request->getResponseCode()
            );
            if ($this->request->getResponseCode() > 0) {
                array_push(
                    $this->syncStatus->errors,
                    $this->request->getResponseBody()
                );
            }
            PostModel::setSyncData($this->getType(), $this->getFeedID(), true);
            return FALSE;
        } else {
            PostModel::setSyncData($this->getType(), $this->getFeedID(), false);
            return TRUE;
        }
    }

    protected function syncWrite() {
        $posts = array();
        if (count($this->postsFromFeed) > 0) {
            foreach ($this->postsFromFeed as $feedItem) {
                $post = $this->createPost($this->getType(), $feedItem);
                if ($post->valid()) {
                    $posts[] = $post;
                    $this->feedsPostsIDs[] = $post->postID();
                }
            }
        }
        $posts = array_reverse($posts);
        foreach ($posts as $post) {
            $this->writePost($post, $this->savedPostsIDs);
        }
    }

    protected function syncDelete() {
        foreach ($this->savedPostsIDs as $savedPostID) {
             if (!in_array($savedPostID, $this->feedsPostsIDs)) {
                PostModel::deletePost(
                    $this->getType(),
                    $this->getFeedID(),
                    $savedPostID
                );
                $this->syncStatus->deleted++;
            }
        }
    }

    public function syncPosts() {
        if (!$this->canSync()) {
            return  $this->syncStatus;
        }
        $this->syncInit();
        if ($this->syncRead()) {
           $this->syncWrite();
        }
        if ($this->canDelete()) {
            $this->syncDelete();
        }
        if (!$this->request->hasFailed()) {
            $this->syncStatus->sync_finished = 'yes';
        }
        return $this->syncStatus;
    }


    protected function writePost($post, $savedPostsIDs) {
        if (in_array($post->postID(), $savedPostsIDs)) {
            //update post if modified
            $savedSourceData = PostModel::getSourceData(
                $this->getType(),
                $this->getFeedID(),
                $post->postID()
            );
            if ($savedSourceData != $post->sourceData()) {
                PostModel::updatePost(
                    $this->getType(),
                    $this->getFeedID(),
                    $post->postID(),
                    $post->sourceData()
                );
                $this->syncStatus->updated++;
            } else {
                $this->syncStatus->identical++;
            }
        } else {
            //save post (create)
            PostModel::createPost(
                $this->getType(),
                $this->getFeedID(),
                $post->postID(),
                $post->date(),
                $post->sourceData()
            );
            $this->syncStatus->created++;
        }
    }
}

abstract class SocialFeedPost implements SocialFeedPostInterface {

    protected $type = '';
    protected $postID = '';
    protected $postDate; //DateTime
    protected $sourceData = array();
    protected $valid = false;


    public function __construct($type, $sourceData) {
        $this->type = $type;
        $this->sourceData = $sourceData;
        $this->setID();
        $this->setDate();
        $this->setValidity();
    }

    abstract protected function setID();
    abstract protected function setDate();
    abstract protected function setValidity();
    abstract public function data();

    public function valid() {
        return $this->valid;
    }

    public function postID() {
        return $this->postID;
    }

    public function date() {
        return $this->postDate->format('Y-m-d H:m:s');
    }

    public function sourceData() {
        return $this->sourceData;
    }
}

class SyncData {
    public $lastSync = '';
    public $serviceUnreachable = false;
    public $unreachableSince = null;
}

class SyncStatus {
    public $type = '';
    public $userID = '';
    public $should_sync   = 'no';
    public $sync_started  = 'no';
    public $sync_finished = 'no';
    public $in_response = 0;
    public $invalid     = 0;
    public $identical   = 0;
    public $updated     = 0;
    public $created     = 0;
    public $deleted     = 0;
    public $errors        = array();

    public function __construct($type, $userID) {
        $this->type = $type;
        $this->userID = $userID;
    }
}

class SocialFeedSettings {

    protected $feedID;
    protected $connectionData;

    public function getFeedID() {
        return $this->feedID;
    }

    public function getConnectionData() {
        return $this->connectionData;
    }

    public function setFeedID($feedID) {
        $this->feedID = $feedID;
    }

    public function setConnectionData($connectionData) {
        $this->connectionData = $connectionData;
    }
}

class FeedAggregateor {

    protected $feeds;

    public function registerFeed($feed) {
        $this->feeds[] = $feed;
    }

    public function removeFeed($feed) {
        $i = array_search($feed, $this->feeds);
        if ($i >= 0) {
            unset($this->feeds[$i]);
        }
    }

    public function syncAllPosts() {
        $syncStatus = array();
        foreach ($this->feeds as $feed) {
            $syncStatus[] = $feed->syncPosts();
        }
        return $syncStatus;
    }

    public function getAllPosts() {
        $allPosts = array();
        foreach ($this->feeds as $feed) {
            $posts = $feed->getPostsFromDB();
            $allPosts =  array_merge($allPosts, $posts);
        }
        krsort($allPosts);
        return $allPosts;
    }


}


class SafeToAccessArray implements ArrayAccess, IteratorAggregate {

    private $collection = array();

    public static function create($arr) {
        $pigArr = new SafeToAccessArray((array) $arr);
        foreach ($pigArr as $key => $value) {
            if (is_array($value)) {
                $pigArr[$key] = self::create($value);
            }
        }
        return $pigArr;
    }

    public function __construct(array $array, $default = NULL) {
        $this->collection = $array;
        $this->default = $default;
    }

    public function offsetExists($offset) {
        return isset($this->collection[$offset]);
    }

    //multidimensianal arrays:
    //NULL automatically casted to an Array.
    //String offset cast?
    public function offsetGet($offset) {
        return isset($this->collection[$offset])
        ?
        $this->collection[$offset] : NULL;
    }

    public function offsetSet($offset, $value) {
        $this->collection[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->collection[$offset]);
    }

    public function getIterator() {
        return new ArrayIterator($this->collection);
    }
}
