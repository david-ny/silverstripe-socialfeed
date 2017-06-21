<?php


class SocialFeedPage extends Page {

    private static $icon =  "SOCIALFEED_DIR/images/SocialFeedPage.png";

    private static $description = 'Page for displaying posts from social media pages';


    public function getCMSFields() {
        $fields = parent::getCMSFields();
        $gridFieldConfig = GridFieldConfig::create()->addComponents(
            new GridFieldToolbarHeader(),
            new GridFieldAddNewButton('toolbar-header-right'),
            new GridFieldSortableHeader(),
            new GridFieldFilterHeader(),
            new GridFieldDataColumns(),
            new GridFieldPaginator(500),
            new GridFieldPageCount('toolbar-header-right'),
            new GridFieldEditButton(),
            new GridFieldDeleteAction(),
            new GridFieldDetailForm()
        );
        $gridFieldConfig->getComponentByType('GridFieldDataColumns')
            ->setDisplayFields(array(
                'PostID'  => _t('SocialFeedPage.PostID',"PostID")
                ,'PostType'  => _t('SocialFeedPage.Type',"Type")
                ,'Date'    => _t('SocialFeedPage.Date',"Date")
        ));
        $gridField = new GridField (
            'posts'
            ,'Posts'
            ,SocialPost::get()
            ,$gridFieldConfig
        );
        $fields->addFieldToTab('Root.Posts', $gridField);
        return $fields;
    }

}




class SocialFeedPage_Controller extends Page_Controller {

    private $feeds;

    private static $allowed_actions = array (
        'sync'
    );

    public function sync(SS_HTTPRequest $request) {
        $syncStatus = $this->feeds->syncAllPosts();
        $changes = 0;
        foreach($syncStatus as $statusObj) {
            $changes +=
                $statusObj->created +
                $statusObj->updated +
                $statusObj->deleted;
        }
        $postsChanged = ($changes > 0)?true:false;
        $status = array('postsChanged' => $postsChanged);

        if (Permission::check("ADMIN")) {
            $status['data'] = $syncStatus;
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($status);
    }

   public function getPosts() {
        $allPosts = $this->feeds->getAllPosts();
        usort($allPosts, function($a, $b) {
            return ($a->date() < $b->date())?true:false;
        });
        $list = ArrayList::create(array());
        foreach ($allPosts as $post) {
             $list->push(
                ArrayData::create($post->data())
             );
        }
        return new ArrayData(array("Posts" => $list));
    }

    public function init() {
        parent::init();
        Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
        Requirements::javascript(SOCIALFEED_DIR . "/javascript/socialfeed.js");
        Requirements::css(SOCIALFEED_DIR . "/css/socialfeed.css");
        $this->feeds = SocialFeedManager::create()->createFeeds();
    }
}
