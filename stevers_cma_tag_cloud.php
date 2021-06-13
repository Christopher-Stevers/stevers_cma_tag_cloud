<?php
/**
*Plugin Name: Christopher Stever's Plugin for cma tag cloud example.
*Description: A plugin for a tag shortcode that points too the cma tags.
*/
?>


function stevers_shortcode_for_tag_cloud()
{
 return 'Hello to my shortcode'
}
add_shortcode('stevers_cma_tag_cloud','stevers_shortcode_for_tag_cloud')