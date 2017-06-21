<?php

class SocialPost extends DataObject {

    private static $db = array(
        'PostType'      => 'Varchar(128)'
        ,'UserID'       => 'Varchar(128)'
        ,'PostID'       => 'Varchar(255)'
        ,'Date'         => 'SS_Datetime'
        ,'SourceData'   => 'Text'
    );

    private static $has_one = array(
    );

//    private static $indexes = array(
//        'PostType_UserID_PostID' => array(
//            'type' => 'index',
//            'value' => '"PostType","UserID","PostID"'
//        )
//    );

    public function getCMSFields() {

        $decodedSourceData = new TextAreaField('DecodedSourceData');
        $decodedSourceData->setValue(
            print_r(unserialize(base64_decode($this->SourceData)), true)
        );

        return new FieldList(
            new TextField('PostID', 'PostID'),
            new TextField('PostType', 'PostType'),
            new TextField('Date', 'Date'),
            new TextField('SourceData', 'SourceData'),
            $decodedSourceData->performDisabledTransformation()
        );
    }


}

class SocialPostSync extends DataObject {

        private static $db = array(
            'PostType'     => 'Varchar(128)'
            ,'UserID'      => 'Varchar(128)'
            ,'LastSync'    => 'SS_Datetime'
            //whatever:
            //,'ReadCount'         => 'Int'       // since last sync
            //,'SyncReferenceTime' => 'SS_Datetime'
            //,'SyncCount'         => 'Int'       // since SyncReferenceTime
            ,'ServiceUnreachable' => 'Boolean(0)'
            ,'UnreachableSince' => 'SS_Datetime'
        );

        private static $has_one = array(
        );

}
