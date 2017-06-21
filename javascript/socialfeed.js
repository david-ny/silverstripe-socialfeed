
jQuery.noConflict();

(function($) {

    $(window).load(function() {

        var Socialfeed = (function() {

            var getURL = function() {
                var baseUrl =  document.URL.split('?')[0];
                var url = ( $('body').hasClass('HomePage') )
                    ? baseUrl + 'home/sync'
                    : baseUrl + '/sync' ;
                return url;
            }

            var readSyncStatus = function() {
                $.ajax(getURL())
                    .done(function (response) {
                        if (response.postsChanged) {
                            showReload();
                        }
                        if ('data' in response) {
                            console.log('sync response:');
                            console.log(response);
                            console.log(JSON.stringify(response.data,null, 2));
                        }
                    })
                    .fail (function (xhr) {
                        console.log('SocialPost sync failed');
                        console.log('Error: ' + xhr.responseText);
                    });
            }

            var showReload = function() {
                //console.log('Refresh to see new posts on page...');
                $('.socialfeed .showNew').css({'display':'block'});
            }

            return {
                readSyncStatus: readSyncStatus
            };
        })();

        Socialfeed.readSyncStatus();

    });

})(jQuery);
