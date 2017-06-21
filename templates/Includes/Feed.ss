
<% with $Posts %>
<div class="socialfeed">
    <a class="showNew" href="$Up.Link">New posts arrived. Click here to refresh.</a>
    <% loop $Posts %>
    <a class="socialItem  hide-for-small" href="$postLink">
       <div class="pict" style="background-image: url('$picture');" >
        <% if $picture == '' %>
                <img src="socialfeed/images/.png" alt="{$postType}">
        <% end_if %>
       </div>
       <div class="desc">
            <% if $postType == 'facebook' %> <% include FacebookPost %> <% end_if %>
            <% if $postType == 'instagram' || $postType == 'twitter' %> <% include Post %> <% end_if %>
            <% if $postType == 'youtube' %>  <% include YouTubePost %> <% end_if %>
            $Title
            <p class="date">$dateToShow</p>
            <img class="type" src="socialfeed/images/{$postType}.png" alt="{$postType}">
       </div>
    </a>
    <% end_loop %>
</div>
<% end_with %>
